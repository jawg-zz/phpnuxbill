{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" action="" role="form" id="mpesaForm">
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">M-Pesa Configuration</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label">Consumer Key <span class="text-danger">*</span></label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="mpesa_consumer_key" name="mpesa_consumer_key" value="{$config['mpesa_consumer_key']}" required>
                            <span class="help-block text-danger" id="mpesa_consumer_key_error"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Consumer Secret <span class="text-danger">*</span></label>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="password" class="form-control" id="mpesa_consumer_secret" name="mpesa_consumer_secret" value="{$config['mpesa_consumer_secret']}" required>
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="togglePassword('mpesa_consumer_secret')">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </span>
                            </div>
                            <span class="help-block text-danger" id="mpesa_consumer_secret_error"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Shortcode <span class="text-danger">*</span></label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="mpesa_shortcode" name="mpesa_shortcode" value="{$config['mpesa_shortcode']}" required pattern="[0-9]+">
                            <span class="help-block text-danger" id="mpesa_shortcode_error"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Passkey <span class="text-danger">*</span></label>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="password" class="form-control" id="mpesa_passkey" name="mpesa_passkey" value="{$config['mpesa_passkey']}" required>
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="togglePassword('mpesa_passkey')">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </span>
                            </div>
                            <span class="help-block text-danger" id="mpesa_passkey_error"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light" type="submit" name="_save" value="mpesa">{Lang::T('Save Changes')}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    var button = input.parentElement.querySelector('button');
    var icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

document.getElementById('mpesaForm').addEventListener('submit', function(event) {
    event.preventDefault();
    
    var errorMessages = document.querySelectorAll('.help-block.text-danger');
    for (var i = 0; i < errorMessages.length; i++) {
        errorMessages[i].textContent = '';
    }
    
    var inputs = document.querySelectorAll('.form-control');
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].closest('.form-group').classList.remove('has-error');
    }

    var isValid = true;
    var requiredInputs = this.querySelectorAll('input[required]');
    
    for (var i = 0; i < requiredInputs.length; i++) {
        var input = requiredInputs[i];
        var errorElement = document.getElementById(input.id + '_error');
        var formGroup = input.closest('.form-group');
        
        if (!input.value.trim()) {
            isValid = false;
            formGroup.classList.add('has-error');
            errorElement.textContent = 'This field is required';
        }
        
        if (input.id === 'mpesa_shortcode' && input.value.trim()) {
            if (!/^[0-9]+$/.test(input.value)) {
                isValid = false;
                formGroup.classList.add('has-error');
                errorElement.textContent = 'Shortcode must contain only numbers';
            }
        }
    }

    if (isValid) {
        this.submit();
    }
});

var requiredInputs = document.querySelectorAll('input[required]');
for (var i = 0; i < requiredInputs.length; i++) {
    requiredInputs[i].addEventListener('input', function() {
        var errorElement = document.getElementById(this.id + '_error');
        var formGroup = this.closest('.form-group');
        
        formGroup.classList.remove('has-error');
        errorElement.textContent = '';
        
        if (this.id === 'mpesa_shortcode' && this.value.trim()) {
            if (!/^[0-9]+$/.test(this.value)) {
                formGroup.classList.add('has-error');
                errorElement.textContent = 'Shortcode must contain only numbers';
            }
        }
    });
}
</script>

{include file="sections/footer.tpl"}