<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'], $_POST['quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $quantity = max(1, intval($_POST['quantity']));
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$quantity, $cart_id, $user_id]);
    header('Location: cart.php?message=Updated');
    exit();
}
header('Location: cart.php');
exit(); 