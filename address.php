<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
// 这里简单实现：只支持单一地址，直接编辑 users.address 字段
$stmt = $conn->prepare('SELECT address FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$address = $stmt->fetchColumn();
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_address = trim($_POST['address']);
    $stmt = $conn->prepare('UPDATE users SET address = ? WHERE id = ?');
    if ($stmt->execute([$new_address, $user_id])) {
        $success = 'Address updated!';
        $address = $new_address;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Address - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <h2 class="mb-4">Manage Address</h2>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Shipping Address</label>
            <textarea name="address" class="form-control" required rows="3"><?php echo htmlspecialchars($address); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save Address</button>
        <a href="profile.php" class="btn btn-secondary ms-2">Back</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 