<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

$soap_url  = 'http://127.0.0.1:7878';
$soap_user = 'admin'; // Must be GM level 3
$soap_pass = 'admin';
?>