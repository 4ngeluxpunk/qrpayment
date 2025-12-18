<?php
/**
 * Controlador Frontal para la página de validación (Alternativa al Modal en Checkout).
 * QR Payment 4.0.0 - Compatibilidad PrestaShop 9
 */

class QrPaymentValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // 1. Recuperar ID de la app seleccionada desde el formulario del checkout
        $id_qrpayment = Tools::getValue('qr_app_id');
        
        // 2. Cargar la configuración de la App QR
        if ($id_qrpayment) {
             $app = Db::getInstance()->getRow("SELECT * FROM " . _DB_PREFIX_ . "qrpayment WHERE id_qrpayment = " . (int)$id_qrpayment);
        } else {
             // Si no hay ID, volvemos al carrito para que seleccione
             Tools::redirect('index.php?controller=order&step=1');
        }

        if (!$app) {
             Tools::redirect('index.php?controller=order&step=1');
        }

        // 3. Asignar variables a Smarty
        $this->context->smarty->assign([
            'app' => $app,
            'total_amount' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH)),
            'qr_img_base_url' => $this->module->getPathUri() . 'img/',
            // El formulario del paso 2 enviará datos al controlador 'process'
            'action_url' => $this->context->link->getModuleLink($this->module->name, 'process', [], true),
        ]);

        // 4. Establecer el template para la vista
        $this->setTemplate('module:qrpayment/views/templates/front/validation.tpl');
    }
}