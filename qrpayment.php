<?php
/**
 * Módulo de Pagos QR - Versión 4.2.4 (Clean Config)
 * Eliminado campo de Logo en configuración general.
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
        $this->version = '4.2.0';
        $this->author = 'Experto PrestaShop';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->controllers = array('process', 'validation');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '9.99.99');

        parent::__construct();

        $this->displayName = $this->l('Pagos y Transferencias QR (Global)');
        $this->description = $this->l('Gestión profesional de pagos QR (Yape, Plin, etc) con validación de comprobantes.');
        $this->confirmUninstall = $this->l('¿Está seguro de eliminar el módulo y sus datos?');
    }

    public function install()
    {
        // 1. Directorios Seguros
        $img_dir = _PS_MODULE_DIR_ . $this->name . '/img/';
        if (!is_dir($img_dir)) { @mkdir($img_dir, 0755, true); }
        if (!file_exists($img_dir . 'index.php')) { file_put_contents($img_dir . 'index.php', ''); }

        $root_voucher = _PS_ROOT_DIR_ . '/img/vouchers/';
        if (!is_dir($root_voucher)) { @mkdir($root_voucher, 0755, true); }
        if (!file_exists($root_voucher . 'index.php')) { file_put_contents($root_voucher . 'index.php', ''); }

        // 2. Valores por Defecto
        if (!Configuration::hasKey('QRPAYMENT_TITLE')) Configuration::updateValue('QRPAYMENT_TITLE', 'Pago con la aplicación QR');
        if (!Configuration::hasKey('QRPAYMENT_DESC')) Configuration::updateValue('QRPAYMENT_DESC', 'Escanea el código QR y adjunta tu comprobante.');

        // 3. Instalación completa
        return parent::install() &&
            $this->installOrderState() &&
            $this->installDb() &&
            $this->installTab() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('header') &&
            $this->registerHook('displayAdminOrderMainBottom');
    }

    public function uninstall()
    {
        return parent::uninstall() &&
               $this->uninstallTab() &&
               $this->uninstallDb();
    }

    /**
     * LÓGICA DE CONFIGURACIÓN
     */
    public function getContent()
    {
        $output = '';

        // 1. Procesar Guardado
        if (Tools::isSubmit('submitQrPaymentConf')) {
            $output .= $this->postProcessConfig();
        }

        // 2. Botón para ir a Gestionar Apps
        $app_manager_url = $this->context->link->getAdminLink('AdminQrPayment');
        $output .= '
        <div class="panel">
            <div class="panel-heading"><i class="icon-cogs"></i> ' . $this->l('Gestión de Cuentas QR') . '</div>
            <div class="row" style="margin-top: 20px;">
                <div class="col-lg-12">
                    <p>'. $this->l('Configura aquí los textos generales. Para gestionar tus cuentas (Yape, Plin, Bancos), usa el siguiente botón:') .'</p>
                    <a class="btn btn-primary btn-lg" href="' . $app_manager_url . '">
                        <i class="icon-list-ul"></i> ' . $this->l('ADMINISTRAR APPS Y CUENTAS') . '
                    </a>
                </div>
            </div>
        </div>';

        // 3. Renderizar Formulario
        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitQrPaymentConf';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        $order_states = OrderState::getOrderStates($this->context->language->id);

        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Configuración General'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    // Activar/Desactivar
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Activar método de pago'),
                        'name' => 'QRPAYMENT_ACTIVE',
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'active_on', 'value' => true, 'label' => $this->l('Sí')),
                            array('id' => 'active_off', 'value' => false, 'label' => $this->l('No')),
                        ),
                    ),
                    // Título
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'desc' => $this->l('Título que verá el cliente en el checkout (ej: Pago con QR).'),
                        'name' => 'QRPAYMENT_TITLE',
                        'label' => $this->l('Título de pago'),
                    ),
                    // Descripción
                    array(
                        'col' => 6,
                        'type' => 'textarea',
                        'desc' => $this->l('Instrucciones cortas que aparecen debajo del título.'),
                        'name' => 'QRPAYMENT_DESC',
                        'label' => $this->l('Descripción de Checkout'),
                    ),
                    // Estado Éxito
                    array(
                        'type' => 'select',
                        'label' => $this->l('Estado de pago en caso de éxito'),
                        'name' => 'QRPAYMENT_SUCCESS_STATUS',
                        'options' => array(
                            'query' => $order_states,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ),
                    ),
                    // Estado Error
                    array(
                        'type' => 'select',
                        'label' => $this->l('Estado de pago en caso de fallo'),
                        'name' => 'QRPAYMENT_ERROR_STATUS',
                        'options' => array(
                            'query' => $order_states,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Guardar'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'QRPAYMENT_ACTIVE' => Configuration::get('QRPAYMENT_ACTIVE', true),
            'QRPAYMENT_TITLE' => Configuration::get('QRPAYMENT_TITLE'),
            'QRPAYMENT_DESC' => Configuration::get('QRPAYMENT_DESC'),
            'QRPAYMENT_SUCCESS_STATUS' => Configuration::get('QRPAYMENT_SUCCESS_STATUS'),
            'QRPAYMENT_ERROR_STATUS' => Configuration::get('QRPAYMENT_ERROR_STATUS'),
        );
    }

    protected function postProcessConfig()
    {
        // Guardar Textos y Estados (Sin lógica de logo)
        Configuration::updateValue('QRPAYMENT_ACTIVE', (bool)Tools::getValue('QRPAYMENT_ACTIVE'));
        Configuration::updateValue('QRPAYMENT_TITLE', Tools::getValue('QRPAYMENT_TITLE'));
        Configuration::updateValue('QRPAYMENT_DESC', Tools::getValue('QRPAYMENT_DESC'));
        Configuration::updateValue('QRPAYMENT_SUCCESS_STATUS', (int)Tools::getValue('QRPAYMENT_SUCCESS_STATUS'));
        Configuration::updateValue('QRPAYMENT_ERROR_STATUS', (int)Tools::getValue('QRPAYMENT_ERROR_STATUS'));

        return $this->displayConfirmation($this->l('Configuración actualizada correctamente.'));
    }

    // --- FUNCIONES DE INSTALACIÓN ---

    public function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminQrPayment';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Gestión Pagos QR';
        }
        $tab->id_parent = (int)Tab::getIdFromClassName('AdminParentPayment');
        $tab->module = $this->name;
        return $tab->add();
    }

    public function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminQrPayment');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    public function installDb()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "qrpayment` (
            `id_qrpayment` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `phone` varchar(50) DEFAULT NULL,
            `email` varchar(255) DEFAULT NULL,
            `max_amount` decimal(20,2) DEFAULT '0.00',
            `image_path` varchar(255) DEFAULT NULL,
            `icon_path` varchar(255) DEFAULT NULL,
            `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
            `position` int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_qrpayment`)
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        return Db::getInstance()->execute($sql);
    }

    public function uninstallDb()
    {
        return Db::getInstance()->execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "qrpayment`");
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
            $state->module_name = $this->name;
            if ($state->add()) {
                Configuration::updateValue('QR_PAYMENT_STATE_ID', (int)$state->id);
                if(!Configuration::hasKey('QRPAYMENT_SUCCESS_STATUS')) Configuration::updateValue('QRPAYMENT_SUCCESS_STATUS', (int)$state->id);
                if(!Configuration::hasKey('QRPAYMENT_ERROR_STATUS')) Configuration::updateValue('QRPAYMENT_ERROR_STATUS', Configuration::get('PS_OS_CANCELED'));
            }
        }
        return true;
    }

    // --- HOOKS ---

    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/qrpayment.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/qrpayment.js');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) return [];
        if (!Configuration::get('QRPAYMENT_ACTIVE')) return [];

        $apps = Db::getInstance()->executeS("SELECT * FROM " . _DB_PREFIX_ . "qrpayment WHERE active = 1 ORDER BY position ASC");

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

    public function hookDisplayAdminOrderMainBottom($params) {
        if(!isset($params['id_order'])) return '';

        $file = 'voucher_' . (int)$params['id_order'] . '.jpg';
        $path = _PS_ROOT_DIR_ . '/img/vouchers/' . $file;
        $url = file_exists($path) ? __PS_BASE_URI__ . 'img/vouchers/' . $file : false;
        $this->context->smarty->assign(['voucher_img' => $url]);
        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }
}
