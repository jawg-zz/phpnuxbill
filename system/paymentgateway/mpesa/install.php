<?php
/**
 * M-Pesa Payment Gateway for PHPNuxBill
 * @author Spidmax Technologies
 * @copyright 2024
 */

if(!defined("_VALID_ACCESS")) {
    die('Direct access to this location is not allowed');
}

$sql = "
INSERT INTO `tbl_appconfig` (`setting`, `value`) VALUES
    ('mpesa_consumer_key', ''),
    ('mpesa_consumer_secret', ''),
    ('mpesa_shortcode', ''),
    ('mpesa_passkey', '');

INSERT INTO `tbl_payment_gateway` (`name`, `gateway`, `status`, `setting`) VALUES
    ('M-Pesa', 'mpesa', 'Active', '');
";

try {
    $d = ORM::execute($sql);
    $message = 'M-Pesa Payment Gateway installed successfully';
} catch (Exception $e) {
    $message = 'Error installing M-Pesa Payment Gateway: ' . $e->getMessage();
}

return $message;