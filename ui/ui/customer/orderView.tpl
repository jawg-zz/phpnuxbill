{include file="customer/header.tpl"}
<!-- user-orderView -->
<div class="row">
    <div class="col-md-3"></div>
    <div class="col-md-6">
        <div class="panel mb20 {if $trx['status']==1}panel-warning{elseif $trx['status']==2}panel-success{elseif $trx['status']==3}panel-danger{elseif $trx['status']==4}panel-danger{else}panel-primary{/if} panel-hovered">
            <div class="panel-footer">
                <strong>{Lang::T('Transaction')} #{$trx['id']}</strong>
                <span class="pull-right">
                    {if $trx['status']==1}
                        <span class="label label-warning">{Lang::T('UNPAID')}</span>
                    {elseif $trx['status']==2}
                        <span class="label label-success">{Lang::T('PAID')}</span>
                    {elseif $trx['status']==3}
                        <span class="label label-danger">{Lang::T('FAILED')}</span>
                    {elseif $trx['status']==4}
                        <span class="label label-danger">{Lang::T('CANCELED')}</span>
                    {else}
                        <span class="label label-default">{Lang::T('UNKNOWN')}</span>
                    {/if}
                </span>
            </div>

            <div class="panel-body">
                {if !in_array($trx['routers'],['balance','radius'])}
                    <div class="panel panel-primary panel-hovered">
                        <div class="panel-heading">{$router['name']}</div>
                        <div class="panel-body">
                            {$router['description']}
                        </div>
                    </div>
                {/if}

                <table class="table table-striped table-bordered">
                    <tbody>
                        <tr>
                            <td>{Lang::T('Plan Name')}</td>
                            <td>{$plan['name_plan']}</td>
                        </tr>
                        <tr>
                            <td>{Lang::T('Plan Price')}</td>
                            <td>{Lang::moneyFormat($trx['price'])}</td>
                        </tr>
                        <tr>
                            <td>{Lang::T('Created Date')}</td>
                            <td>{date($_c['date_format'], strtotime($trx['created_date']))} {date('H:i', strtotime($trx['created_date']))}</td>
                        </tr>
                        <tr>
                            <td>{Lang::T('Expires Date')}</td>
                            <td>{date($_c['date_format'], strtotime($trx['expired_date']))} {date('H:i', strtotime($trx['expired_date']))}</td>
                        </tr>
                        {if $trx['status']==2}
                            <tr>
                                <td>{Lang::T('Paid Date')}</td>
                                <td>{date($_c['date_format'], strtotime($trx['paid_date']))} {date('H:i', strtotime($trx['paid_date']))}</td>
                            </tr>
                            {if $trx['gateway'] eq 'mpesa'}
                                <tr>
                                    <td>{Lang::T('M-Pesa Receipt')}</td>
                                    <td>{$trx['gateway_trx_id']}</td>
                                </tr>
                            {/if}
                        {/if}
                        {if $trx['gateway'] eq 'mpesa' && isset($trx['gateway_trx_id']) && $trx['status']==1}
                            <tr>
                                <td>{Lang::T('M-Pesa Reference')}</td>
                                <td>{$trx['gateway_trx_id']}</td>
                            </tr>
                        {/if}
                    </tbody>
                </table>

                {if $trx['status']==1}
                    <div class="payment-section">
                        {if $trx['gateway'] eq 'mpesa'}
                            <div class="mpesa-payment-section">
                                {if !isset($trx['pg_request'])}
                                    <div class="alert alert-info">
                                        <strong>{Lang::T('Payment Instructions')}:</strong>
                                        <ol>
                                            <li>{Lang::T('Click the Pay Now button below')}</li>
                                            <li>{Lang::T('You will receive an STK Push on your phone')}</li>
                                            <li>{Lang::T('Enter your M-Pesa PIN to complete payment')}</li>
                                            <li>{Lang::T('Wait for confirmation message')}</li>
                                        </ol>
                                    </div>
                                    <form method="POST" action="{$_url}order/buy/{$plan['id']}/{$router['id']}">
                                        <input type="hidden" name="gateway" value="mpesa">
                                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                                            {Lang::T('Pay Now')} - {Lang::moneyFormat($trx['price'])}
                                        </button>
                                    </form>
                                {else}
                                    <div class="mpesa-payment-status mpesa-status-checking card">
                                        <div class="status-header card-header">
                                            <h4 class="status-title">{Lang::T('M-PESA Payment')}</h4>
                                            <span class="status-badge" id="status-badge">
                                                <span class="badge badge-primary">{Lang::T('Processing')}</span>
                                            </span>
                                        </div>
                                        <div class="status-body card-body">
                                            <div class="progress-tracker">
                                                <ul class="status-steps">
                                                    <li class="status-step completed" id="step-initiated">
                                                        <div class="status-step-icon"><i class="fa fa-check"></i></div>
                                                        <div class="status-step-content">
                                                            <div class="status-step-text">{Lang::T('Payment Initiated')}</div>
                                                            <div class="status-step-subtext">{Lang::T('Request sent to your phone')}</div>
                                                        </div>
                                                    </li>
                                                    <li class="status-step active" id="step-approval">
                                                        <div class="status-step-icon"><i class="fa fa-mobile"></i></div>
                                                        <div class="status-step-content">
                                                            <div class="status-step-text">{Lang::T('Waiting for Approval')}</div>
                                                            <div class="status-step-subtext">{Lang::T('Please enter your M-PESA PIN')}</div>
                                                        </div>
                                                    </li>
                                                    <li class="status-step" id="step-processing">
                                                        <div class="status-step-icon"><i class="fa fa-exchange"></i></div>
                                                        <div class="status-step-content">
                                                            <div class="status-step-text">{Lang::T('Processing Payment')}</div>
                                                            <div class="status-step-subtext">{Lang::T('Confirming transaction')}</div>
                                                        </div>
                                                    </li>
                                                    <li class="status-step" id="step-completed">
                                                        <div class="status-step-icon"><i class="fa fa-check-circle"></i></div>
                                                        <div class="status-step-content">
                                                            <div class="status-step-text">{Lang::T('Payment Completed')}</div>
                                                            <div class="status-step-subtext">{Lang::T('Your package will be activated')}</div>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                            
                                            <div class="status-indicator">
                                                <div class="progress-spinner" id="payment-spinner">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="sr-only">{Lang::T('Loading...')}</span>
                                                    </div>
                                                </div>
                                                <div class="status-message alert alert-info" id="payment-message">
                                                    {Lang::T('Waiting for your payment approval')}
                                                </div>
                                                <div class="status-timer" id="payment-timer">
                                                    <span class="countdown-text">{Lang::T('Payment expires in')}:</span>
                                                    <span class="countdown-timer" id="countdown-timer"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="status-footer card-footer">
                                            <div class="status-actions">
                                                <a href="{$_url}order/view/{$trx['id']}/check" id="check-status-btn" class="btn btn-info">
                                                    <i class="fa fa-refresh"></i> {Lang::T('Check Status')}
                                                </a>
                                            </div>
                                            <div class="status-actions mt-2">
                                                <div class="btn-group">
                                                    <form method="POST" action="{$_url}order/buy/{$plan['id']}/{$router['id']}" id="retry-payment-form" style="display:inline-block;">
                                                        <input type="hidden" name="gateway" value="mpesa">
                                                        <button type="submit" class="btn btn-warning" id="retry-payment-btn">
                                                            <i class="fa fa-repeat"></i> {Lang::T('Retry Payment')}
                                                        </button>
                                                    </form>
                                                    <a href="{$_url}order/package" class="btn btn-default" id="other-options-btn">
                                                        <i class="fa fa-arrow-left"></i> {Lang::T('Other Options')}
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-success-actions text-center mt-3" style="display:none;" id="success-actions">
                                        <div class="alert alert-success">
                                            <strong><i class="fa fa-check-circle"></i> {Lang::T('Payment Successful!')}</strong>
                                            <p>{Lang::T('Your package has been activated')}</p>
                                        </div>
                                        <a href="{$_url}dashboard" class="btn btn-success btn-lg">
                                            <i class="fa fa-home"></i> {Lang::T('Go to Dashboard')}
                                        </a>
                                    </div>
                                    
                                    <div class="payment-failure-actions text-center mt-3" style="display:none;" id="failure-actions">
                                        <div class="alert alert-danger">
                                            <strong><i class="fa fa-times-circle"></i> <span id="failure-message">{Lang::T('Payment Failed')}</span></strong>
                                            <p>{Lang::T('Please try again or choose another payment method')}</p>
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        {else}
                            <a href="{$trx['pg_url_payment']}" 
                               {if $trx['gateway'] eq 'midtrans'} target="_blank" {/if}
                               class="btn btn-primary btn-block">
                                {Lang::T('Pay Now')}
                            </a>
                        {/if}
                    </div>
                {elseif $trx['status']==2}
                    <div class="alert alert-success">
                        <strong><i class="fa fa-check-circle"></i> {Lang::T('Payment Completed Successfully')}</strong>
                        <p>{Lang::T('Your package has been activated')}</p>
                    </div>
                    <a href="{$_url}home" class="btn btn-primary btn-block">
                        {Lang::T('Return to Dashboard')}
                    </a>
                {elseif $trx['status']==3}
                    <div class="alert alert-danger">
                        <strong><i class="fa fa-times-circle"></i> {Lang::T('Payment Failed')}</strong>
                        <p>{Lang::T('Please try again or choose another payment method')}</p>
                    </div>
                    <div class="btn-group btn-group-justified">
                        <a href="{$_url}order/package" class="btn btn-primary">
                            {Lang::T('Try Again')}
                        </a>
                        <a href="{$_url}home" class="btn btn-default">
                            {Lang::T('Return to Dashboard')}
                        </a>
                    </div>
                {elseif $trx['status']==4}
                    <div class="alert alert-danger">
                        <strong><i class="fa fa-ban"></i> {Lang::T('Transaction Cancelled')}</strong>
                        <p>{Lang::T('This transaction has been cancelled')}</p>
                    </div>
                    <a href="{$_url}order/package" class="btn btn-primary btn-block">
                        {Lang::T('Browse Packages')}
                    </a>
                {/if}

                {if $trx['status']==1}
                    <div class="panel-footer mt-3">
                        <a href="{$_url}order/view/{$trx['id']}/cancel" 
                           class="btn btn-danger"
                           onclick="return confirm('{Lang::T('Are you sure you want to cancel this transaction?')}')">
                            <i class="fa fa-times"></i> {Lang::T('Cancel Transaction')}
                        </a>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced M-Pesa Payment Status Styles */
