<!-- sections/header.tpl is assumed to be the header template -->
{include file="sections/header.tpl"}

<!-- M-Pesa STK Push Settings Form -->
<form class="form-horizontal" method="post" autocomplete="off" role="form" action="{$_url}paymentgateway/mpesa">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-12 col-md-12">
        <div class="card">
          <div class="card-header">
            <h4>M-Pesa STK Push Settings</h4>
          </div>
          <div class="card-body">
            <!-- Consumer Key Field -->
            <div class="form-group row">
              <label for="mpesa_consumer_key" class="col-md-2 col-form-label">Consumer Key <span class="text-danger">*</span></label>
              <div class="col-md-6">
                <input type="text" class="form-control" id="mpesa_consumer_key" name="mpesa_consumer_key" required
                       value="{$_c['mpesa_consumer_key']}" placeholder="Enter Consumer Key" onkeyup="checkFields()">
              </div>
            </div>

            <!-- Consumer Secret Field -->
            <div class="form-group row">
              <label for="mpesa_consumer_secret" class="col-md-2 col-form-label">Consumer Secret <span class="text-danger">*</span></label>
              <div class="col-md-6">
                <input type="password" class="form-control" id="mpesa_consumer_secret" name="mpesa_consumer_secret" required
                       value="{$_c['mpesa_consumer_secret']}" placeholder="Enter Consumer Secret" onkeyup="checkFields()">
              </div>
            </div>

            <!-- Passkey Field -->
            <div class="form-group row">
              <label for="mpesa_passkey" class="col-md-2 col-form-label">Passkey <span class="text-danger">*</span></label>
              <div class="col-md-6">
                <input type="password" class="form-control" id="mpesa_passkey" name="mpesa_passkey" required
                       value="{$_c['mpesa_passkey']}" placeholder="Enter Passkey" onkeyup="checkFields()">
              </div>
            </div>

            <!-- Shortcode Field -->
            <div class="form-group row">
              <label for="mpesa_shortcode" class="col-md-2 col-form-label">Shortcode <span class="text-danger">*</span></label>
              <div class="col-md-6">
                <input type="text" class="form-control" id="mpesa_shortcode" name="mpesa_shortcode" required
                       value="{$_c['mpesa_shortcode']}" placeholder="Enter Shortcode" onkeyup="checkFields()">
              </div>
            </div>

            <!-- Callback URL Field -->
            <div class="form-group row">
              <label for="callback_url" class="col-md-2 col-form-label">Callback URL <span class="text-danger">*</span></label>
              <div class="col-md-6">
                <input type="text" readonly class="form-control" id="callback_url" value="{$_url}callback/mpesa" required
                       onclick="this.select()">
              </div>
            </div>

            <!-- Save Button -->
            <div class="form-group row">
              <label for="saveButton" class="col-md-2 col-form-label"></label>
              <div class="col-md-6">
                <button class="btn btn-primary btn-lg form-control" type="submit" id="saveButton" disabled style="padding: 0.5rem 2.5rem;">Save</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Additional Configuration Alert -->
        <div class="alert alert-info">
          <h5>Additional Configuration</h5>
          <pre>
/ip hotspot walled-garden
add dst-host=safaricom.co.ke
add dst-host=*.safaricom.co.ke
add dst-host=*.safaricom.com
</pre>
        </div>

        <!-- Telegram Bot Notification Text -->
        <small id="emailHelp" class="form-text text-muted">Set Telegram Bot to get any error and notification</small>
      </div>
    </div>
  </div>
</form>

<!-- sections/footer.tpl is assumed to be the footer template -->
{include file="sections/footer.tpl"}

<script>
function checkFields() {
  var consumerKey = document.getElementById("mpesa_consumer_key").value;
  var consumerSecret = document.getElementById("mpesa_consumer_secret").value;
  var passkey = document.getElementById("mpesa_passkey").value;
  var shortcode = document.getElementById("mpesa_shortcode").value;

  if (consumerKey && consumerSecret && passkey && shortcode) {
    document.getElementById("saveButton").disabled = false;
  } else {
    document.getElementById("saveButton").disabled = true;
  }
}
</script>
