<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = intval($_POST['product_id']);
    $quantity = max(1, intval($_POST['quantity']));
    // 检查购物车是否已有该商品
    $stmt = $conn->prepare('SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$user_id, $product_id]);
    if ($cart = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // 更新数量
        $stmt = $conn->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?');
        $stmt->execute([$quantity, $cart['id']]);
    } else {
        // 新增
        $stmt = $conn->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $product_id, $quantity]);
    }
    header('Location: cart.php?message=Added to cart');
    exit();
}
header('Location: products.php');
exit(); 