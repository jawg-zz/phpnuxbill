<?php
/**
 * M-Pesa Payment Gateway for PHPNuxBill
 * @author Spidmax Technologies
 * @copyright 2024
 */

_admin();
$ui->assign('_title', 'M-Pesa Settings');
$ui->assign('_system_menu', 'settings');

$action = $routes[2];

switch ($action) {
    case 'post':
        $mpesa_consumer_key = _post('mpesa_consumer_key');
        $mpesa_consumer_secret = _post('mpesa_consumer_secret');
        $mpesa_shortcode = _post('mpesa_shortcode');
        $mpesa_passkey = _post('mpesa_passkey');
        
        $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_key')->find_one();
        if($d){
            $d->value = $mpesa_consumer_key;
            $d->save();
        }else{
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = 'mpesa_consumer_key';
            $d->value = $mpesa_consumer_key;
            $d->save();
        }
        
        $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_secret')->find_one();
        if($d){
            $d->value = $mpesa_consumer_secret;
            $d->save();
        }else{
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = 'mpesa_consumer_secret';
            $d->value = $mpesa_consumer_secret;
            $d->save();
        }
        
        $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_shortcode')->find_one();
        if($d){
            $d->value = $mpesa_shortcode;
            $d->save();
        }else{
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = 'mpesa_shortcode';
            $d->value = $mpesa_shortcode;
            $d->save();
        }
        
        $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_passkey')->find_one();
        if($d){
            $d->value = $mpesa_passkey;
            $d->save();
        }else{
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = 'mpesa_passkey';
            $d->value = $mpesa_passkey;
            $d->save();
        }
        
        _log('[' . $admin['username'] . ']: M-Pesa Settings updated');
        r2(U . 'paymentgateway/mpesa', 's', 'Settings updated successfully');
        break;

    default:
        $ui->assign('mpesa_consumer_key', $config['mpesa_consumer_key']);
        $ui->assign('mpesa_consumer_secret', $config['mpesa_consumer_secret']);
        $ui->assign('mpesa_shortcode', $config['mpesa_shortcode']);
        $ui->assign('mpesa_passkey', $config['mpesa_passkey']);
        
        $ui->display('mpesa/admin.tpl');
        break;
}