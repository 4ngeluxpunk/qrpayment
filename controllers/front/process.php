<?php
class QrPaymentProcessModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (Tools::isSubmit('submitQrPayment')) {
            $cart = $this->context->cart;
            
            // 1. Validar Cliente
            if (!$this->context->customer->isLogged() || $cart->id_customer != $this->context->customer->id) {
                 Tools::redirect('index.php?controller=authentication');
            }

            // 2. SEGURIDAD EXTREMA: Verificar archivo ANTES de crear la orden
            // Si intenta subir algo que no es imagen, se cancela todo.
            if (isset($_FILES['payment_voucher'])) {
                $file = $_FILES['payment_voucher'];
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $allowed = array('jpg', 'jpeg', 'png', 'gif');

                // Si la extensión no es válida, detenemos ejecución (evita inyecciones PHP)
                if (!in_array(strtolower($ext), $allowed)) {
                    $this->errors[] = $this->module->l('Formato de archivo no permitido. Solo imágenes.');
                    Tools::redirect('index.php?controller=order&step=3'); // Regresar al checkout
                    exit;
                }
            } else {
                // Si no hay archivo, tampoco procesamos (aunque el JS lo previene)
                Tools::redirect('index.php?controller=order&step=3');
                exit;
            }

            $customer = new Customer($cart->id_customer);
            if (!Validate::isLoadedObject($customer)) {
                Tools::redirect('index.php?controller=order&step=1');
                exit;
            }

            $currency = $this->context->currency;
            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            $id_status = (int)Configuration::get('QRPAYMENT_SUCCESS_STATUS');
            
            // 3. Validar la orden (Crear el pedido en PrestaShop)
            $this->module->validateOrder(
                (int)$cart->id,
                $id_status, 
                $total,
                $this->module->displayName,
                null,
                [],
                (int)$currency->id,
                false,
                $customer->secure_key
            );

            $id_order = (int)$this->module->currentOrder;
            
            // 4. Guardar Voucher
            if (!empty($_FILES['payment_voucher']['tmp_name'])) {
                $target_dir = _PS_ROOT_DIR_ . '/img/vouchers/'; 
                
                // Validación profunda de imagen (Header MIME)
                if (!ImageManager::validateUpload($file, 4000000)) {
                    $file_name = 'voucher_' . $id_order . '.jpg'; 
                    
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0755, true);
                        @file_put_contents($target_dir . 'index.php', '');
                    }

                    move_uploaded_file($file['tmp_name'], $target_dir . $file_name);
                }
            }

            // 5. Éxito
            Tools::redirect('index.php?controller=history');
            exit;
        }
    }
}