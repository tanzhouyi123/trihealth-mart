<?php
echo "<h1>Database Connection Test</h1>";

try {
    require_once 'config/database.php';
    echo "<p>✅ Database connection successful!</p>";
    
    // Test query
    $stmt = $conn->query("SELECT COUNT(*) FROM categories");
    $count = $stmt->fetchColumn();
    echo "<p>Categories in database: {$count}</p>";
    
    // Test categories table structure
    $stmt = $conn->query("DESCRIBE categories");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>Categories Table Structure:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 