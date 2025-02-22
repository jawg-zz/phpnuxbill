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
                            <td>{Lang::moneyFormat($plan['price'])}</td>
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
                                    <div class="alert alert-warning">
                                        <strong>{Lang::T('Payment in Progress')}:</strong>
                                        <ol>
                                            <li>{Lang::T('Check your phone for the STK Push')}</li>
                                            <li>{Lang::T('Enter your M-Pesa PIN to complete payment')}</li>
                                            <li>{Lang::T('Wait for confirmation')}</li>
                                        </ol>
                                        <p class="mt-2">{Lang::T('If you did not receive the STK Push, click Retry Payment below')}</p>
                                    </div>
                                    <div class="btn-group btn-group-justified">
                                        <form method="POST" action="{$_url}order/buy/{$plan['id']}/{$router['id']}">
                                            <input type="hidden" name="gateway" value="mpesa">
                                            <button type="submit" class="btn btn-warning">
                                                {Lang::T('Retry Payment')}
                                            </button>
                                        </form>
                                        <a href="{$_url}order/view/{$trx['id']}/check" class="btn btn-info">
                                            {Lang::T('Check Status')}
                                        </a>
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
        // Auto refresh for pending M-Pesa transactions
        setTimeout(function() {
            window.location.href = '{$_url}order/view/{$trx['id']}/check';
        }, 10000); // Refresh every 10 seconds
    </script>
{/if}

{include file="customer/footer.tpl"}
