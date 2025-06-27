<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Malaysian banks list
$malaysian_banks = [
    'MAYBANK' => 'Maybank',
    'CIMB' => 'CIMB Bank',
    'PUBLIC' => 'Public Bank',
    'RHB' => 'RHB Bank',
    'HONGLEONG' => 'Hong Leong Bank',
    'AMBANK' => 'AmBank',
    'ALLIANCE' => 'Alliance Bank',
    'AFFIN' => 'Affin Bank',
    'BSN' => 'Bank Simpanan Nasional',
    'UOB' => 'United Overseas Bank'
];

// E-wallet options
$e_wallets = [
    'TOUCHNGO' => 'Touch \'n Go eWallet',
    'GRABPAY' => 'GrabPay',
    'BOOST' => 'Boost',
    'SHOPEEPAY' => 'ShopeePay',
    'MAE' => 'MAE by Maybank',
    'BIGPAY' => 'BigPay',
    'SETEL' => 'Setel',
    'LINK' => 'Link',
    'GOPAY' => 'GoPayz'
];

// Handle payment method status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
    
    if ($payment_id) {
        if ($_POST['action'] === 'update') {
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
            $bank_code = $type === 'bank' ? filter_input(INPUT_POST, 'bank_code', FILTER_SANITIZE_STRING) : filter_input(INPUT_POST, 'ewallet_code', FILTER_SANITIZE_STRING);
            $account_number = filter_input(INPUT_POST, 'account_number', FILTER_SANITIZE_STRING);
            $account_name = filter_input(INPUT_POST, 'account_name', FILTER_SANITIZE_STRING);
            $instructions = filter_input(INPUT_POST, 'instructions', FILTER_SANITIZE_STRING);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
            
            // Handle image upload
            $qrcode_image = isset($_POST['current_qrcode']) ? $_POST['current_qrcode'] : '';
            if (isset($_FILES['qrcode']) && $_FILES['qrcode']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($_FILES['image']['type'], $allowed_types)) {
                    $_SESSION['error'] = "Invalid image type. Only JPG, PNG and GIF are allowed.";
                } elseif ($_FILES['image']['size'] > $max_size) {
                    $_SESSION['error'] = "Image size must be less than 5MB";
                } else {
                    $upload_dir = '../uploads/payment_methods/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '.' . $file_extension;
                    $target_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                        // Delete old image if exists
                        if ($qrcode_image && file_exists('../' . $qrcode_image)) {
                            unlink('../' . $qrcode_image);
                        }
                        $qrcode_image = 'uploads/payment_methods/' . $file_name;
                    } else {
                        $_SESSION['error'] = "Failed to upload image";
                    }
                }
            }

            if (!isset($_SESSION['error'])) {
                try {
                    $stmt = $conn->prepare("
                        UPDATE payment_methods 
                        SET name = ?, type = ?, bank_code = ?, account_number = ?, 
                            account_name = ?, instructions = ?, qrcode_image = ?, status = ?
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([$name, $type, $bank_code, $account_number, $account_name, 
                                      $instructions, $qrcode_image, $status, $payment_id])) {
                        $_SESSION['success'] = "Payment method updated successfully";
                    } else {
                        $_SESSION['error'] = "Failed to update payment method";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            try {
                // Get the payment method to check if it has an image
                $stmt = $conn->prepare("SELECT qrcode_image FROM payment_methods WHERE id = ?");
                $stmt->execute([$payment_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);

                // Delete the payment method
                $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ?");
                if ($stmt->execute([$payment_id])) {
                    // Delete the image file if it exists
                    if ($payment['qrcode_image'] && file_exists('../' . $payment['qrcode_image'])) {
                        unlink('../' . $payment['qrcode_image']);
                    }
                    $_SESSION['success'] = "Payment method deleted successfully";
                } else {
                    $_SESSION['error'] = "Failed to delete payment method";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get all payment methods
$stmt = $conn->query("SELECT * FROM payment_methods ORDER BY type, name");
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Methods - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
        }

        .payment-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 5px;
            background-color: #f8f9fa;
            padding: 0.5rem;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            border-radius: 5px;
            background-color: #f8f9fa;
            padding: 0.5rem;
        }

        .type-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="p-3">
            <h5 class="text-white mb-0">Admin Panel</h5>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="index.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a class="nav-link" href="products.php">
                <i class="fas fa-box"></i> Products
            </a>
            <a class="nav-link" href="categories.php">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a class="nav-link" href="orders.php">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
            <a class="nav-link active" href="payment_methods.php">
                <i class="fas fa-credit-card"></i> Payment Methods
            </a>
            <a class="nav-link" href="users.php">
                <i class="fas fa-users"></i> Users
            </a>
            <a class="nav-link" href="settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Payment Methods</h2>
                <a href="add_payment_method.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Payment Method
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Account Details</th>
                            <th>QR Code</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_methods as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['name']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $payment['type'] === 'bank' ? 'primary' : 'success'; ?> type-badge">
                                    <?php echo ucfirst($payment['type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($payment['type'] === 'bank'): ?>
                                    <strong>Bank:</strong> <?php echo htmlspecialchars($payment['bank_code']); ?><br>
                                    <strong>Account:</strong> <?php echo htmlspecialchars($payment['account_number']); ?><br>
                                    <strong>Name:</strong> <?php echo htmlspecialchars($payment['account_name']); ?>
                                <?php else: ?>
                                    <strong>Wallet ID:</strong> <?php echo htmlspecialchars($payment['wallet_id']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($payment['qrcode_image']): ?>
                                    <img src="../<?php echo htmlspecialchars($payment['qrcode_image']); ?>" 
                                         alt="QR Code" class="payment-image">
                                <?php else: ?>
                                    <span class="text-muted">No QR Code</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $payment['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editPaymentMethodModal<?php echo $payment['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deletePaymentMethodModal<?php echo $payment['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editPaymentMethodModal<?php echo $payment['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Payment Method</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="" enctype="multipart/form-data">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <input type="hidden" name="current_qrcode" value="<?php echo htmlspecialchars($payment['qrcode_image']); ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Name</label>
                                                <input type="text" class="form-control" name="name" 
                                                       value="<?php echo htmlspecialchars($payment['name']); ?>" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Type</label>
                                                <select class="form-select" name="type" required>
                                                    <option value="bank" <?php echo $payment['type'] === 'bank' ? 'selected' : ''; ?>>Bank</option>
                                                    <option value="ewallet" <?php echo $payment['type'] === 'ewallet' ? 'selected' : ''; ?>>E-Wallet</option>
                                                </select>
                                            </div>

                                            <div class="mb-3 bank-fields" style="display: <?php echo $payment['type'] === 'bank' ? 'block' : 'none'; ?>">
                                                <label class="form-label">Bank</label>
                                                <select class="form-select" name="bank_code">
                                                    <?php foreach ($malaysian_banks as $code => $name): ?>
                                                        <option value="<?php echo $code; ?>" 
                                                                <?php echo $payment['bank_code'] === $code ? 'selected' : ''; ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3 bank-fields" style="display: <?php echo $payment['type'] === 'bank' ? 'block' : 'none'; ?>">
                                                <label class="form-label">Account Number</label>
                                                <input type="text" class="form-control" name="account_number" 
                                                       value="<?php echo htmlspecialchars($payment['account_number']); ?>">
                                            </div>

                                            <div class="mb-3 bank-fields" style="display: <?php echo $payment['type'] === 'bank' ? 'block' : 'none'; ?>">
                                                <label class="form-label">Account Name</label>
                                                <input type="text" class="form-control" name="account_name" 
                                                       value="<?php echo htmlspecialchars($payment['account_name']); ?>">
                                            </div>

                                            <div class="mb-3 ewallet-fields" style="display: <?php echo $payment['type'] === 'ewallet' ? 'block' : 'none'; ?>">
                                                <label class="form-label">E-Wallet</label>
                                                <select class="form-select" name="ewallet_code">
                                                    <?php foreach ($e_wallets as $code => $name): ?>
                                                        <option value="<?php echo $code; ?>" 
                                                                <?php echo $payment['bank_code'] === $code ? 'selected' : ''; ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Instructions</label>
                                                <textarea class="form-control" name="instructions" rows="3"><?php echo htmlspecialchars($payment['instructions']); ?></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">QR Code Image</label>
                                                <?php if ($payment['qrcode_image']): ?>
                                                    <div class="mb-2">
                                                        <img src="../<?php echo htmlspecialchars($payment['qrcode_image']); ?>" 
                                                             alt="Current QR Code" class="preview-image">
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" class="form-control" name="qrcode" accept="image/*">
                                                <div class="form-text">Leave empty to keep current image</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status" required>
                                                    <option value="active" <?php echo $payment['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $payment['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deletePaymentMethodModal<?php echo $payment['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete Payment Method</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete this payment method?</p>
                                        <p class="text-danger">This action cannot be undone.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide fields based on payment type
        document.querySelectorAll('select[name="type"]').forEach(select => {
            select.addEventListener('change', function() {
                const isBank = this.value === 'bank';
                const bankFields = this.closest('form').querySelectorAll('.bank-fields');
                const ewalletFields = this.closest('form').querySelectorAll('.ewallet-fields');
                
                bankFields.forEach(field => field.style.display = isBank ? 'block' : 'none');
                ewalletFields.forEach(field => field.style.display = isBank ? 'none' : 'block');
            });
        });
    </script>
</body>
</html> 