<?php
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test connection
    if ($conn) {
        echo "✅ Database connection successful<br>";
        
        // Check if payment_methods table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'payment_methods'");
        if ($stmt->rowCount() > 0) {
            echo "✅ payment_methods table exists<br>";
            
            // Show table structure
            $stmt = $conn->query("DESCRIBE payment_methods");
            echo "<h3>Table Structure:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . $row['Field'] . "</td>";
                echo "<td>" . $row['Type'] . "</td>";
                echo "<td>" . $row['Null'] . "</td>";
                echo "<td>" . $row['Key'] . "</td>";
                echo "<td>" . $row['Default'] . "</td>";
                echo "<td>" . $row['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Test insert
            echo "<h3>Testing Insert:</h3>";
            $test_stmt = $conn->prepare("
                INSERT INTO payment_methods (name, type, bank_code, account_number, account_name, 
                                          wallet_id, instructions, image, qrcode_image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $test_params = [
                'Test Bank', 'bank', 'TEST', '1234567890', 'Test Account', 
                null, 'Test instructions', 'test.jpg', 'test.jpg', 'active'
            ];
            
            if ($test_stmt->execute($test_params)) {
                echo "✅ Test insert successful. ID: " . $conn->lastInsertId() . "<br>";
                
                // Clean up test data
                $conn->exec("DELETE FROM payment_methods WHERE name = 'Test Bank'");
                echo "✅ Test data cleaned up<br>";
            } else {
                echo "❌ Test insert failed<br>";
                print_r($test_stmt->errorInfo());
            }
            
        } else {
            echo "❌ payment_methods table does not exist<br>";
        }
        
    } else {
        echo "❌ Database connection failed<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?> 