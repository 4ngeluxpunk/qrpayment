/**
 * Lógica QR Payment - Validación de Seguridad y UX Mejorada
 */

document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById('qr-modal-overlay');
    if (modal) {
        document.body.appendChild(modal);
    }

    // Interceptamos el botón de pago nativo
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

    document.getElementById('qr-modal-title').innerText = appName;
    document.getElementById('qr-modal-phone').innerText = selectedItem.getAttribute('data-phone');
    document.getElementById('qr-modal-image').src = selectedItem.getAttribute('data-image');
    document.getElementById('qr-modal-app-id-input').value = selectedItem.getAttribute('data-id');
    document.getElementById('qr-modal-app-name-label').innerText = appName;

    var maxAmountContainer = document.getElementById('qr-modal-max-amount-container');
    var maxAmountValue = document.getElementById('qr-modal-max-amount-value');

    if (maxAmount && maxAmount !== '') {
        maxAmountValue.innerText = maxAmount;
        maxAmountContainer.style.display = 'block';
    } else {
        maxAmountContainer.style.display = 'none';
    }

    // Resetear errores anteriores
    document.getElementById('qr-error-msg').style.display = 'none';
    
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

// VALIDACIÓN ESTRICTA DE IMAGEN AL SELECCIONAR
function previewFile(input) {
    var fileNamePreview = document.getElementById('file-name-preview');
    var errorMsg = document.getElementById('qr-error-msg');
    
    // Lista blanca de extensiones permitidas
    var allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif)$/i;

    if(input.files && input.files[0]) {
        var file = input.files[0];
        
        // Validar extensión
        if (!allowedExtensions.exec(file.name)) {
            alert('Error de seguridad: Solo se permiten imágenes (JPG, PNG, GIF).');
            input.value = ''; // Borra el archivo seleccionado
            fileNamePreview.innerText = '';
            return false;
        }

        fileNamePreview.innerText = file.name;
        // Ocultar mensaje de error si existía
        errorMsg.style.display = 'none';
    } else {
        fileNamePreview.innerText = '';
    }
}

// VALIDACIÓN AL DAR CLIC EN FINALIZAR PEDIDO
function validateAndSubmitQr() {
    var fileInput = document.querySelector('input[name="payment_voucher"]');
    var errorMsg = document.getElementById('qr-error-msg');
    
    // 1. Validar que exista archivo
    if (!fileInput.files || fileInput.files.length === 0) {
        errorMsg.innerText = "Falta subir la captura del pago";
        errorMsg.style.display = 'block';
        return; 
    }

    // 2. Validar extensión nuevamente por seguridad
    var allowedExtensions = /(\.jpg|\.jpeg|\.png|\.gif)$/i;
    if (!allowedExtensions.exec(fileInput.files[0].name)) {
        errorMsg.innerText = "Archivo no permitido. Solo imágenes.";
        errorMsg.style.display = 'block';
        fileInput.value = ''; // Limpiar input
        return;
    }

    // 3. Si todo está bien, enviar formulario
    document.getElementById('qr-voucher-form').submit();
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
        console.error('No se pudo copiar el texto: ', err);
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