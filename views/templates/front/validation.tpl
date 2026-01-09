{extends file='page.tpl'}

{block name='content'}
<div class="qr-modal-wrapper">
    
    <div id="qr-step-1">
        <div class="qr-modal-header">
            <h3>{l s='Hacer un pago QR con' mod='qrpayment'} <span style="color:#2fb5d2">{$app.name}</span></h3>
        </div>
        <div class="qr-modal-body">
            <p class="text-muted">{l s='Copiar Número de teléfono' mod='qrpayment'}</p>
            <div class="qr-phone-display">{$app.phone}</div>

            {if $app.image_path}
                <img src="{$qr_img_base_url}{$app.image_path}" class="qr-main-img" alt="{$app.name} QR Code" />
            {/if}

            <p>{l s='Cantidad total a pagar:' mod='qrpayment'} <strong>{$app.name}</strong></p>
            <div class="qr-amount-display">{$total_amount}</div>

            <p style="font-size: 13px; color: #777; margin-bottom: 20px;">
                {l s='Escanee el código QR o agregue nuestro número. Una vez realizado el pago, por favor haga clic en Continuar.' mod='qrpayment'}
            </p>

            <button type="button" id="btn-qr-continue" class="btn-qr-action btn-continue">
                {l s='CONTINUAR' mod='qrpayment'}
            </button>
        </div>
    </div>

    <div id="qr-step-2" style="display:none;">
        <div class="qr-modal-header">
            <h3>{l s='Adjunte la captura de pantalla o voucher' mod='qrpayment'}</h3>
        </div>
        <div class="qr-modal-body">
            <form action="{$action_url}" method="post" enctype="multipart/form-data">
                
                <label for="file_upload" class="qr-upload-zone">
                    <i class="material-icons" style="font-size: 50px; color: #2fb5d2; margin-bottom: 10px;">cloud_upload</i>
                    <p><strong>{l s='Haga clic para buscar la imagen' mod='qrpayment'}</strong></p>
                    <p class="small">{l s='Formatos aceptados: JPG, PNG' mod='qrpayment'}</p>
                    <input type="file" id="file_upload" name="payment_voucher" required accept="image/jpeg,image/png" style="display:none" onchange="document.getElementById('file-name').innerText = this.files[0].name">
                    <div id="file-name" style="margin-top:10px; font-weight:bold; color:#333;">{l s='Ningún archivo seleccionado' mod='qrpayment'}</div>
                </label>

                <p style="font-size: 13px; color: #777; margin-bottom: 25px;">
                    {l s='El pedido se colocará en estado "En espera de validación" y será revisado por un administrador.' mod='qrpayment'}
                </p>

                <div style="display: flex; justify-content: space-between; gap: 10px;">
                    <button type="button" id="btn-qr-back" class="btn-qr-action btn-back">{l s='REGRESAR' mod='qrpayment'}</button>
                    <button type="submit" name="submitQrPayment" class="btn-qr-action btn-finish">{l s='FINALIZAR PEDIDO' mod='qrpayment'}</button>
                </div>
            </form>
        </div>
    </div>

</div>
{/block}