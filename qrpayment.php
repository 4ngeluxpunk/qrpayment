<?php
/**
 * Módulo de Pagos QR - Versión 4.0.1 (Security Fix)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class QrPayment extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'qrpayment';
        $this->tab = 'payments_gateways';
        $this->version = '4.0.0'; // Incrementamos versión por seguridad
        $this->author = 'Experto PrestaShop';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->controllers = array('process');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '9.99.99'); 

        parent::__construct();

        $this->displayName = $this->l('Pagos y Transferencias QR (Global)');
        $this->description = $this->l('Pago mediante Apps QR con validación de voucher en modal.');
        $this->confirmUninstall = $this->l('¿Eliminar módulo?');
    }

    public function install()
    {
        // SEGURIDAD: Permisos 0755 en lugar de 0777
        $img_dir = _PS_MODULE_DIR_ . $this->name . '/img/';
        if (!is_dir($img_dir) && !@mkdir($img_dir, 0755, true)) {
            $this->_errors[] = $this->l('Error permisos carpeta img módulo');
            return false;
        }
        // Evitar Directory Listing
        @file_put_contents($img_dir . 'index.php', '');

        $root_voucher = _PS_ROOT_DIR_ . '/img/vouchers/';
        if (!is_dir($root_voucher) && !@mkdir($root_voucher, 0755, true)) {
            $this->_errors[] = $this->l('Error permisos carpeta /img/vouchers/');
            return false;
        }
        // Evitar Directory Listing en vouchers (Muy importante)
        @file_put_contents($root_voucher . 'index.php', '');

        if (!parent::install() ||
            !$this->installOrderState() ||
            !$this->registerHook('paymentOptions') ||
            !$this->registerHook('header') ||
            !$this->registerHook('displayAdminOrderMainBottom') ||
            !$this->installDb()) {
            return false;
        }

        Configuration::updateValue('QRPAYMENT_TITLE', 'Pago con la aplicación QR');
        Configuration::updateValue('QRPAYMENT_DESC', 'Método de pago para pagar con varias aplicaciones QR');

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('QR_PAYMENT_STATE_ID');
        Configuration::deleteByName('QRPAYMENT_TITLE');
        Configuration::deleteByName('QRPAYMENT_DESC');
        Configuration::deleteByName('QRPAYMENT_SUCCESS_STATUS');
        Configuration::deleteByName('QRPAYMENT_ERROR_STATUS');
        return parent::uninstall() && $this->uninstallDb();
    }

    public function installDb()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "qrpayment_apps` (
            `id_app` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `phone` varchar(50) DEFAULT NULL,
            `email` varchar(255) DEFAULT NULL,
            `max_amount` decimal(20,2) DEFAULT '0.00',
            `image_path` varchar(255) DEFAULT NULL,
            `icon_path` varchar(255) DEFAULT NULL,
            `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
            `position` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id_app`)
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        return Db::getInstance()->execute($sql);
    }

    public function uninstallDb()
    {
        return Db::getInstance()->execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "qrpayment_apps`");
    }

    protected function installOrderState()
    {
        if (!Configuration::get('QR_PAYMENT_STATE_ID') || !Validate::isLoadedObject(new OrderState(Configuration::get('QR_PAYMENT_STATE_ID')))) {
            $state = new OrderState();
            $state->name = array();
            foreach (Language::getLanguages(false) as $lang) {
                $state->name[$lang['id_lang']] = ($lang['iso_code'] == 'es') ? 'En espera de validación QR' : 'Awaiting QR Validation';
            }
            $state->send_email = false;
            $state->color = '#800000';
            $state->hidden = false;
            $state->delivery = false;
            $state->logable = false;
            $state->invoice = false;
            $state->module_name = $this->name; // Buena práctica vincularlo al módulo
            
            if ($state->add()) {
                Configuration::updateValue('QR_PAYMENT_STATE_ID', (int)$state->id);
                Configuration::updateValue('QRPAYMENT_SUCCESS_STATUS', (int)$state->id);
                Configuration::updateValue('QRPAYMENT_ERROR_STATUS', (int)Configuration::get('PS_OS_CANCELED'));
            }
        }
        return true;
    }

    public function hookHeader()
    {
        // Solo cargar CSS/JS en las páginas necesarias para mejorar WPO
        if (Tools::getIsset('configure') && Tools::getValue('configure') == $this->name) {
             $this->context->controller->addCSS($this->_path . 'views/css/qrpayment_admin.css', 'all');
             $this->context->controller->addJS($this->_path . 'views/js/admin_qrpayment.js');
             return;
        }

        // Cargar en checkout y en validation
        $controller = $this->context->controller->php_self;
        if ($controller == 'order' || $controller == 'order-opc' || $this->context->controller instanceof QrPaymentValidationModuleFrontController) {
            $this->context->controller->addCSS($this->_path . 'views/css/qrpayment.css', 'all');
            $this->context->controller->addJS($this->_path . 'views/js/qrpayment.js');
        }
    }

    // --- FRONTEND ---

    public function hookPaymentOptions($params)
    {
        if (!$this->active) return [];
        
        $apps = Db::getInstance()->executeS("SELECT * FROM " . _DB_PREFIX_ . "qrpayment_apps WHERE active = 1 ORDER BY position ASC");
        
        if (empty($apps)) return [];

        $locale = $this->context->getCurrentLocale();
        $currency = $this->context->currency;

        foreach ($apps as &$app) {
            $app['formatted_max_amount'] = ($app['max_amount'] > 0) ? $locale->formatPrice($app['max_amount'], $currency->iso_code) : '';
        }

        $processUrl = $this->context->link->getModuleLink($this->name, 'process', [], true);
        $totalToPay = $params['cart']->getOrderTotal(true, Cart::BOTH);
        
        $this->context->smarty->assign([
            'qr_apps' => $apps,
            'qr_img_base_url' => $this->_path . 'img/',
            'qr_desc' => Configuration::get('QRPAYMENT_DESC'),
            'qr_process_url' => $processUrl,
            'total_to_pay' => $locale->formatPrice($totalToPay, $currency->iso_code),
        ]);
        
        $newOption = new PaymentOption();
        $newOption->setCallToActionText(Configuration::get('QRPAYMENT_TITLE'))
                  ->setModuleName($this->name)
                  ->setAdditionalInformation($this->fetch('module:qrpayment/views/templates/hook/payment_infos.tpl'));

        return [$newOption];
    }
    
    // --- ADMIN / BACKOFFICE ---
    
    public function getContent()
    {
        $base_url = AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');

        if (Tools::isSubmit('statusqrpayment_apps') && $id_app = (int)Tools::getValue('id_app')) {
            $this->toggleStatus($id_app);
            Tools::redirectAdmin($base_url . '&conf=5');
        }

        if (Tools::isSubmit('updatePosition') && Tools::isSubmit('id_app')) {
            $this->updateAppPosition((int)Tools::getValue('id_app'), (int)Tools::getValue('position'));
        }
        
        $msg = '';
        
        if (Tools::isSubmit('submitQrApp')) {
            $this->postProcessApp();
            Tools::redirectAdmin($base_url . '&conf=3');
        }
        
        if (Tools::isSubmit('deleteqrpayment_apps')) {
            Db::getInstance()->delete('qrpayment_apps', 'id_app = '.(int)Tools::getValue('id_app'));
            Tools::redirectAdmin($base_url . '&conf=1');
        }
        
        if (Tools::isSubmit('submitQrGlobal')) {
            Configuration::updateValue('QRPAYMENT_TITLE', Tools::getValue('QRPAYMENT_TITLE'));
            Configuration::updateValue('QRPAYMENT_DESC', Tools::getValue('QRPAYMENT_DESC'));
            Configuration::updateValue('QRPAYMENT_SUCCESS_STATUS', (int)Tools::getValue('QRPAYMENT_SUCCESS_STATUS'));
            Configuration::updateValue('QRPAYMENT_ERROR_STATUS', (int)Tools::getValue('QRPAYMENT_ERROR_STATUS'));
            $msg = $this->displayConfirmation($this->l('Configuración general guardada.'));
        }
        
        if (Tools::isSubmit('addNewApp') || Tools::isSubmit('updateqrpayment_apps')) {
            return $msg . $this->renderAppForm();
        }

        return $msg . $this->renderGlobalForm() . $this->renderAppList();
    }

    public function updateAppPosition($id_app, $position)
    {
        $result = (bool)Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'qrpayment_apps` SET
            `position` = ' . (int)$position . '
            WHERE `id_app` = ' . (int)$id_app
        );
        // Respuesta segura para AJAX
        if (Tools::isSubmit('updatePosition')) {
            die(json_encode(['hasError' => !$result]));
        }
    }

    protected function postProcessApp() {
        $name = Tools::getValue('name');
        
        // SEGURIDAD EN SUBIDA DE IMAGENES BACKOFFICE
        $icon_path = Tools::getValue('current_icon');
        if (isset($_FILES['icon_path']) && !empty($_FILES['icon_path']['tmp_name'])) {
            if ($error = ImageManager::validateUpload($_FILES['icon_path'], 4000000)) {
                 $this->context->controller->errors[] = $error;
            } else {
                $ext = pathinfo($_FILES['icon_path']['name'], PATHINFO_EXTENSION);
                $file_name = 'icon_' . md5(uniqid()) . '.' . $ext;
                if (move_uploaded_file($_FILES['icon_path']['tmp_name'], _PS_MODULE_DIR_ . $this->name . '/img/' . $file_name)) {
                    $icon_path = $file_name;
                }
            }
        }
        
        $qr_path = Tools::getValue('current_qr');
        if (isset($_FILES['image_path']) && !empty($_FILES['image_path']['tmp_name'])) {
            if ($error = ImageManager::validateUpload($_FILES['image_path'], 4000000)) {
                 $this->context->controller->errors[] = $error;
            } else {
                $ext = pathinfo($_FILES['image_path']['name'], PATHINFO_EXTENSION);
                $file_name = 'qr_' . md5(uniqid()) . '.' . $ext;
                if (move_uploaded_file($_FILES['image_path']['tmp_name'], _PS_MODULE_DIR_ . $this->name . '/img/' . $file_name)) {
                    $qr_path = $file_name;
                }
            }
        }

        $data = [
            'name' => pSQL($name),
            'phone' => pSQL(Tools::getValue('phone')),
            'email' => pSQL(Tools::getValue('email')), 
            'max_amount' => (float)Tools::getValue('max_amount'),
            'icon_path' => pSQL($icon_path),
            'image_path' => pSQL($qr_path),
            'active' => (int)Tools::getValue('active')
        ];

        if ($id = (int)Tools::getValue('id_app')) {
            Db::getInstance()->update('qrpayment_apps', $data, 'id_app = ' . $id);
        } else {
            $max = (int)Db::getInstance()->getValue('SELECT MAX(position) FROM '._DB_PREFIX_.'qrpayment_apps');
            $data['position'] = $max + 1;
            Db::getInstance()->insert('qrpayment_apps', $data);
        }
    }

    // ... (Métodos renderGlobalForm, renderAppList, renderAppForm se mantienen prácticamente igual, 
    // solo asegúrate de usar (int) al recuperar valores de la DB o pSQL al insertar si haces consultas manuales)

    protected function renderGlobalForm() {
        // (Código original se mantiene, es seguro porque usa HelperForm)
        // ... (Copia el contenido original aquí) ...
        // Para brevedad, asumo que mantienes el código original de renderGlobalForm
        
        // REPETIR CODIGO ORIGINAL DE renderGlobalForm
        $states = OrderState::getOrderStates($this->context->language->id);
        
        $fields_form = [['form' => [
            'legend' => ['title' => $this->l('Configuración General'), 'icon' => 'icon-cogs'],
            'input' => [
                ['type' => 'text', 'label' => $this->l('Título'), 'name' => 'QRPAYMENT_TITLE'],
                ['type' => 'text', 'label' => $this->l('Descripción'), 'name' => 'QRPAYMENT_DESC'],
                ['type' => 'select', 'label' => $this->l('Estado caso éxito'), 'name' => 'QRPAYMENT_SUCCESS_STATUS', 'options' => ['query' => $states, 'id' => 'id_order_state', 'name' => 'name']],
                ['type' => 'select', 'label' => $this->l('Estado caso fallo'), 'name' => 'QRPAYMENT_ERROR_STATUS', 'options' => ['query' => $states, 'id' => 'id_order_state', 'name' => 'name']],
            ], 
            'submit' => ['title' => $this->l('Guardar'), 'name' => 'submitQrGlobal', 'class' => 'btn btn-default pull-right']
        ]]];
        
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        
        $helper->fields_value['QRPAYMENT_TITLE'] = Configuration::get('QRPAYMENT_TITLE');
        $helper->fields_value['QRPAYMENT_DESC'] = Configuration::get('QRPAYMENT_DESC');
        $helper->fields_value['QRPAYMENT_SUCCESS_STATUS'] = Configuration::get('QRPAYMENT_SUCCESS_STATUS');
        $helper->fields_value['QRPAYMENT_ERROR_STATUS'] = Configuration::get('QRPAYMENT_ERROR_STATUS');
        
        return $helper->generateForm($fields_form);
    }

    protected function renderAppList() {
        // (Código original se mantiene)
        // REPETIR CODIGO ORIGINAL DE renderAppList
        $apps = Db::getInstance()->executeS("SELECT * FROM "._DB_PREFIX_."qrpayment_apps ORDER BY position ASC");
        $count = count($apps); 
        
        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = ['edit', 'delete']; 
        $helper->identifier = 'id_app';
        $helper->position_identifier = 'position';
        $helper->position_identifier_field = 'position';
        
        $helper->list_id = 'qrpayment_apps_list';
        $helper->is_module = true; 
        
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->title = $this->l('Apps QR');
        $helper->listTotal = $count;
        $helper->table = 'qrpayment_apps';
        
        $helper->toolbar_btn['new'] = [
            'href' => $helper->currentIndex . '&addNewApp&token=' . $helper->token,
            'desc' => $this->l('Nueva App')
        ];

        $fields_list = [
            'id_app' => ['title' => $this->l('ID'), 'align' => 'center', 'class' => 'fixed-width-xs'],
            'name' => ['title' => $this->l('Nombre')],
            'active' => ['title' => $this->l('Activo'), 'align' => 'center', 'active' => 'status', 'type' => 'bool', 'orderby' => false],
            'phone' => ['title' => $this->l('Teléfono / Cuenta')],
            'formatted_max_amount' => ['title' => $this->l('Monto Máximo'), 'align' => 'right', 'orderby' => false],
        ];

        $locale = $this->context->getCurrentLocale();
        foreach ($apps as &$app) {
             if ((float)$app['max_amount'] > 0) {
                $app['formatted_max_amount'] = $locale->formatPrice($app['max_amount'], $this->context->currency->iso_code);
            } else {
                $app['formatted_max_amount'] = '-';
            }
        }

        return $helper->generateList($apps, $fields_list);
    }

    protected function renderAppForm() {
        // (Código original se mantiene)
        // REPETIR CODIGO ORIGINAL DE renderAppForm
        $id_app = (int)Tools::getValue('id_app');
        $app = $id_app ? Db::getInstance()->getRow("SELECT * FROM "._DB_PREFIX_."qrpayment_apps WHERE id_app = $id_app") : [];
        
        $icon_url = (isset($app['icon_path']) && $app['icon_path']) ? $this->_path . 'img/' . $app['icon_path'] : false;
        $qr_url = (isset($app['image_path']) && $app['image_path']) ? $this->_path . 'img/' . $app['image_path'] : false;

        $fields_form = [['form' => [
            'legend' => [
                'title' => ($id_app ? $this->l('Editar App QR') : $this->l('Crear Nueva App QR')), 
                'icon' => 'icon-qrcode'
            ], 
            'input' => [
                ['type' => 'switch', 'label' => 'Activar', 'name' => 'active', 'values' => [['id' => 'on', 'value' => 1, 'label' => 'Sí'], ['id' => 'off', 'value' => 0, 'label' => 'No']]],
                ['type' => 'text', 'label' => 'Nombre', 'name' => 'name', 'required' => true],
                
                ['type' => 'file', 'label' => 'Icono (80x80)', 'name' => 'icon_path', 'display_image' => true, 'image' => $icon_url ? '<img src="'.$icon_url.'" style="max-width: 80px; border: 1px solid #ccc;"/>' : null, 'hint' => $this->l('Sube el logo o icono de la aplicación QR.')],
                
                ['type' => 'file', 'label' => 'QR Imagen', 'name' => 'image_path', 'display_image' => true, 'image' => $qr_url ? '<img src="'.$qr_url.'" style="max-width: 150px; border: 1px solid #ccc;"/>' : null, 'hint' => $this->l('Sube la imagen del código QR para el pago.')],
                
                ['type' => 'text', 'label' => 'Teléfono', 'name' => 'phone'],
                ['type' => 'text', 'label' => 'Email', 'name' => 'email'],
                ['type' => 'text', 'label' => 'Monto Max', 'name' => 'max_amount', 'hint' => $this->l('Monto máximo permitido para este método. Dejar en 0 para ilimitado.')],
                ['type' => 'hidden', 'name' => 'current_icon'],
                ['type' => 'hidden', 'name' => 'current_qr'],
                ['type' => 'hidden', 'name' => 'id_app']
            ], 
            'submit' => ['title' => 'Guardar', 'name' => 'submitQrApp', 'class' => 'btn btn-default pull-right'],
            'buttons' => [
                'cancel_button' => [
                    'title' => $this->l('Cancelar'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                    'icon' => 'process-icon-cancel'
                ]
            ]
        ]]];
        
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        
        foreach(['active'=>1, 'name'=>'', 'phone'=>'', 'email'=>'', 'max_amount'=>'', 'id_app'=>$id_app, 'current_icon'=>'', 'current_qr'=>''] as $k=>$v) 
            $helper->fields_value[$k] = isset($app[$k]) ? $app[$k] : $v;
        if(isset($app['icon_path'])) $helper->fields_value['current_icon'] = $app['icon_path'];
        if(isset($app['image_path'])) $helper->fields_value['current_qr'] = $app['image_path'];
        
        return $helper->generateForm($fields_form);
    }
    
    public function hookDisplayAdminOrderMainBottom($params) {
        $id_order = (int)$params['id_order'];
        $file = 'voucher_' . $id_order . '.jpg';
        $path = _PS_ROOT_DIR_ . '/img/vouchers/' . $file;
        
        // Uso de file_exists básico para rendimiento, aunque Tools::file_exists_cache es opción
        $url = file_exists($path) ? __PS_BASE_URI__ . 'img/vouchers/' . $file : false;
        
        $this->context->smarty->assign(['voucher_img' => $url]);
        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }
    
    public function toggleStatus($id_app) {
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'qrpayment_apps` SET active = 1 - active WHERE id_app = '.(int)$id_app);
    }
}