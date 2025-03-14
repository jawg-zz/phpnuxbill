<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

_admin();
$ui->assign('_system_menu', 'paymentgateway');

$action = alphanumeric($routes[1]);
$ui->assign('_admin', $admin);
switch ($action) {
    case 'delete':
        $pg = alphanumeric($routes[2]);
        if (file_exists($PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR . $pg . '.php')) {
            deleteFile($PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR, $pg);
        }
        r2(getUrl('paymentgateway'), 's', Lang::T('Payment Gateway Deleted'));

    case 'audit':
        $pg = alphanumeric($routes[2]);
        $q = alphanumeric(_req('q'), '-._ ');
        $query = ORM::for_table('tbl_payment_gateway')->order_by_desc("id");
        $query->selects('id', 'username', 'gateway', 'gateway_trx_id', 'plan_id', 'plan_name', 'routers_id', 'routers', 'price', 'pg_url_payment', 'payment_method', 'payment_channel', 'expired_date', 'created_date', 'paid_date', 'trx_invoice', 'status');
        $query->where('gateway', $pg);
        if (!empty($q)) {
            $query->whereRaw("(gateway_trx_id LIKE '%$q%' OR username LIKE '%$q%' OR routers LIKE '%$q%' OR plan_name LIKE '%$q%')");
            $append_url = 'q=' . urlencode($q);
        }
        $pgs = Paginator::findMany($query, ["search" => $search], 50, $append_url);

        $ui->assign('_title', 'Payment Gateway Audit');
        $ui->assign('pgs', $pgs);
        $ui->assign('pg', $pg);
        $ui->assign('q', $q);
        $ui->display('admin/paymentgateway/audit.tpl');
        break;
    case 'auditview':
        $pg = alphanumeric($routes[2]);
        $d = ORM::for_table('tbl_payment_gateway')->find_one($pg);
        $d['pg_request'] = (!empty($d['pg_request']))? Text::jsonArray21Array(json_decode($d['pg_request'], true)) : [];
        $d['pg_paid_response'] = (!empty($d['pg_paid_response']))? Text::jsonArray21Array(json_decode($d['pg_paid_response'], true)) : [];
        $ui->assign('_title', 'Payment Gateway Audit View');
        $ui->assign('pg', $d);
        $ui->display('admin/paymentgateway/audit-view.tpl');
        break;
    case 'check-mpesa-status':
        $trx_id = alphanumeric($routes[2]);
        $trx = ORM::for_table('tbl_payment_gateway')->find_one($trx_id);
        
        if (!$trx) {
            r2(getUrl('paymentgateway/audit/mpesa'), 'e', Lang::T('Transaction not found'));
        }
        
        if ($trx['gateway'] !== 'mpesa') {
            r2(getUrl('paymentgateway/audit/' . $trx['gateway']), 'e', Lang::T('Not an M-Pesa transaction'));
        }
        
        if ($trx['status'] == 2) {
            r2(getUrl('paymentgateway/auditview/' . $trx_id), 'd', Lang::T('Transaction already paid'));
        }
        
        // Check if transaction has expired
        if (strtotime($trx['expired_date']) < time()) {
            $trx->status = 3; // Failed status
            $trx->pg_paid_response = json_encode(['error' => 'Transaction expired']);
            $trx->save();
            r2(getUrl('paymentgateway/auditview/' . $trx_id), 'w', Lang::T('Transaction has expired'));
        }
        
        try {
            include $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR . 'mpesa.php';
            
            $mpesa = get_mpesa_gateway();
            $result = $mpesa->queryTransactionStatus($trx['gateway_trx_id']);
            
            if ($result['ResultCode'] === '0') {
                $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
                if (!$user) {
                    r2(getUrl('paymentgateway/auditview/' . $trx_id), 'e', Lang::T('User not found'));
                }
                
                process_successful_payment($trx, $user, $result);
                r2(getUrl('paymentgateway/auditview/' . $trx_id), 's', Lang::T('Payment verified successfully'));
            } else {
                r2(getUrl('paymentgateway/auditview/' . $trx_id), 'w', Lang::T('Transaction still unpaid'));
            }
        } catch (Exception $e) {
            r2(getUrl('paymentgateway/auditview/' . $trx_id), 'e', $e->getMessage());
        }
        break;
    default:
        if (_post('save') == 'actives') {
            $pgs = '';
            if (is_array($_POST['pgs'])) {
                $pgs = implode(',', $_POST['pgs']);
            }
            $d = ORM::for_table('tbl_appconfig')->where('setting', 'payment_gateway')->find_one();
            if ($d) {
                $d->value = $pgs;
                $d->save();
            } else {
                $d = ORM::for_table('tbl_appconfig')->create();
                $d->setting = 'payment_gateway';
                $d->value = $pgs;
                $d->save();
            }
            r2(getUrl('paymentgateway'), 's', Lang::T('Payment Gateway saved successfully'));
        }

        if (file_exists($PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR . $action . '.php')) {
            include $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR . $action . '.php';
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (function_exists($action . '_save_config')) {
                    call_user_func($action . '_save_config');
                } else {
                    $ui->display('admin/404.tpl');
                }
            } else {
                if (function_exists($action . '_show_config')) {
                    call_user_func($action . '_show_config');
                } else {
                    $ui->display('admin/404.tpl');
                }
            }
        } else {
            if (!empty($action)) {
                r2(getUrl('paymentgateway'), 'w', Lang::T('Payment Gateway Not Found'));
            } else {
                $files = scandir($PAYMENTGATEWAY_PATH);
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                        $pgs[] = str_replace('.php', '', $file);
                    }
                }
                $ui->assign('_title', 'Payment Gateway Settings');
                $ui->assign('pgs', $pgs);
                $ui->assign('actives', explode(',', $config['payment_gateway']));
                $ui->display('admin/paymentgateway/list.tpl');
            }
        }
}


function deleteFile($path, $name)
{
    $files = scandir($path);
    foreach ($files as $file) {
        if (is_file($path . $file) && strpos($file, $name) !== false) {
            unlink($path . $file);
        } else if (is_dir($path . $file) && !in_array($file, ['.', '..'])) {
            deleteFile($path . $file . DIRECTORY_SEPARATOR, $name);
        }
    }
}
