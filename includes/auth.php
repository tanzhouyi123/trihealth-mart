<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is an approved deliveryman
function isApprovedDeliveryman() {
    return isset($_SESSION['role']) && 
           $_SESSION['role'] === 'deliveryman' && 
           isset($_SESSION['deliveryman_status']) && 
           $_SESSION['deliveryman_status'] === 'active';
}

// Require login for protected pages
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Require admin access for admin pages
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

// Require approved deliveryman access
function requireApprovedDeliveryman() {
    if (!isApprovedDeliveryman()) {
        header('Location: pending_approval.php');
        exit();
    }
} 