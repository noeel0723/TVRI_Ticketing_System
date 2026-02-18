<?php
require_once __DIR__ . '/config/bootstrap.php';
startSecureSession();
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();
header('Location: index.php?error=logout');
exit();
?>

