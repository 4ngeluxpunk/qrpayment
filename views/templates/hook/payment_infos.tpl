<div class="qr-payment-container js-qr-payment-container">
    <p class="qr-desc">{$qr_desc}</p>
    <p><strong>{l s='SELECCIONE LA IMAGEN DE LA APLICACIÓN:' mod='qrpayment'}</strong></p>

    <div class="qr-app-list">
        {foreach from=$qr_apps item=app name=apps}
            <div class="qr-app-item {if $smarty.foreach.apps.first}selected{/if}"
                 data-id="{$app.id_app}"
                 data-name="{$app.name}"
                 data-phone="{$app.phone}"
                 data-max-amount="{$app.formatted_max_amount}"
                 data-image="{$qr_img_base_url}{$app.image_path}"
                 onclick="selectQrApp(this)">

                <img src="{$qr_img_base_url}{$app.icon_path}" alt="{$app.name}" />
                <div class="qr-check"><i class="material-icons">check_circle</i></div>
            </div>
        {/foreach}
    </div>

    <input type="hidden" id="selected_qr_app_id" value="{if isset($qr_apps[0])}{$qr_apps[0].id_app}{/if}">
</div>

<div id="qr-modal-overlay" class="qr-modal-overlay" style="display:none;">
    <div class="qr-modal">
        <div class="qr-modal-header">
            <h3 id="qr-modal-title"></h3>
            <button type="button" class="close-modal-btn" onclick="closeQrModal()"><i class="material-icons">close</i></button>
        </div>

        <div class="qr-modal-body">
            {* ------------------------------------------- *}
            {* Paso 1: Información y Código QR *}
            {* ------------------------------------------- *}
            <div id="qr-step-info">
                <p class="qr-modern-label">{l s='Datos de Transferencia' mod='qrpayment'}</p>

                <div id="qr-modal-phone" class="qr-phone-box" onclick="copyPhoneNumber()"></div>
                <div id="qr-copy-feedback" class="qr-copy-feedback">{l s='¡Copiado al portapapeles!' mod='qrpayment'}</div>

                <div class="qr-big-img-container">
                    <img id="qr-modal-image" src="" alt="QR Image" class="qr-big-img" />
                </div>

                <div class="qr-amount-wrapper">
                    <p class="qr-amount-label">
                        {l s='Monto total de tu pedido' mod='qrpayment'}
                        (<span id="qr-modal-app-name-label"></span>)
                    </p>
                    <div class="qr-amount-display">
                        {Context::getContext()->currentLocale->formatPrice(Context::getContext()->cart->getOrderTotal(true, Cart::BOTH), Context::getContext()->currency->iso_code)}
                    </div>
                </div>

                {* Monto Máximo *}
                <p id="qr-modal-max-amount-container" style="display:none;" class="qr-max-amount-container">
                    <i class="material-icons">warning</i>
                    {l s='Monto Máximo de Transferencia:' mod='qrpayment'} <span id="qr-modal-max-amount-value"></span>
                </p>

                <button type="button" class="btn btn-primary btn-block btn-continue-custom" onclick="goToStep2()">
                    {l s='CONTINUAR' mod='qrpayment'}
                </button>
            </div>

            {* ------------------------------------------- *}
            {* Paso 2: Subir Voucher *}
            {* ------------------------------------------- *}
            <div id="qr-step-upload" style="display:none;">
                <h4 class="qr-upload-header">{l s='Adjuntar Comprobante' mod='qrpayment'}</h4>
                <p class="qr-upload-subheader">{l s='Sube una foto o captura de la transacción exitosa.' mod='qrpayment'}</p>

                <form action="{$qr_process_url}" method="post" enctype="multipart/form-data" id="qr-voucher-form">
                    <input type="hidden" name="qr_app_id" id="qr-modal-app-id-input">
                    <input type="hidden" name="submitQrPayment" value="1">

                    <label class="qr-upload-area">
                        <i class="material-icons">cloud_upload</i>
                        <span>{l s='CLIC AQUÍ para seleccionar el archivo (JPG, PNG)' mod='qrpayment'}</span>
                        <input type="file" name="payment_voucher" required accept=".jpg, .jpeg, .png, .gif, image/*" onchange="previewFile(this)">
                    </label>
                    
                    <div id="file-name-preview" class="small text-center"></div>

                    <div id="qr-error-msg" class="alert alert-danger" style="display:none; margin-top:10px; font-weight:bold;"></div>

                    <div class="qr-modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="goToStep1()">{l s='ATRÁS' mod='qrpayment'}</button>
                        
                        <button type="button" class="btn btn-success" onclick="validateAndSubmitQr()">
                            {l s='FINALIZAR PEDIDO' mod='qrpayment'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>