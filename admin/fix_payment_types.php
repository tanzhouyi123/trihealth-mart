<?php
require_once '../config/database.php';

try {
    // Update e-wallet payment methods
    $stmt = $conn->prepare("
        UPDATE payment_methods 
        SET type = 'ewallet' 
        WHERE bank_code IN ('TOUCHNGO', 'GRABPAY', 'BOOST', 'SHOPEEPAY', 'MAE', 'BIGPAY', 'SETEL', 'LINK', 'GOPAY')
    ");
    $stmt->execute();
    
    // Update bank transfer payment methods
    $stmt = $conn->prepare("
        UPDATE payment_methods 
        SET type = 'bank' 
        WHERE bank_code IN ('MAYBANK', 'CIMB', 'PUBLIC', 'RHB', 'HONGLEONG', 'AMBANK', 'ALLIANCE', 'AFFIN', 'BSN', 'UOB')
    ");
    $stmt->execute();
    
    echo "Payment types updated successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 