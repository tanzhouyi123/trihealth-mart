<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = filter_input(INPUT_POST, 'site_name', FILTER_SANITIZE_STRING);
    $site_description = filter_input(INPUT_POST, 'site_description', FILTER_SANITIZE_STRING);
    $contact_email = filter_input(INPUT_POST, 'contact_email', FILTER_SANITIZE_EMAIL);
    $contact_phone = filter_input(INPUT_POST, 'contact_phone', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_STRING);
    $tax_rate = filter_input(INPUT_POST, 'tax_rate', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $delivery_fee = filter_input(INPUT_POST, 'delivery_fee', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    $errors = [];

    // Validate required fields
    if (empty($site_name)) {
        $errors[] = "Site name is required";
    }
    if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid contact email is required";
    }
    if (empty($contact_phone)) {
        $errors[] = "Contact phone is required";
    }
    if (empty($currency)) {
        $errors[] = "Currency is required";
    }
    if ($tax_rate === false || $tax_rate < 0) {
        $errors[] = "Valid tax rate is required";
    }
    if ($delivery_fee === false || $delivery_fee < 0) {
        $errors[] = "Valid delivery fee is required";
    }

    if (empty($errors)) {
        // Update settings
        $stmt = $conn->prepare("
            UPDATE settings SET 
                site_name = ?,
                site_description = ?,
                contact_email = ?,
                contact_phone = ?,
                address = ?,
                currency = ?,
                tax_rate = ?,
                delivery_fee = ?
            WHERE id = 1
        ");
        $stmt->bind_param("ssssssdd", $site_name, $site_description, $contact_email, $contact_phone, $address, $currency, $tax_rate, $delivery_fee);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Settings updated successfully";
            header("Location: settings.php");
            exit();
        } else {
            $errors[] = "Failed to update settings";
        }
    }
}

// Get current settings
$stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();

// If no settings exist, create default settings
if (!$settings) {
    $stmt = $conn->prepare("
        INSERT INTO settings (
            site_name, site_description, contact_email, contact_phone, 
            address, currency, tax_rate, delivery_fee
        ) VALUES (
            'TriHealth Mart', 'Your Health, Our Priority', 'contact@trihealth.com', 
            '+60123456789', '123 Health Street, Kuala Lumpur', 'MYR', 6.0, 5.00
        )
    ");
    $stmt->execute();
    
    // Get the newly created settings
    $stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - TriHealth Mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --header-height: 60px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --text-light: #ecf0f1;
            --text-dark: #2c3e50;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--primary-color);
            color: var(--text-light);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .admin-sidebar.collapsed {
            width: 60px;
        }

        .admin-sidebar .nav-link {
            padding: 0.8rem 1rem;
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .admin-sidebar .nav-link:hover {
            background: var(--secondary-color);
            color: var(--text-light);
        }

        .admin-sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .admin-sidebar.collapsed + .main-content {
            margin-left: 60px;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <div class="p-3">
            <h4 class="text-white mb-4">TriHealth Mart</h4>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link text-white" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="products.php">
                        <i class="fas fa-box me-2"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="categories.php">
                        <i class="fas fa-tags me-2"></i> Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="orders.php">
                        <i class="fas fa-shopping-cart me-2"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="delivery_men.php">
                        <i class="fas fa-truck me-2"></i> Delivery Men
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="payment_methods.php">
                        <i class="fas fa-credit-card me-2"></i> Payment Methods
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Settings</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php 
                                echo $_SESSION['success'];
                                unset($_SESSION['success']);
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" class="form-control" name="site_name" value="<?php echo $settings['site_name']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Site Description</label>
                                    <textarea class="form-control" name="site_description" rows="3"><?php echo $settings['site_description']; ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" name="contact_email" value="<?php echo $settings['contact_email']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" name="contact_phone" value="<?php echo $settings['contact_phone']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3"><?php echo $settings['address']; ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Currency</label>
                                    <input type="text" class="form-control" name="currency" value="<?php echo $settings['currency']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" name="tax_rate" value="<?php echo $settings['tax_rate']; ?>" step="0.01" min="0" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Delivery Fee</label>
                                    <input type="number" class="form-control" name="delivery_fee" value="<?php echo $settings['delivery_fee']; ?>" step="0.01" min="0" required>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('adminSidebar');
            const toggleBtn = document.createElement('button');
            toggleBtn.className = 'btn btn-light position-fixed';
            toggleBtn.style.cssText = 'top: 10px; left: 10px; z-index: 1001;';
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.appendChild(toggleBtn);

            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
            });

            // Handle mobile view
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html> 