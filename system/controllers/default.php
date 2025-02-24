<?php
/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

if(Admin::getID()){
    //r2(getUrl('dashboard'));
    $handler = 'dashboard';
}else if(User::getID()){
    //r2(getUrl('home'));
    $handler = 'home';
}else{
    // Check if it's a hotspot login attempt
    if (isset($_GET['nux-mac']) && isset($_GET['nux-ip'])) {
        r2(getUrl('login/mlogin')); // Redirect to mlogin for hotspot users
    }
    $handler = 'login';
}
include($root_path . File::pathFixer('system/controllers/' . $handler . '.php'));
