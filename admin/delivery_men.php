<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle delivery man status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $delivery_id = filter_input(INPUT_POST, 'delivery_id', FILTER_VALIDATE_INT);
    
    if ($delivery_id) {
        if ($_POST['action'] === 'update') {
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
            $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
            $vehicle_type = filter_input(INPUT_POST, 'vehicle_type', FILTER_SANITIZE_STRING);
            $vehicle_number = filter_input(INPUT_POST, 'vehicle_number', FILTER_SANITIZE_STRING);
            $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
            
            // Handle image upload
            $image_path = $_POST['current_image'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($_FILES['image']['type'], $allowed_types)) {
                    $_SESSION['error'] = "Invalid image type. Only JPG, PNG and GIF are allowed.";
                } elseif ($_FILES['image']['size'] > $max_size) {
                    $_SESSION['error'] = "Image size must be less than 5MB";
                } else {
                    $upload_dir = '../uploads/delivery_men/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid() . '.' . $file_extension;
                    $target_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                        // Delete old image if exists
                        if ($_POST['current_image'] && file_exists('../' . $_POST['current_image'])) {
                            unlink('../' . $_POST['current_image']);
                        }
                        $image_path = 'uploads/delivery_men/' . $file_name;
                    } else {
                        $_SESSION['error'] = "Failed to upload image";
                    }
                }
            }

            if (!isset($_SESSION['error'])) {
                try {
                    $stmt = $conn->prepare("
                        UPDATE delivery_men 
                        SET username = ?, email = ?, phone = ?, address = ?, 
                            vehicle_type = ?, vehicle_number = ?, image_path = ?, status = ?
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([$username, $email, $phone, $address, $vehicle_type, 
                                      $vehicle_number, $image_path, $status, $delivery_id])) {
                        $_SESSION['success'] = "Delivery man updated successfully";
                    } else {
                        $_SESSION['error'] = "Failed to update delivery man";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Get all delivery men with their order statistics
$stmt = $conn->query("
    SELECT dm.*, 
           COUNT(DISTINCT o.id) as total_orders,
           COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END), 0) as total_earnings
    FROM delivery_men dm
    LEFT JOIN orders o ON dm.id = o.deliveryman_id
    GROUP BY dm.id
    ORDER BY dm.username
");
$delivery_men = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Men - TriHealth Mart</title>
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
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }

        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
        }

        .delivery-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 50%;
        }

        .vehicle-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
        </ul>
    </div>
</div>

<!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Delivery Men</h2>
                    <a href="add_delivery_man.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add Delivery Man
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
                                <th>Image</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Vehicle</th>
                                <th>Orders</th>
                                <th>Earnings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($delivery_men as $delivery): ?>
                            <tr>
                                    <td>
                                        <?php if ($delivery['image_path']): ?>
                                            <img src="../<?php echo $delivery['image_path']; ?>" 
                                                 class="delivery-image" alt="<?php echo htmlspecialchars($delivery['username']); ?>">
                                        <?php else: ?>
                                            <div class="delivery-image bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-user text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($delivery['username']); ?></td>
                                    <td>
                                        <div>
                                            <div><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($delivery['email']); ?></div>
                                            <div><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($delivery['phone']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info vehicle-badge">
                                            <?php echo htmlspecialchars($delivery['vehicle_type']); ?>
                                        </span>
                                        <div class="small text-muted"><?php echo htmlspecialchars($delivery['vehicle_number']); ?></div>
                                    </td>
                                    <td><?php echo $delivery['total_orders']; ?></td>
                                    <td>$<?php echo number_format($delivery['total_earnings'], 2); ?></td>
                                <td>
                                        <span class="badge bg-<?php echo $delivery['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($delivery['status']); ?>
                                    </span>
                                </td>
                                <td>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editDeliveryModal<?php echo $delivery['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteDeliveryMan(<?php echo $delivery['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                </td>
                            </tr>

                                <!-- Edit Delivery Man Modal -->
                                <div class="modal fade" id="editDeliveryModal<?php echo $delivery['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Delivery Man</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" enctype="multipart/form-data">
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                                                    <input type="hidden" name="current_image" value="<?php echo $delivery['image_path']; ?>">

                                                    <div class="mb-3">
                                                        <label for="username" class="form-label">Username</label>
                                                        <input type="text" class="form-control" id="username" name="username" 
                                                               value="<?php echo htmlspecialchars($delivery['username']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="email" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="email" name="email" 
                                                               value="<?php echo htmlspecialchars($delivery['email']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="phone" class="form-label">Phone Number</label>
                                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                                               value="<?php echo htmlspecialchars($delivery['phone']); ?>" required>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="address" class="form-label">Address</label>
                                                        <textarea class="form-control" id="address" name="address" 
                                                                  rows="2" required><?php echo htmlspecialchars($delivery['address']); ?></textarea>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                                                                <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                                                    <option value="Motorcycle" <?php echo $delivery['vehicle_type'] === 'Motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                                                    <option value="Car" <?php echo $delivery['vehicle_type'] === 'Car' ? 'selected' : ''; ?>>Car</option>
                                                                    <option value="Van" <?php echo $delivery['vehicle_type'] === 'Van' ? 'selected' : ''; ?>>Van</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="vehicle_number" class="form-label">Vehicle Number</label>
                                                                <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" 
                                                                       value="<?php echo htmlspecialchars($delivery['vehicle_number']); ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="image" class="form-label">Profile Image</label>
                                                        <?php if ($delivery['image_path']): ?>
                                                            <div class="mb-2">
                                                                <img src="../<?php echo $delivery['image_path']; ?>" 
                                                                     class="preview-image" alt="Current Image">
                                                            </div>
                                                        <?php endif; ?>
                                                        <input type="file" class="form-control" id="image" name="image" 
                                                               accept="image/*" onchange="previewImage(this, 'preview<?php echo $delivery['id']; ?>')">
                                                        <div class="form-text">Leave empty to keep current image</div>
                                                        <img id="preview<?php echo $delivery['id']; ?>" class="preview-image mt-2" style="display: none;">
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="status" class="form-label">Status</label>
                                                        <select class="form-select" id="status" name="status" required>
                                                            <option value="active" <?php echo $delivery['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="inactive" <?php echo $delivery['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update Delivery Man</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
}
                reader.readAsDataURL(input.files[0]);
    }
}

function deleteDeliveryMan(id) {
    if (confirm('Are you sure you want to delete this delivery man? This action cannot be undone.')) {
        // Create form data
        const formData = new FormData();
        formData.append('delivery_id', id);

        // Send AJAX request
        fetch('delete_delivery_man.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload(); // Reload the page to update the table
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the delivery man');
        });
    }
}
</script>
</body>
</html> 