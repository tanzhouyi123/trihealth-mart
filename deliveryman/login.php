<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'deliveryman') {
    if (isset($_SESSION['deliveryman_status']) && $_SESSION['deliveryman_status'] === 'active') {
        header('Location: dashboard.php');
    } else {
        header('Location: pending_approval.php');
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $error = '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $conn->prepare('SELECT * FROM delivery_men WHERE username = ?');
        $stmt->execute([$username]);
        $deliveryman = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($deliveryman && password_verify($password, $deliveryman['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $deliveryman['id'];
            $_SESSION['role'] = 'deliveryman';  // Changed from user_role to role
            $_SESSION['deliveryman_status'] = $deliveryman['status'];
            $_SESSION['username'] = $deliveryman['username'];

            if ($deliveryman['status'] === 'pending') {
                header('Location: pending_approval.php');
            } elseif ($deliveryman['status'] === 'inactive') {
                $error = 'Your account has been deactivated. Please contact admin.';
                session_destroy();
            } else {
                header('Location: dashboard.php');
            }
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Man Login - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Delivery Man Login</h2>
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <div class="text-center mt-3">
                        <p>Don't have an account? <a href="register.php">Register here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 