<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

_admin();
$ui->assign('_title', 'PHPNuxBill Logs');
$ui->assign('_system_menu', 'logs');

$action = $routes['1'];
$ui->assign('_admin', $admin);

if (!in_array($admin['user_type'], ['SuperAdmin', 'Admin'])) {
    _alert(Lang::T('You do not have permission to access this page'),'danger', "dashboard");
}


switch ($action) {
    case 'list':
        $q = (_post('q') ? _post('q') : _get('q'));
        $keep = _post('keep');
        if (!empty($keep)) {
            ORM::raw_execute("DELETE FROM tbl_logs WHERE UNIX_TIMESTAMP(date) < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL $keep DAY))");
            r2(U . "logs/list/", 's', "Delete logs older than $keep days");
        }
        if ($q != '') {
            $query = ORM::for_table('tbl_logs')->where_like('description', '%' . $q . '%');
            $paginator = Paginator::generate($query, ['q' => $q]);
            $d = $query->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        } else {
            $query = ORM::for_table('tbl_logs');
            $paginator = Paginator::generate($query);
            $d = $query->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        }

        $ui->assign('d', $d);
        $ui->assign('q', $q);
        $ui->assign('paginator', $paginator);
        $ui->display('logs.tpl');
        break;
    case 'radius':
        $q = (_post('q') ? _post('q') : _get('q'));
        $keep = _post('keep');
        if (!empty($keep)) {
            ORM::raw_execute("DELETE FROM radpostauth WHERE UNIX_TIMESTAMP(authdate) < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL $keep DAY))", [], 'radius');
            r2(U . "logs/radius/", 's', "Delete logs older than $keep days");
        }
        if ($q != '') {
            $query = ORM::for_table('radpostauth', 'radius')->where_like('username', '%' . $q . '%');
            $paginator = Paginator::generate($query, ['q' => $q]);
            $d = $query->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        } else {
            $query = ORM::for_table('radpostauth', 'radius');
            $paginator = Paginator::generate($query);
            $d = $query->offset($paginator['startpoint'])->limit($paginator['limit'])->order_by_desc('id')->find_many();
        }

        $ui->assign('d', $d);
        $ui->assign('q', $q);
        $ui->assign('paginator', $paginator);
        $ui->display('logs-radius.tpl');
        break;


    default:
        r2(U . 'logs/list/', 's', '');
}
