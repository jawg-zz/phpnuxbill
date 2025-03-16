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
                                    <div class="mpesa-payment-status mpesa-status-checking">
                                        <div class="status-header">
                                            <h4 class="status-title">{Lang::T('M-PESA Payment')}</h4>
                                            <span class="status-badge" id="status-badge">
                                                <span class="badge badge-primary">{Lang::T('Processing')}</span>
                                            </span>
                                        </div>
                                        <div class="status-body">
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
                                            
                                            <div class="status-indicator">
                                                <div class="progress-spinner" id="payment-spinner"></div>
                                                <div class="status-message" id="payment-message">
                                                    {Lang::T('Waiting for your payment approval')}
                                                </div>
                                                <div class="status-timer" id="payment-timer"></div>
                                            </div>
                                        </div>
                                        <div class="status-footer">
                                            <div class="status-actions">
                                                <a href="{$_url}order/view/{$trx['id']}/check" id="check-status-btn" class="btn btn-info">
                                                    <i class="fa fa-refresh"></i> {Lang::T('Check Status')}
                                                </a>
                                            </div>
                                            <div class="status-actions">
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
                        <strong>{Lang::T('Payment Completed Successfully')}</strong>
                        <p>{Lang::T('Your package has been activated')}</p>
                    </div>
                    <a href="{$_url}home" class="btn btn-primary btn-block">
                        {Lang::T('Return to Dashboard')}
                    </a>
                {elseif $trx['status']==3}
                    <div class="alert alert-danger">
                        <strong>{Lang::T('Payment Failed')}</strong>
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
                        <strong>{Lang::T('Transaction Cancelled')}</strong>
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
                            {Lang::T('Cancel Transaction')}
                        </a>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>

{if $trx['status']==1 && $trx['gateway'] eq 'mpesa' && isset($trx['pg_request'])}
    <script>
        // Initialize the enhanced payment checker
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
            
            // Initialize the payment checker
            const paymentChecker = new MpesaPaymentChecker({
                transactionId: '{$trx['id']}',
                checkUrl: '{$_url}callback/check-payment-status',
                redirectUrl: '{$_url}dashboard',
                checkInterval: 6000, // Check every 6 seconds
                maxAttempts: 50      // Maximum 50 attempts (5 minutes)
            });
        });
    </script>
{/if}

<script src="{$app_url}/ui/ui/scripts/mpesa-payment.js"></script>
{include file="customer/footer.tpl"}


