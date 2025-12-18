/**
 * Lógica QR Payment 3.3.1 - AÑADIDA FUNCIÓN DE COPIADO
 */

document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById('qr-modal-overlay');
    if (modal) {
        document.body.appendChild(modal);
    }

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

    // Asignar datos al MODAL 2
    document.getElementById('qr-modal-title').innerText = appName;
    document.getElementById('qr-modal-phone').innerText = selectedItem.getAttribute('data-phone');
    document.getElementById('qr-modal-image').src = selectedItem.getAttribute('data-image');
    document.getElementById('qr-modal-app-id-input').value = selectedItem.getAttribute('data-id');

    // Etiqueta de cantidad
    document.getElementById('qr-modal-app-name-label').innerText = appName;

    // Monto Máximo (Mostrar solo si existe)
    var maxAmountContainer = document.getElementById('qr-modal-max-amount-container');
    var maxAmountValue = document.getElementById('qr-modal-max-amount-value');

    if (maxAmount && maxAmount !== '') {
        maxAmountValue.innerText = maxAmount;
        maxAmountContainer.style.display = 'block';
    } else {
        maxAmountContainer.style.display = 'none';
    }

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
    if(input.files && input.files[0]) {
        fileNamePreview.innerText = input.files[0].name;
    } else {
        fileNamePreview.innerText = '';
    }
}

function showCopyFeedback(feedbackElement) {
    // Mostrar el feedback y ocultarlo después de 2 segundos
    feedbackElement.style.display = 'block';
    setTimeout(function() {
        feedbackElement.style.display = 'none';
    }, 2000);
}

function copyFallback(textToCopy, feedbackElement) {
    // Crear un área de texto temporal
    var tempInput = document.createElement("textarea");
    tempInput.value = textToCopy;
    tempInput.style.position = 'fixed';
    tempInput.style.opacity = '0';
    document.body.appendChild(tempInput);

    // Seleccionar el texto y copiar
    tempInput.select();
    try {
        var successful = document.execCommand('copy');
        if (successful) {
            showCopyFeedback(feedbackElement);
        } else {
            console.error('Error al copiar (Fallback)');
        }
    } catch (err) {
        console.error('No se pudo copiar el texto: ', err);
    }

    // Eliminar el área de texto temporal
    document.body.removeChild(tempInput);
}

function copyPhoneNumber() {
    var textToCopy = document.getElementById('qr-modal-phone').innerText.trim();
    var feedbackElement = document.getElementById('qr-copy-feedback');

    // 1. Usar la API Clipboard moderna (promesa)
    if (navigator.clipboard) {
        navigator.clipboard.writeText(textToCopy).then(function() {
            showCopyFeedback(feedbackElement);
        }).catch(function(err) {
            console.error('Error al copiar (API): ', err);
            // Fallback si la API falla o no hay permisos
            copyFallback(textToCopy, feedbackElement);
        });
    } else {
        // 2. Fallback clásico (document.execCommand)
        copyFallback(textToCopy, feedbackElement);
    }
}
