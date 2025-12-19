<?php
class QrPaymentValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $id_qrpayment = (int)Tools::getValue('qr_app_id');

        // Uso de cast (int) para evitar SQL Injection directo
        if ($id_qrpayment) {
             $app = Db::getInstance()->getRow("SELECT * FROM " . _DB_PREFIX_ . "qrpayment WHERE id_qrpayment = " . $id_qrpayment);
        } else {
             Tools::redirect('index.php?controller=order&step=1');
        }

        if (!$app) {
             Tools::redirect('index.php?controller=order&step=1');
        }

        $this->context->smarty->assign([
            'app' => $app,
            'total_amount' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH)),
            'qr_img_base_url' => $this->module->getPathUri() . 'img/',
            'action_url' => $this->context->link->getModuleLink($this->module->name, 'process', [], true),
        ]);

        $this->setTemplate('module:qrpayment/views/templates/front/validation.tpl');
    }
}
