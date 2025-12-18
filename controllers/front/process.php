<?php
class QrPaymentProcessModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (Tools::isSubmit('submitQrPayment')) {
            $cart = $this->context->cart;
            $customer = new Customer($cart->id_customer);
            
            if (!Validate::isLoadedObject($customer)) Tools::redirect('index.php?controller=order&step=1');

            $currency = $this->context->currency;
            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            
            // Estado de orden personalizado: "En espera de validación QR"
            $id_status = Configuration::get('QRPAYMENT_SUCCESS_STATUS');
            
            // 1. Validar la orden
            $this->module->validateOrder(
                $cart->id,
                $id_status, 
                $total,
                $this->module->displayName,
                null,
                [],
                (int)$currency->id,
                false,
                $customer->secure_key
            );

            $id_order = $this->module->currentOrder;
            
            // 2. Guardar Voucher en /img/vouchers/
            if (isset($_FILES['payment_voucher']) && $_FILES['payment_voucher']['size'] > 0) {
                $target_dir = _PS_ROOT_DIR_ . '/img/vouchers/'; 
                $file_name = 'voucher_' . $id_order . '.jpg'; 
                
                // Mover el archivo subido
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

                // Se puede añadir validación de tipo de archivo aquí si es necesario
                move_uploaded_file($_FILES['payment_voucher']['tmp_name'], $target_dir . $file_name);
            }

            // 3. Redirigir al historial de pedidos
            Tools::redirect('index.php?controller=history');
        }
    }
}