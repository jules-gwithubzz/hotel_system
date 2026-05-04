<?php
include 'auth.php';

if ($_SESSION['role'] == 'admin') {
    header("Location: admin/admin_dashboard.php");
} else {
    header("Location: staff/staff_dashboard.php");
}
exit();
?>