<?php
require_once 'config/database.php';

echo "<h2>Test Payment Method Insert</h2>";

try {
    // Test bank payment method
    echo "<h3>Testing Bank Payment Method:</h3>";
    $stmt = $conn->prepare("
        INSERT INTO payment_methods (name, type, bank_code, account_number, account_name, 
                                  wallet_id, instructions, image, qrcode_image, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $params = [
        'Test Maybank', 'bank', 'MAYBANK', '1234567890', 'Test Account', 
        '', 'REF12345678', 'test.jpg', 'test.jpg', 'active'
    ];
    
    if ($stmt->execute($params)) {
        echo "✅ Bank payment method added successfully. ID: " . $conn->lastInsertId() . "<br>";
    } else {
        echo "❌ Bank payment method failed<br>";
        print_r($stmt->errorInfo());
    }
    
    // Test e-wallet payment method
    echo "<h3>Testing E-Wallet Payment Method:</h3>";
    $params = [
        'Test Touch n Go', 'ewallet', '', 'TNG123456', '', 
        'TNG123456', 'REF87654321', 'test.jpg', 'test.jpg', 'active'
    ];
    
    if ($stmt->execute($params)) {
        echo "✅ E-wallet payment method added successfully. ID: " . $conn->lastInsertId() . "<br>";
    } else {
        echo "❌ E-wallet payment method failed<br>";
        print_r($stmt->errorInfo());
    }
    
    // Clean up test data
    $conn->exec("DELETE FROM payment_methods WHERE name LIKE 'Test %'");
    echo "<br>✅ Test data cleaned up<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?> 