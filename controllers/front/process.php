<?php

class QrPaymentProcessModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (Tools::isSubmit('submitQrPayment')) {
            $cart = $this->context->cart;

            if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
                Tools::redirect('index.php?controller=order&step=1');
            }

            $customer = new Customer($cart->id_customer);
            if (!Validate::isLoadedObject($customer)) {
                Tools::redirect('index.php?controller=order&step=1');
            }

            $file = $_FILES['payment_voucher'];
            $allowedExtensions = array('jpeg', 'jpg', 'png', 'gif');

            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->errors[] = $this->module->l('Error al subir el archivo. Intente nuevamente.');
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
                $this->errors[] = $this->module->l('Formato de archivo no permitido. Solo se aceptan imágenes (JPG, PNG, GIF).');
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            if ($error = ImageManager::validateUpload($file, Tools::getMaxUploadSize())) {
                $this->errors[] = $error;
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            $currency = $this->context->currency;
            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            $id_status = (int)Configuration::get('QRPAYMENT_SUCCESS_STATUS');

            $this->module->validateOrder(
                (int)$cart->id,
                $id_status,
                $total,
                $this->module->displayName,
                null,
                array(),
                (int)$currency->id,
                false,
                $customer->secure_key
            );

            $id_order = (int)$this->module->currentOrder;

            if (isset($file) && $file['size'] > 0) {
                $target_dir = _PS_ROOT_DIR_ . '/img/vouchers/';
                $safe_file_name = 'voucher_' . $id_order . '.jpg';

                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                    file_put_contents($target_dir . 'index.php', '');
                }

                if (!move_uploaded_file($file['tmp_name'], $target_dir . $safe_file_name)) {
                    PrestaShopLogger::addLog('QrPayment: No se pudo mover el voucher para la orden ' . $id_order, 3);
                }
            }

            Tools::redirect('index.php?controller=history');
        }
    }
}