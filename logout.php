<?php
// logout.php — safely log out and return to login page
require_once __DIR__ . '/assets/inc/authenticate.php';
auth_logout();
header('Location: login.php');
exit;
