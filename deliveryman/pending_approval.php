<?php
session_start();
require_once '../includes/auth.php';

// If user is not logged in or is not a deliveryman, redirect to login
if (!isLoggedIn() || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'deliveryman') {
    header('Location: login.php');
    exit();
}

// If deliveryman is already approved, redirect to dashboard
if (isset($_SESSION['deliveryman_status']) && $_SESSION['deliveryman_status'] === 'active') {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-5 text-center">
                    <i class="fas fa-clock fa-4x text-warning mb-4"></i>
                    <h2 class="mb-4">Account Pending Approval</h2>
                    <p class="text-muted mb-4">
                        Your deliveryman account is currently pending approval by our administrators. 
                        We will review your application and get back to you soon.
                    </p>
                    <p class="text-muted">
                        You will be notified via email once your account is approved.
                    </p>
                    <a href="logout.php" class="btn btn-primary mt-3">Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 