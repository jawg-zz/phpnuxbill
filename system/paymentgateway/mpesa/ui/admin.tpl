{include file="sections/header.tpl"}

<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">M-Pesa Settings</div>
            <div class="panel-body">
                <form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/mpesa/admin/post">
                    <div class="form-group">
                        <label class="col-md-2 control-label">Consumer Key</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="mpesa_consumer_key" name="mpesa_consumer_key" value="{$mpesa_consumer_key}">
                            <small class="help-block">Your M-Pesa API Consumer Key</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Consumer Secret</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="mpesa_consumer_secret" name="mpesa_consumer_secret" value="{$mpesa_consumer_secret}">
                            <small class="help-block">Your M-Pesa API Consumer Secret</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Business Shortcode</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="mpesa_shortcode" name="mpesa_shortcode" value="{$mpesa_shortcode}">
                            <small class="help-block">Your M-Pesa Paybill/Till Number</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Passkey</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="mpesa_passkey" name="mpesa_passkey" value="{$mpesa_passkey}">
                            <small class="help-block">Your M-Pesa API Passkey</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary" type="submit">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="panel-footer">
                <p class="help-block">
                    <strong>Callback URL:</strong> {$_url}plugin/mpesa/callback<br>
                    Configure this URL in your M-Pesa developer portal
                </p>
            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}