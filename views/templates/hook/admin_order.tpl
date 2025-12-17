<div class="panel card">
    <div class="panel-heading card-header">
        <h3 class="card-title">
            <i class="icon-camera"></i> {l s='Comprobante de Pago QR' mod='qrpayment'}
        </h3>
    </div>
    
    <div class="panel-body card-body">
        {if isset($voucher_img) && $voucher_img}
            <div class="alert alert-info">
                <p>{l s='El cliente ha adjuntado el siguiente comprobante:' mod='qrpayment'}</p>
            </div>
            
            <div class="row">
                <div class="col-lg-6">
                    <a href="{$voucher_img|escape:'html':'UTF-8'}" target="_blank" title="{l s='Ver imagen completa' mod='qrpayment'}">
                        <img src="{$voucher_img|escape:'html':'UTF-8'}" class="img-thumbnail" style="max-width: 400px; border: 1px solid #ccc;" />
                    </a>
                </div>
            </div>
        {else}
            <div class="alert alert-warning">
                {l s='No se ha encontrado comprobante para este pedido.' mod='qrpayment'}
            </div>
        {/if}
    </div>
</div>