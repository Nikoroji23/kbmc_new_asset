<?php
/**
 * KBMC Asset Management - System Entry Point
 */
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();
