<?php
// config/functions.php

/**
 * Checks if the user is logged in. If not, redirects to the login page.
 */
function check_login() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php"); // Redirect to login page
        exit();
    }
}

/**
 * Checks if the logged-in user has the required role.
 * @param string $required_role The role needed ('admin', 'receptionist', 'stylist')
 */
function check_access($required_role) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if logged in first
    if (!isset($_SESSION['role'])) {
        header("Location: /login.php?error=Access Denied");
        exit();
    }
    
    // Simple access check: Admin can do anything
    if ($_SESSION['role'] === 'admin') {
        return; 
    }
    
    // Check for specific role match
    if ($_SESSION['role'] !== $required_role) {
        header("Location: /dashboard.php?error=Permission Denied");
        exit();
    }
}
?>