<?php
/**
 * Módulo de Pagos QR - Versión 4.2.0 (Con Posicionamiento)
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
        $this->description = $this->l('Pago mediante Apps QR. Gestión profesional con ordenamiento.');
        $this->confirmUninstall = $this->l('¿Eliminar módulo y sus datos?');
    }

    public function install()
    {
        $img_dir = _PS_MODULE_DIR_ . $this->name . '/img/';
        if (!is_dir($img_dir) && !@mkdir($img_dir, 0755, true)) { return false; }

        $root_voucher = _PS_ROOT_DIR_ . '/img/vouchers/';
        if (!is_dir($root_voucher) && !@mkdir($root_voucher, 0755, true)) { return false; }

        if (!Configuration::hasKey('QRPAYMENT_TITLE')) Configuration::updateValue('QRPAYMENT_TITLE', 'Pago con la aplicación QR');
        if (!Configuration::hasKey('QRPAYMENT_DESC')) Configuration::updateValue('QRPAYMENT_DESC', 'Método de pago para pagar con varias aplicaciones QR');

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

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminQrPayment'));
    }

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

    /**
     * Instala la DB INCLUYENDO LA COLUMNA POSITION
     */
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

    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/qrpayment.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/qrpayment.js');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) return [];

        // Ahora ordenamos por POSITION en lugar de ID
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
        $file = 'voucher_' . $params['id_order'] . '.jpg';
        $path = _PS_ROOT_DIR_ . '/img/vouchers/' . $file;
        $url = file_exists($path) ? __PS_BASE_URI__ . 'img/vouchers/' . $file : false;
        $this->context->smarty->assign(['voucher_img' => $url]);
        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }
}