.mpesa-payment-section {
    margin-top: 20px;
}

.mpesa-payment-status {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    background: #fff;
    margin-bottom: 20px;
}

.status-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.status-title {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.status-badge .badge {
    font-size: 12px;
    padding: 5px 10px;
    border-radius: 20px;
}

.status-body {
    padding: 20px;
}

.progress-tracker {
    margin-bottom: 25px;
}

.status-steps {
    display: flex;
    justify-content: space-between;
    padding: 0;
    margin: 0;
    list-style: none;
    position: relative;
}

.status-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.status-step {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 2;
    padding: 0 5px;
}

.status-step-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    margin: 0 auto 10px;
    color: #6c757d;
    font-size: 16px;
    position: relative;
    z-index: 2;
    transition: all 0.3s ease;
}

.status-step.active .status-step-icon {
    background: #007bff;
    color: white;
    box-shadow: 0 0 0 5px rgba(0,123,255,0.2);
}

.status-step.completed .status-step-icon {
    background: #28a745;
    color: white;
}

.status-step-content {
    margin-top: 5px;
}

.status-step-text {
    font-weight: 600;
    font-size: 14px;
    color: #343a40;
    margin-bottom: 5px;
}

.status-step-subtext {
    font-size: 12px;
    color: #6c757d;
}

