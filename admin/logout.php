<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
startSession();
unset($_SESSION['admin_id'], $_SESSION['admin_username']);
session_destroy();
header('Location: /admin/login.php');
exit;
