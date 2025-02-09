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
    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('button');
    const icon = button.querySelector('i');
    
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
    
    // Reset all error messages
    document.querySelectorAll('.help-block.text-danger').forEach(el => {
        el.textContent = '';
    });
    
    // Reset all input borders
    document.querySelectorAll('.form-control').forEach(input => {
        input.classList.remove('has-error');
    });

    let isValid = true;
    const inputs = this.querySelectorAll('input[required]');
    
    inputs.forEach(input => {
        const errorElement = document.getElementById(`${input.id}_error`);
        const formGroup = input.closest('.form-group');
        
        // Check if empty
        if (!input.value.trim()) {
            isValid = false;
            formGroup.classList.add('has-error');
            errorElement.textContent = 'This field is required';
        }
        
        // Check shortcode pattern
        if (input.id === 'mpesa_shortcode' && input.value.trim()) {
            if (!/^[0-9]+$/.test(input.value)) {
                isValid = false;
                formGroup.classList.add('has-error');
                errorElement.textContent = 'Shortcode must contain only numbers';
            }
        }
    });

    // If form is valid, submit it
    if (isValid) {
        this.submit();
    }
});

// Real-time validation
document.querySelectorAll('input[required]').forEach(input => {
    input.addEventListener('input', function() {
        const errorElement = document.getElementById(`${this.id}_error`);
        const formGroup = this.closest('.form-group');
        
        // Clear error state
        formGroup.classList.remove('has-error');
        errorElement.textContent = '';
        
        // Validate shortcode format
        if (this.id === 'mpesa_shortcode' && this.value.trim()) {
            if (!/^[0-9]+$/.test(this.value)) {
                formGroup.classList.add('has-error');
                errorElement.textContent = 'Shortcode must contain only numbers';
            }
        }
    });
});
</script>

{include file="sections/footer.tpl"}