.status-indicator {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.progress-spinner {
    margin-bottom: 15px;
}

.status-message {
    font-size: 16px;
    margin-bottom: 15px;
}

.status-timer {
    font-size: 14px;
    color: #6c757d;
}

.countdown-timer {
    font-weight: 700;
    color: #dc3545;
}

.status-footer {
    padding: 15px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.status-actions {
    display: flex;
    justify-content: center;
}

.spinner-border {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    vertical-align: text-bottom;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border .75s linear infinite;
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}

.payment-success-actions,
.payment-failure-actions {
    margin-top: 20px;
}

/* Card styling */
.card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    word-wrap: break-word;
    background-color: #fff;
    background-clip: border-box;
    border: 1px solid rgba(0,0,0,.125);
    border-radius: .25rem;
}

.card-header {
    padding: .75rem 1.25rem;
    margin-bottom: 0;
    background-color: rgba(0,0,0,.03);
    border-bottom: 1px solid rgba(0,0,0,.125);
}

.card-body {
    flex: 1 1 auto;
    padding: 1.25rem;
}

.card-footer {
    padding: .75rem 1.25rem;
    background-color: rgba(0,0,0,.03);
    border-top: 1px solid rgba(0,0,0,.125);
}

/* Alert styling */
.alert {
    position: relative;
    padding: .75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: .25rem;
}

.alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.mt-2 {
    margin-top: 0.5rem !important;
}

.mt-3 {
    margin-top: 1rem !important;
}

/* Make buttons more appealing */
.btn {
    display: inline-block;
    font-weight: 400;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    user-select: none;
    border: 1px solid transparent;
    padding: .375rem .75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: .25rem;
    transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
}

.btn:focus, .btn:hover {
    text-decoration: none;
}

.btn-lg {
    padding: .5rem 1rem;
    font-size: 1.25rem;
    line-height: 1.5;
    border-radius: .3rem;
}

.btn-info {
    color: #fff;
    background-color: #17a2b8;
    border-color: #17a2b8;
}

.btn-success {
    color: #fff;
    background-color: #28a745;
    border-color: #28a745;
}

.btn-warning {
    color: #212529;
    background-color: #ffc107;
    border-color: #ffc107;
}

.btn-danger {
    color: #fff;
    background-color: #dc3545;
    border-color: #dc3545;
}
</style>

{if $trx['status']==1 && $trx['gateway'] eq 'mpesa' && isset($trx['pg_request'])}
    <script>
        // Enhanced M-Pesa Payment Checker
        $(document).ready(function() {
            // Define Lang translation object if it doesn't exist
            if (typeof Lang === 'undefined') {
                window.Lang = {
                    T: function(text) {
                        return text;
                    }
                };
            }
            
            // Define appUrl if it doesn't exist
            if (typeof appUrl === 'undefined') {
                window.appUrl = '{$_url}';
            }
            
            // Calculate expiry time
            const expiryDate = new Date('{$trx['expired_date']}');
            const now = new Date();
            const timeLeft = expiryDate - now;
            
            if (timeLeft > 0) {
                // Update countdown timer
                const countdownTimer = document.getElementById('countdown-timer');
                if (countdownTimer) {
                    startCountdown(expiryDate, countdownTimer);
                }
                
                // Initialize the payment checker
                const paymentChecker = new MpesaPaymentChecker({
                    transactionId: '{$trx['id']}',
                    checkUrl: '{$_url}callback/check-payment-status',
                    redirectUrl: '{$_url}dashboard',
                    checkInterval: 6000, // Check every 6 seconds
                    maxAttempts: 50,     // Maximum 50 attempts (5 minutes)
                    onStatusChange: updatePaymentStatus
                });
            } else {
                // Payment has expired
                updatePaymentStatus('expired');
            }
            
            // Function to update the payment status UI
            function updatePaymentStatus(status, message) {
                const stepInitiated = document.getElementById('step-initiated');
                const stepApproval = document.getElementById('step-approval');
                const stepProcessing = document.getElementById('step-processing');
                const stepCompleted = document.getElementById('step-completed');
                const statusBadge = document.getElementById('status-badge');
                const paymentMessage = document.getElementById('payment-message');
                const paymentSpinner = document.getElementById('payment-spinner');
                const successActions = document.getElementById('success-actions');
                const failureActions = document.getElementById('failure-actions');
                const failureMessage = document.getElementById('failure-message');
                
                // Reset all steps
                stepInitiated.className = 'status-step completed';
                stepApproval.className = 'status-step';
                stepProcessing.className = 'status-step';
                stepCompleted.className = 'status-step';
                
                switch(status) {
                    case 'processing':
                        statusBadge.innerHTML = '<span class="badge badge-primary">Processing</span>';
                        stepApproval.className = 'status-step completed';
                        stepProcessing.className = 'status-step active';
                        paymentMessage.className = 'status-message alert alert-info';
                        paymentMessage.innerHTML = message || Lang.T('Processing your payment...');
                        break;
                    case 'completed':
                    case 'success':
                        statusBadge.innerHTML = '<span class="badge badge-success">Completed</span>';
                        stepApproval.className = 'status-step completed';
                        stepProcessing.className = 'status-step completed';
                        stepCompleted.className = 'status-step completed';
                        paymentMessage.className = 'status-message alert alert-success';
                        paymentMessage.innerHTML = message || Lang.T('Payment completed successfully!');
                        paymentSpinner.style.display = 'none';
                        document.querySelector('.mpesa-payment-status').style.display = 'none';
                        successActions.style.display = 'block';
                        setTimeout(function() {
                            window.location.href = '{$_url}dashboard';
                        }, 3000);
                        break;
                    case 'failed':
                        statusBadge.innerHTML = '<span class="badge badge-danger">Failed</span>';
                        stepApproval.className = 'status-step completed';
                        stepProcessing.className = 'status-step active';
                        paymentMessage.className = 'status-message alert alert-danger';
                        paymentMessage.innerHTML = message || Lang.T('Payment failed. Please try again.');
                        paymentSpinner.style.display = 'none';
                        failureActions.style.display = 'block';
                        failureMessage.innerHTML = Lang.T('Payment Failed');
                        break;
                    case 'expired':
                        statusBadge.innerHTML = '<span class="badge badge-secondary">Expired</span>';
                        paymentMessage.className = 'status-message alert alert-warning';
                        paymentMessage.innerHTML = Lang.T('Payment request has expired. Please try again.');
                        paymentSpinner.style.display = 'none';
                        failureActions.style.display = 'block';
                        failureMessage.innerHTML = Lang.T('Payment Expired');
                        break;
                    case 'cancelled':
                        statusBadge.innerHTML = '<span class="badge badge-info">Cancelled</span>';
                        paymentMessage.className = 'status-message alert alert-warning';
                        paymentMessage.innerHTML = Lang.T('Payment was cancelled. Please try again.');
                        paymentSpinner.style.display = 'none';
                        failureActions.style.display = 'block';
                        failureMessage.innerHTML = Lang.T('Payment Cancelled');
                        break;
                    default:
                        statusBadge.innerHTML = '<span class="badge badge-primary">Processing</span>';
                        stepApproval.className = 'status-step active';
                        paymentMessage.className = 'status-message alert alert-info';
                        paymentMessage.innerHTML = message || Lang.T('Waiting for your payment approval');
                }
            }
            
            // Function to start countdown timer
            function startCountdown(endDate, element) {
                if (!element) return;
                
                const timer = setInterval(function() {
                    const now = new Date().getTime();
                    const distance = endDate - now;
                    
                    if (distance < 0) {
                        clearInterval(timer);
                        element.innerHTML = "00:00:00";
                        updatePaymentStatus('expired');
                        return;
                    }
                    
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    
                    element.innerHTML = 
                        (hours < 10 ? "0" + hours : hours) + ":" +
                        (minutes < 10 ? "0" + minutes : minutes) + ":" +
                        (seconds < 10 ? "0" + seconds : seconds);
                }, 1000);
            }
            
            // Add refresh functionality to check status button
            $('#check-status-btn').on('click', function(e) {
                e.preventDefault();
                
                // Show spinner
                paymentMessage.innerHTML = Lang.T('Checking payment status...');
                
                // Fetch updated status
                $.ajax({
                    url: '{$_url}callback/check-payment-status',
                    type: 'POST',
                    data: { 
                        transaction_id: '{$trx['id']}'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            updatePaymentStatus('completed', response.message);
                        } else if (response.status === 'failed') {
                            updatePaymentStatus('failed', response.message);
                        } else if (response.status === 'expired') {
                            updatePaymentStatus('expired', response.message);
                        } else if (response.status === 'cancelled') {
                            updatePaymentStatus('cancelled', response.message);
                        } else {
                            updatePaymentStatus('processing', response.message || Lang.T('Still processing your payment...'));
                        }
                    },
                    error: function() {
                        paymentMessage.innerHTML = Lang.T('Failed to check status. Please try again.');
                    }
                });
                
                return false;
            });
        });
        
        /**
         * M-Pesa Payment Checker Class
         */
        class MpesaPaymentChecker {
            constructor(options) {
                this.transactionId = options.transactionId;
                this.checkUrl = options.checkUrl;
                this.redirectUrl = options.redirectUrl;
                this.checkInterval = options.checkInterval || 5000;
                this.maxAttempts = options.maxAttempts || 60;
                this.currentAttempt = 0;
                this.onStatusChange = options.onStatusChange || function() {};
                
                this.checkTimer = null;
                this.startChecking();
            }
            
            startChecking() {
                this.checkStatus();
                this.checkTimer = setInterval(() => this.checkStatus(), this.checkInterval);
            }
            
            stopChecking() {
                if (this.checkTimer) {
                    clearInterval(this.checkTimer);
                }
            }
            
            checkStatus() {
                if (this.currentAttempt >= this.maxAttempts) {
                    this.stopChecking();
                    this.onStatusChange('expired', 'Payment request timed out');
                    return;
                }
                
                this.currentAttempt++;
                
                $.ajax({
                    url: this.checkUrl,
                    type: 'POST',
                    data: { transaction_id: this.transactionId },
                    dataType: 'json',
                    success: (response) => this.handleResponse(response),
                    error: () => this.handleError()
                });
            }
            
            handleResponse(response) {
                if (response.status === 'success') {
                    this.stopChecking();
                    this.onStatusChange('completed', response.message);
                    
                    setTimeout(() => {
                        window.location.href = this.redirectUrl;
                    }, 3000);
                } else if (response.status === 'failed') {
                    this.stopChecking();
                    this.onStatusChange('failed', response.message);
                } else if (response.status === 'expired') {
                    this.stopChecking();
                    this.onStatusChange('expired', response.message);
                } else if (response.status === 'cancelled') {
                    this.stopChecking();
                    this.onStatusChange('cancelled', response.message);
                } else if (response.status === 'processing') {
                    this.onStatusChange('processing', response.message);
                }
            }
            
            handleError() {
                // Continue checking even if there's an error
                this.onStatusChange('processing', 'Still checking payment status...');
            }
        }
    </script>
{/if}

{include file="customer/footer.tpl"}


