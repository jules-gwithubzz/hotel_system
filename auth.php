<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // Redirect to the correct relative login page
    $depth = substr_count(str_replace('\\', '/', $_SERVER['PHP_SELF']), '/');
    $prefix = ($depth > 2) ? '../' : '';
    header("Location: {$prefix}login.php");
    exit();
}
