<?php
/**
 * Controlador Frontal de Procesamiento Seguro
 * Corrige vulnerabilidades de File Upload, RCE y errores de redirección.
 */

class QrPaymentProcessModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (Tools::isSubmit('submitQrPayment')) {
            $cart = $this->context->cart;

            // Validaciones de contexto de seguridad
            if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
                Tools::redirect('index.php?controller=order&step=1');
            }

            $customer = new Customer($cart->id_customer);
            if (!Validate::isLoadedObject($customer)) {
                Tools::redirect('index.php?controller=order&step=1');
            }

            // --- SEGURIDAD: Validación del archivo ANTES de procesar la orden ---
            $file = $_FILES['payment_voucher'];
            $allowedExtensions = array('jpeg', 'jpg', 'png', 'gif');

            // Obtener extensión de forma segura
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

            // 1. Verificar si hubo error en la subida (ej. tamaño excedido en php.ini)
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->errors[] = $this->module->l('Error al subir el archivo. Intente nuevamente.');
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            // 2. Verificar extensión explícita (Bloqueo de PHP, HTML, EXE, etc.)
            if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
                $this->errors[] = $this->module->l('Formato de archivo no permitido. Solo se aceptan imágenes (JPG, PNG, GIF).');
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            // 3. Validación profunda de PrestaShop (Detecta scripts disfrazados de imágenes)
            if ($error = ImageManager::validateUpload($file, Tools::getMaxUploadSize())) {
                $this->errors[] = $error;
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            }

            // --- Procesamiento de la Orden ---
            $currency = $this->context->currency;
            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            $id_status = (int)Configuration::get('QRPAYMENT_SUCCESS_STATUS');

            // Validar la orden en PrestaShop
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

            // --- Guardado Seguro del Archivo ---
            // Renombramos forzosamente a .jpg y usamos id_order para evitar colisiones y ejecución
            if (isset($file) && $file['size'] > 0) {
                $target_dir = _PS_ROOT_DIR_ . '/img/vouchers/';
                $safe_file_name = 'voucher_' . $id_order . '.jpg';

                // Crear directorio si no existe y asegurarlo
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0755, true);
                    // Seguridad: crear index.php vacío para evitar listado
                    file_put_contents($target_dir . 'index.php', '');
                }

                // Mover el archivo validado
                if (!move_uploaded_file($file['tmp_name'], $target_dir . $safe_file_name)) {
                    PrestaShopLogger::addLog('QrPayment: No se pudo mover el voucher para la orden ' . $id_order, 3);
                }
            }

            // Redirigir a la página de confirmación/historial
            Tools::redirect('index.php?controller=history');
        }
    }
}
