{include file="sections/header.tpl"}


<div class="row">
    <div class="col-sm-5">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                {$pg['gateway_trx_id']}
            </div>
            <div class="panel-body">
                <ul class="list-group list-group-unbordered">
                    <li class="list-group-item">
                        <b>{Lang::T('TRX ID')}</b> <span class="pull-right">&nbsp;{$pg['id']}&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Invoice')}</b> <span class="pull-right">&nbsp;
                            <a href="{Text::url('reports/activation&q=')}{$pg['trx_invoice']}"
                                class="text-black">{$pg['trx_invoice']}</a>
                            &nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Status')}</b> 
                        <span class="pull-right">&nbsp;
                            {if $pg['status'] == 1}
                                <span class="label label-warning">UNPAID</span>
                            {elseif $pg['status'] == 2}
                                <span class="label label-success">PAID</span>
                            {elseif $pg['status'] == 3}
                                <span class="label label-danger">FAILED</span>
                            {elseif $pg['status'] == 4}
                                <span class="label label-danger">CANCELED</span>
                            {/if}
                            &nbsp;
                        </span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Username')}</b>
                        <span class="pull-right">&nbsp;<a href="{Text::url('customers/viewu/')}{$pg['username']}"
                                class="text-black">{$pg['username']}</a>&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Plan Name')}</b> <span class="pull-right">&nbsp;{$pg['plan_name']}&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Routers')}</b> <span class="pull-right">&nbsp;{$pg['routers']}&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Price')}</b> <span
                            class="pull-right">&nbsp;{Lang::moneyFormat($pg['price'])}&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Payment Gateway')}</b> <span class="pull-right">&nbsp;{$pg['gateway']}&nbsp;</span>
                    </li>
                    {if $pg['gateway'] eq 'mpesa'}
                    <li class="list-group-item">
                        <b>{Lang::T('M-Pesa Phone')}</b> <span class="pull-right">&nbsp;{$pg['account']}&nbsp;</span>
                    </li>
                    {/if}
                    <li class="list-group-item">
                        <b>{Lang::T('Payment Link')}</b> <span class="pull-right">&nbsp;{if $pg['pg_url_payment']}
                                <a href="{$pg['pg_url_payment']}" target="_blank" class="btn btn-xs btn-default"
                                    rel="noopener noreferrer">open</a>
                            {/if}&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Channel')}</b> <span class="pull-right">&nbsp;{$pg['payment_method']} -
                            {$pg['payment_channel']}&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Created')}</b> <span
                            class="pull-right">&nbsp;{if $pg['created_date'] != null}{Lang::dateTimeFormat($pg['created_date'])}{/if}&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Expired')}</b> <span
                            class="pull-right">&nbsp;{if $pg['expired_date'] != null}{Lang::dateTimeFormat($pg['expired_date'])}{/if}&nbsp;</span>
                    </li>
                    <li class="list-group-item">
                        <b>{Lang::T('Paid')}</b> <span
                            class="pull-right">&nbsp;{if $pg['paid_date'] != null}{Lang::dateTimeFormat($pg['paid_date'])}{/if}&nbsp;</span>
                    </li>
                </ul>
            </div>
            {if $pg['gateway'] eq 'mpesa' && $pg['status'] == 1}
            <div class="panel-footer">
                <a href="{Text::url('paymentgateway/check-mpesa-status/')}{$pg['id']}" class="btn btn-primary">
                    <i class="fa fa-refresh"></i> {Lang::T('Check M-Pesa Status')}
                </a>
            </div>
            {/if}
        </div>
    </div>
</div>

<div class="panel panel-hovered mb20 panel-primary">
    <div class="panel-heading">
        {Lang::T('Response when request payment')}
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-condensed">
            {foreach $pg['pg_request'] as $k => $v}
                <tr>
                    <td>{$k}</td>
                    <td>{$v}</td>
                </tr>
            {/foreach}
        </table>
    </div>
</div>
<div class="panel panel-hovered mb20 panel-primary">
    <div class="panel-heading">
        {Lang::T('Response when payment PAID')}
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-condensed">
            {foreach $pg['pg_paid_response'] as $k => $v}
                <tr>
                    <td>{$k}</td>
                    <td>{$v}</td>
                </tr>
            {/foreach}
        </table>
    </div>
</div>

{include file="sections/footer.tpl"}