{# M-Pesa Configuration Template #}
<div class="row">
    <div class="col-md-12">
        <h3>M-Pesa Configuration</h3>
        <form method="post" action="">
            <div class="form-group">
                <label for="mpesa_consumer_key">Consumer Key</label>
                <input type="text" class="form-control" id="mpesa_consumer_key" name="mpesa_consumer_key" value="{$config['mpesa_consumer_key']}">
            </div>
            <div class="form-group">
                <label for="mpesa_consumer_secret">Consumer Secret</label>
                <input type="password" class="form-control" id="mpesa_consumer_secret" name="mpesa_consumer_secret" value="{$config['mpesa_consumer_secret']}">
            </div>
            <div class="form-group">
                <label for="mpesa_shortcode">Shortcode</label>
                <input type="text" class="form-control" id="mpesa_shortcode" name="mpesa_shortcode" value="{$config['mpesa_shortcode']}">
            </div>
            <div class="form-group">
                <label for="mpesa_passkey">Passkey</label>
                <input type="password" class="form-control" id="mpesa_passkey" name="mpesa_passkey" value="{$config['mpesa_passkey']}">
            </div>
            <button type="submit" class="btn btn-primary" name="_save" value="mpesa">Save</button>
        </form>
    </div>
</div>