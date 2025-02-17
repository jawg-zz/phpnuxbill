{include file="sections/header.tpl"}

<div class="row">
    <div class="col-sm-12 col-md-8 col-md-offset-2">
        <form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/mpesa">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-money"></i> M-Pesa Integration Settings</h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-3 control-label">Consumer Key</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="mpesa_consumer_key" name="mpesa_consumer_key"
                                value="{$_c['mpesa_consumer_key']}" required
                                placeholder="Enter your M-Pesa Consumer Key">
                            <small class="help-block">You can find this in your Daraja Developer Portal</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Consumer Secret</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                <input type="password" class="form-control" id="mpesa_consumer_secret" name="mpesa_consumer_secret"
                                    value="{$_c['mpesa_consumer_secret']}" required
                                    placeholder="Enter your M-Pesa Consumer Secret">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="togglePassword('mpesa_consumer_secret')">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </span>
                            </div>
                            <small class="help-block">Keep this secret and secure</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Shortcode</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="mpesa_shortcode" name="mpesa_shortcode"
                                value="{$_c['mpesa_shortcode']}" required
                                placeholder="Enter your Business Shortcode">
                            <small class="help-block">Your M-Pesa Business Shortcode (Paybill or Till Number)</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Passkey</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                <input type="password" class="form-control" id="mpesa_passkey" name="mpesa_passkey"
                                    value="{$_c['mpesa_passkey']}" required
                                    placeholder="Enter your M-Pesa Passkey">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="togglePassword('mpesa_passkey')">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </span>
                            </div>
                            <small class="help-block">The passkey provided by Safaricom</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Callback URL</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                <input type="text" readonly class="form-control" id="callback_url"
                                    value="{$_url}callback/mpesa">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="copyToClipboard('callback_url')">
                                        <i class="fa fa-copy"></i>
                                    </button>
                                </span>
                            </div>
                            <small class="help-block">Copy this URL to your Daraja Developer Portal</small>
                        </div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <div class="col-md-offset-3 col-md-9">
                            <button class="btn btn-success btn-lg" type="submit">
                                <i class="fa fa-save"></i> {$_L['Save']} M-Pesa Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}

function copyToClipboard(inputId) {
    var input = document.getElementById(inputId);
    input.select();
    document.execCommand('copy');
    toastr.success('Callback URL copied to clipboard');
}
</script>

{include file="sections/footer.tpl"}
