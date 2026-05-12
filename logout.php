<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
startSession();
session_destroy();
header('Location: /login.php');
exit;
