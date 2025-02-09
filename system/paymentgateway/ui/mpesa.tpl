{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" action="" role="form">
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">M-Pesa Configuration</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label" for="mpesa_consumer_key">Consumer Key</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="mpesa_consumer_key" name="mpesa_consumer_key" value="{$config['mpesa_consumer_key']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label" for="mpesa_consumer_secret">Consumer Secret</label>
                        <div class="col-md-6">
                            <input type="password" class="form-control" id="mpesa_consumer_secret" name="mpesa_consumer_secret" value="{$config['mpesa_consumer_secret']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label" for="mpesa_shortcode">Shortcode</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="mpesa_shortcode" name="mpesa_shortcode" value="{$config['mpesa_shortcode']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label" for="mpesa_passkey">Passkey</label>
                        <div class="col-md-6">
                            <input type="password" class="form-control" id="mpesa_passkey" name="mpesa_passkey" value="{$config['mpesa_passkey']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-offset-2 col-md-6">
                            <button type="submit" class="btn btn-primary waves-effect waves-light" name="_save" value="mpesa">{Lang::T('Save')}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

{include file="sections/footer.tpl"}