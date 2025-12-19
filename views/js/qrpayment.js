/**
 * Lógica QR Payment - Validación Cliente (Efecto Ajax)
 */

document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById('qr-modal-overlay');
    if (modal) {
        document.body.appendChild(modal);
    }

    // Listener para abrir el modal desde el checkout
    var orderButton = document.getElementById('payment-confirmation') ? document.getElementById('payment-confirmation').querySelector('button') : null;

    if(orderButton) {
        orderButton.addEventListener('click', function(e) {
            var isMyModule = false;
            var radios = document.getElementsByName('payment-option');
            for (var i = 0; i < radios.length; i++) {
                if (radios[i].checked) {
                    var container = document.getElementById(radios[i].id + '-additional-information');
                    if (container && container.querySelector('.js-qr-payment-container')) {
                        isMyModule = true;
                    }
                    break;
                }
            }

            if (isMyModule) {
                e.preventDefault();
                e.stopPropagation();
                openQrModal();
                return false;
            }
        }, true);
    }

    // --- VALIDACIÓN ESTRICTA DEL FORMULARIO (Efecto AJAX) ---
    var voucherForm = document.getElementById('qr-voucher-form');
    if (voucherForm) {
        voucherForm.addEventListener('submit', function(e) {

            var fileInput = document.getElementById('qr_payment_voucher');
            var errorBox = document.getElementById('qr-file-error');
            var fileNamePreview = document.getElementById('file-name-preview');

            // 1. Limpiar errores previos
            if(errorBox) {
                errorBox.style.display = 'none';
                errorBox.innerText = '';
            }

            // 2. Validar Existencia del Archivo
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                e.stopImmediatePropagation(); // Detiene cualquier otro script

                if(errorBox) {
                    errorBox.innerText = 'Falta Captura de Pago';
                    errorBox.style.display = 'block';
                } else {
                    alert('Falta Captura de Pago'); // Fallback por si falla el CSS/HTML
                }
                return false;
            }

            // 3. Validar Extensión de Imagen
            var file = fileInput.files[0];
            var allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif)$/i;

            if (!allowedExtensions.exec(file.name)) {
                e.preventDefault();
                e.stopImmediatePropagation(); // Detiene envío al servidor

                // Limpiar input visualmente
                fileInput.value = '';
                if(fileNamePreview) fileNamePreview.innerText = '';

                if(errorBox) {
                    // Mensaje solicitado exacto
                    errorBox.innerText = 'Formato de archivo no permitido. Solo se aceptan imágenes (JPG, PNG, GIF).';
                    errorBox.style.display = 'block';
                } else {
                    alert('Formato de archivo no permitido. Solo se aceptan imágenes (JPG, PNG, GIF).');
                }
                return false;
            }

            // Si todo está bien, dejamos que el formulario se envíe normalmente al servidor
        });
    }
});


function selectQrApp(element) {
    var items = document.querySelectorAll('.qr-app-item');
    items.forEach(function(item) { item.classList.remove('selected'); });
    element.classList.add('selected');
    document.getElementById('selected_qr_app_id').value = element.getAttribute('data-id');
}

function openQrModal() {
    var selectedId = document.getElementById('selected_qr_app_id').value;
    var selectedItem = document.querySelector('.qr-app-item[data-id="' + selectedId + '"]');
    if (!selectedItem) {
        selectedItem = document.querySelector('.qr-app-item');
        if(selectedItem) selectQrApp(selectedItem);
    }
    if(!selectedItem) return;

    var appName = selectedItem.getAttribute('data-name');
    var maxAmount = selectedItem.getAttribute('data-max-amount');

    // Llenar datos
    document.getElementById('qr-modal-title').innerText = appName;
    document.getElementById('qr-modal-phone').innerText = selectedItem.getAttribute('data-phone');
    document.getElementById('qr-modal-image').src = selectedItem.getAttribute('data-image');
    document.getElementById('qr-modal-app-id-input').value = selectedItem.getAttribute('data-id');
    document.getElementById('qr-modal-app-name-label').innerText = appName;

    // Monto Máximo
    var maxAmountContainer = document.getElementById('qr-modal-max-amount-container');
    var maxAmountValue = document.getElementById('qr-modal-max-amount-value');

    if (maxAmount && maxAmount !== '') {
        maxAmountValue.innerText = maxAmount;
        maxAmountContainer.style.display = 'block';
    } else {
        maxAmountContainer.style.display = 'none';
    }

    // Resetear formulario (UI)
    var errorBox = document.getElementById('qr-file-error');
    if(errorBox) errorBox.style.display = 'none';

    var fileInput = document.getElementById('qr_payment_voucher');
    if(fileInput) fileInput.value = '';

    var fileNamePreview = document.getElementById('file-name-preview');
    if(fileNamePreview) fileNamePreview.innerText = '';

    goToStep1();
    document.getElementById('qr-modal-overlay').style.display = 'flex';
}

function closeQrModal() {
    document.getElementById('qr-modal-overlay').style.display = 'none';
}

function goToStep1() {
    document.getElementById('qr-step-info').style.display = 'block';
    document.getElementById('qr-step-upload').style.display = 'none';
}

function goToStep2() {
    document.getElementById('qr-step-info').style.display = 'none';
    document.getElementById('qr-step-upload').style.display = 'block';
}

function previewFile(input) {
    var fileNamePreview = document.getElementById('file-name-preview');
    var errorBox = document.getElementById('qr-file-error');

    // Al seleccionar archivo, ocultamos el error "Falta captura"
    if(errorBox) errorBox.style.display = 'none';

    if(input.files && input.files[0]) {
        fileNamePreview.innerText = input.files[0].name;
    } else {
        fileNamePreview.innerText = '';
    }
}

function showCopyFeedback(feedbackElement) {
    feedbackElement.style.display = 'block';
    setTimeout(function() {
        feedbackElement.style.display = 'none';
    }, 2000);
}

function copyFallback(textToCopy, feedbackElement) {
    var tempInput = document.createElement("textarea");
    tempInput.value = textToCopy;
    tempInput.style.position = 'fixed';
    tempInput.style.opacity = '0';
    document.body.appendChild(tempInput);
    tempInput.select();
    try {
        var successful = document.execCommand('copy');
        if (successful) showCopyFeedback(feedbackElement);
    } catch (err) {
        console.error('Error al copiar', err);
    }
    document.body.removeChild(tempInput);
}

function copyPhoneNumber() {
    var textToCopy = document.getElementById('qr-modal-phone').innerText.trim();
    var feedbackElement = document.getElementById('qr-copy-feedback');

    if (navigator.clipboard) {
        navigator.clipboard.writeText(textToCopy).then(function() {
            showCopyFeedback(feedbackElement);
        }).catch(function(err) {
            copyFallback(textToCopy, feedbackElement);
        });
    } else {
        copyFallback(textToCopy, feedbackElement);
    }
}
