<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Function to generate random reference code
function generateReferenceCode() {
    $prefix = 'THM';
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    return $prefix . $random;
}

// 获取购物车商品
$stmt = $conn->prepare('SELECT c.*, p.name, p.price, p.image FROM cart_items c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?');
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}
// 获取用户信息
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
// Get payment methods
$stmt = $conn->prepare("SELECT id, name, type, bank_code, account_number, account_name, instructions, qrcode_image FROM payment_methods WHERE status = 'active' ORDER BY type, name");
$stmt->execute();
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug output
error_log("Payment Methods: " . print_r($payment_methods, true));
$error = '';

// reference code 统一逻辑
if (!isset($_SESSION['checkout_reference_code'])) {
    // 保证唯一性
    do {
        $reference_code = generateReferenceCode();
        $stmt = $conn->prepare('SELECT id FROM orders WHERE reference_code = ?');
        $stmt->execute([$reference_code]);
    } while ($stmt->fetch());
    $_SESSION['checkout_reference_code'] = $reference_code;
}
$checkout_reference_code = $_SESSION['checkout_reference_code'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];
    if (empty($address) || empty($payment_method)) {
        $error = 'Please fill in all required fields.';
    } else {
        // 使用 session 中的 reference code
        $reference_code = $_SESSION['checkout_reference_code'];
        // 创建订单
        $conn->beginTransaction();
        $stmt = $conn->prepare('INSERT INTO orders (user_id, total_amount, status, delivery_address, payment_method, reference_code) VALUES (?, ?, "pending", ?, ?, ?)');
        $stmt->execute([$user_id, $total, $address, $payment_method, $reference_code]);
        $order_id = $conn->lastInsertId();
        // 插入订单商品
        $stmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
        foreach ($cart_items as $item) {
            $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
            // 扣减库存
            $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ?')->execute([$item['quantity'], $item['product_id']]);
        }
        // 清空购物车
        $conn->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$user_id]);
        $conn->commit();
        // 下单成功后清除 session 里的 reference code
        unset($_SESSION['checkout_reference_code']);
        header('Location: order_success.php?id=' . $order_id);
        exit();
    }
}
$selected_payment = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - TriHealth Mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
    <h2 class="mb-4">Checkout</h2>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <div class="row">
        <div class="col-md-7">
            <h5>Shipping Information</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="payment_method_select" class="form-label">Payment Method</label>
                    <select class="form-select" id="payment_method_select" name="payment_method" required>
                        <option value="">Select Payment Method</option>
                        <?php 
                        // Debug output
                        echo "<!-- Debug: Payment Methods -->\n";
                        foreach ($payment_methods as $pm) {
                            echo "<!-- Payment Method: " . htmlspecialchars($pm['name']) . 
                                 ", Type: " . htmlspecialchars($pm['type']) . 
                                 ", Image: " . htmlspecialchars($pm['qrcode_image']) . " -->\n";
                        }
                        ?>
                        <?php foreach ($payment_methods as $pm): ?>
                        <option value="<?php echo htmlspecialchars($pm['name']); ?>" 
                                data-type="<?php echo strtolower(htmlspecialchars($pm['type'])); ?>"
                                data-qr="<?php echo $pm['qrcode_image'] ? htmlspecialchars($pm['qrcode_image']) : ''; ?>"
                                data-bank="<?php echo htmlspecialchars($pm['bank_code']); ?>"
                                data-account="<?php echo htmlspecialchars($pm['account_number']); ?>"
                                data-account-name="<?php echo htmlspecialchars($pm['account_name']); ?>"
                                data-instructions="<?php echo htmlspecialchars($pm['instructions']); ?>"
                                <?php if($selected_payment==$pm['name'])echo 'selected'; ?>>
                            <?php echo htmlspecialchars($pm['name']); ?> (<?php echo strtoupper($pm['type']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="payment_details_box" class="mb-3" style="display:none;">
                    <div id="bank_details" style="display:none;">
                        <h6>Bank Transfer Details:</h6>
                        <div class="card">
                            <div class="card-body">
                                <p class="mb-1"><strong>Bank:</strong> <span id="bank_name"></span></p>
                                <p class="mb-1"><strong>Account Number:</strong> <span id="account_number"></span></p>
                                <p class="mb-1"><strong>Account Name:</strong> <span id="account_name"></span></p>
                                <p class="mb-1"><strong>Reference Code:</strong> <span id="bank_instructions" class="text-primary fw-bold"></span></p>
                                <p class="mb-1 text-danger">Please include this reference code in your payment description</p>
                            </div>
                        </div>
                    </div>
                    <div id="ewallet_details" style="display:none;">
                        <h6>Scan QR Code to Pay:</h6>
                        <div id="qr_container">
                            <img id="payment_qr_img" src="" style="max-width:200px;max-height:200px;" class="border rounded">
                        </div>
                        <p class="mt-2"><strong>Reference Code:</strong> <span id="ewallet_instructions" class="text-primary fw-bold"></span></p>
                        <p class="text-danger">Please include this reference code in your payment description</p>
                    </div>
                </div>
                <script>
                function showPaymentDetails() {
                    var sel = document.getElementById('payment_method_select');
                    var selectedOption = sel.options[sel.selectedIndex];
                    var type = selectedOption.getAttribute('data-type');
                    var detailsBox = document.getElementById('payment_details_box');
                    var bankDetails = document.getElementById('bank_details');
                    var ewalletDetails = document.getElementById('ewallet_details');
                    
                    console.log('Selected type:', type); // Debug log
                    console.log('Selected option:', selectedOption); // Debug log
                    console.log('All data attributes:', {
                        type: selectedOption.getAttribute('data-type'),
                        qr: selectedOption.getAttribute('data-qr'),
                        bank: selectedOption.getAttribute('data-bank'),
                        account: selectedOption.getAttribute('data-account'),
                        accountName: selectedOption.getAttribute('data-account-name'),
                        instructions: selectedOption.getAttribute('data-instructions')
                    });
                    
                    if (type) {
                        detailsBox.style.display = 'block';
                        
                        if (type === 'bank') {
                            bankDetails.style.display = 'block';
                            ewalletDetails.style.display = 'none';
                            
                            // Update bank details
                            document.getElementById('bank_name').textContent = selectedOption.getAttribute('data-bank');
                            document.getElementById('account_number').textContent = selectedOption.getAttribute('data-account');
                            document.getElementById('account_name').textContent = selectedOption.getAttribute('data-account-name');
                            // Display random reference code instead of static instructions
                            document.getElementById('bank_instructions').textContent = '<?php echo $checkout_reference_code; ?>';
                        } else if (type === 'ewallet') {
                            console.log('Showing e-wallet details'); // Debug log
                            bankDetails.style.display = 'none';
                            ewalletDetails.style.display = 'block';
                            
                            // Update e-wallet details
                            var qr = selectedOption.getAttribute('data-qr');
                            console.log('QR Image Path:', qr); // Debug log
                            
                            var img = document.getElementById('payment_qr_img');
                            if (qr) {
                                console.log('Setting image source to:', qr);
                                img.src = qr;
                                img.onerror = function() {
                                    console.error('Failed to load image:', qr);
                                    this.style.display = 'none';
                                };
                                img.onload = function() {
                                    console.log('Image loaded successfully');
                                    this.style.display = 'block';
                                };
                            } else {
                                console.log('No QR image path available');
                                img.style.display = 'none';
                            }
                            // Display random reference code instead of static instructions
                            document.getElementById('ewallet_instructions').textContent = '<?php echo $checkout_reference_code; ?>';
                        }
                    } else {
                        detailsBox.style.display = 'none';
                    }
                }

                // Add event listener for payment method change
                document.getElementById('payment_method_select').addEventListener('change', showPaymentDetails);
                
                // Call on page load
                document.addEventListener('DOMContentLoaded', showPaymentDetails);
                </script>
                <button type="submit" class="btn btn-primary btn-lg">Place Order</button>
            </form>
        </div>
        <div class="col-md-5">
            <h5>Order Summary</h5>
            <ul class="list-group mb-3">
                <?php foreach ($cart_items as $item): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <?php 
                        $image_path = '';
                        if ($item['image']) {
                            if (strpos($item['image'], 'uploads/') === 0) {
                                $image_path = $item['image'];
                            } elseif (strpos($item['image'], 'assets/') === 0) {
                                $image_path = $item['image'];
                            } else {
                                $image_path = "assets/images/products/" . $item['image'];
                            }
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($image_path); ?>" style="width:40px;" class="me-2">
                        <?php echo htmlspecialchars($item['name']); ?> x <?php echo $item['quantity']; ?>
                    </div>
                    <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center fw-bold">
                    Total <span>$<?php echo number_format($total, 2); ?></span>
                </li>
            </ul>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 