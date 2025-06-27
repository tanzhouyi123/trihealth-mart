<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize form variables
$name = '';
$type = '';
$bank_code = '';
$account_number = '';
$account_name = '';
$wallet_id = '';
$instructions = 'REF' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
$status = 'active';

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

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $bank_code = $_POST['bank_code'] ?? '';
    $account_number = trim($_POST['account_number'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $wallet_id = trim($_POST['wallet_id'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Generate reference code if empty
    if (empty($instructions)) {
        $instructions = 'REF' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }
    
    // Validation
    if (empty($name)) {
        $errors[] = "Payment method name is required";
    }
    
    if (empty($type) || !in_array($type, ['bank', 'ewallet'])) {
        $errors[] = "Please select a valid payment type";
    }
    
    if (empty($account_number)) {
        $errors[] = "Account number/Wallet ID is required";
    }
    
    if ($type === 'bank') {
        if (empty($bank_code)) {
            $errors[] = "Please select a bank";
        }
        if (empty($account_name)) {
            $errors[] = "Account name is required for bank transfers";
        }
    }
    
    if ($type === 'ewallet') {
        if (empty($wallet_id)) {
            $errors[] = "Wallet ID is required for e-wallet";
        }
    }
    
    // Handle image upload
    $image_path = '';
    $qrcode_image_path = '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = "Invalid image type. Only JPG, PNG and GIF are allowed.";
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Image size must be less than 5MB";
        } else {
            $upload_dir = '../uploads/payment_methods/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = 'uploads/payment_methods/' . $file_name;
                $qrcode_image_path = $image_path; // Same image for both fields
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Prepare the SQL statement
            $sql = "INSERT INTO payment_methods (name, type, bank_code, account_number, account_name, 
                                               wallet_id, instructions, image, qrcode_image, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            // Set values based on payment type
            $bank_code_value = ($type === 'bank') ? $bank_code : '';
            $account_name_value = ($type === 'bank') ? $account_name : '';
            $wallet_id_value = ($type === 'ewallet') ? $wallet_id : '';
            
            $params = [
                $name,
                $type,
                $bank_code_value,
                $account_number,
                $account_name_value,
                $wallet_id_value,
                $instructions,
                $image_path,
                $qrcode_image_path,
                $status
            ];
            
            if ($stmt->execute($params)) {
                $success = "Payment method added successfully!";
                // Clear form data after successful submission
                $name = $type = $bank_code = $account_number = $account_name = $wallet_id = $instructions = '';
                $status = 'active';
            } else {
                $errors[] = "Failed to add payment method to database";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment Method - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: #2c3e50;
            color: #ecf0f1;
            z-index: 1000;
        }
        .main-content {
            margin-left: 280px;
            padding: 2rem;
        }
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            border-radius: 5px;
            background-color: #f8f9fa;
            padding: 0.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #2c3e50;
        }
        .required {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
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
                    <a class="nav-link text-white" href="payment_methods.php">
                        <i class="fas fa-credit-card me-2"></i> Payment Methods
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-plus-circle me-2"></i>Add Payment Method</h2>
                    <a href="payment_methods.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Payment Methods
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:</h5>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="payment_form">
                    <!-- Payment Type -->
                    <div class="mb-3">
                        <label for="type" class="form-label">Payment Type <span class="required">*</span></label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Select Payment Type</option>
                            <option value="bank" <?php echo ($type === 'bank') ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="ewallet" <?php echo ($type === 'ewallet') ? 'selected' : ''; ?>>E-Wallet</option>
                        </select>
                    </div>

                    <!-- Payment Method Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Payment Method Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>

                    <!-- Bank Selection (for bank type) -->
                    <div class="mb-3 bank-fields" style="display: none;">
                        <label for="bank_code" class="form-label">Select Bank <span class="required">*</span></label>
                        <select class="form-select" id="bank_code" name="bank_code">
                            <option value="">Select Bank</option>
                            <?php foreach ($malaysian_banks as $code => $bank_name): ?>
                                <option value="<?php echo $code; ?>" 
                                        <?php echo ($bank_code === $code) ? 'selected' : ''; ?>>
                                    <?php echo $bank_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Account Number -->
                    <div class="mb-3">
                        <label for="account_number" class="form-label">Account Number / Wallet ID <span class="required">*</span></label>
                        <input type="text" class="form-control" id="account_number" name="account_number" 
                               value="<?php echo htmlspecialchars($account_number); ?>" required>
                    </div>

                    <!-- Account Name (for bank type) -->
                    <div class="mb-3 bank-fields" style="display: none;">
                        <label for="account_name" class="form-label">Account Name <span class="required">*</span></label>
                        <input type="text" class="form-control" id="account_name" name="account_name" 
                               value="<?php echo htmlspecialchars($account_name); ?>">
                    </div>

                    <!-- Wallet ID (for e-wallet type) -->
                    <div class="mb-3 ewallet-fields" style="display: none;">
                        <label for="wallet_id" class="form-label">Wallet ID <span class="required">*</span></label>
                        <input type="text" class="form-control" id="wallet_id" name="wallet_id" 
                               value="<?php echo htmlspecialchars($wallet_id); ?>">
                    </div>

                    <!-- Reference Code -->
                    <div class="mb-3">
                        <label for="instructions" class="form-label">Reference Code</label>
                        <input type="text" class="form-control" id="instructions" name="instructions" 
                               value="<?php echo htmlspecialchars($instructions); ?>" readonly>
                        <div class="form-text">This reference code will be used to track payments</div>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-3">
                        <label for="image" class="form-label">Payment Method Image</label>
                        <input type="file" class="form-control" id="image" name="image" 
                               accept="image/*" onchange="previewImage(this, 'preview')">
                        <div class="form-text" id="imageHelp">Upload QR code or payment method image</div>
                        <img id="preview" class="preview-image mt-2" style="display: none;">
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="status" class="form-label">Status <span class="required">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Add Payment Method
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect = document.getElementById('type');
        const bankFields = document.querySelectorAll('.bank-fields');
        const ewalletFields = document.querySelectorAll('.ewallet-fields');
        const imageHelp = document.getElementById('imageHelp');

        function updateFields() {
            const type = typeSelect.value;
            
            // Show/hide bank fields
            bankFields.forEach(field => {
                field.style.display = type === 'bank' ? 'block' : 'none';
            });
            
            // Show/hide e-wallet fields
            ewalletFields.forEach(field => {
                field.style.display = type === 'ewallet' ? 'block' : 'none';
            });
            
            // Update required attributes
            const bankCode = document.getElementById('bank_code');
            const accountName = document.getElementById('account_name');
            const walletId = document.getElementById('wallet_id');
            
            if (type === 'bank') {
                bankCode.required = true;
                accountName.required = true;
                walletId.required = false;
            } else if (type === 'ewallet') {
                bankCode.required = false;
                accountName.required = false;
                walletId.required = true;
            } else {
                bankCode.required = false;
                accountName.required = false;
                walletId.required = false;
            }
        }

        typeSelect.addEventListener('change', updateFields);
        updateFields(); // Initial update
    });

    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.style.display = 'none';
        }
    }
    </script>
</body>
</html> 