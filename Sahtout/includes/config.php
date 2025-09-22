<?php
if (!defined('ALLOWED_ACCESS')) exit('Direct access not allowed.');
define('SUBDIR', /sahtout/);//required for dynamic structure
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root';
$db_auth = 'test_auth';
$db_world = 'test_world';
$db_char = 'test_char';
$db_site = 'test_site';
$auth_db = new mysqli($db_host,$db_user,$db_pass,$db_auth);
$world_db = new mysqli($db_host,$db_user,$db_pass,$db_world);
$char_db = new mysqli($db_host,$db_user,$db_pass,$db_char);
$site_db = new mysqli($db_host,$db_user,$db_pass,$db_site);

if ($auth_db->connect_error) die('Auth DB Connection failed: ' . $auth_db->connect_error);
if ($world_db->connect_error) die('World DB Connection failed: ' . $world_db->connect_error);
if ($char_db->connect_error) die('Char DB Connection failed: ' . $char_db->connect_error);
if ($site_db->connect_error) die('Site DB Connection failed: ' . $site_db->connect_error);
?>