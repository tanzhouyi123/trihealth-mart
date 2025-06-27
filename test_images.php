<?php
require_once 'config/database.php';

echo "<h2>Image Path Test - User Portal</h2>";

// Test 1: Check payment methods images
echo "<h3>1. Payment Methods Images</h3>";
$stmt = $conn->query("SELECT name, qrcode_image FROM payment_methods WHERE status = 'active'");
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($payment_methods as $pm) {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>" . htmlspecialchars($pm['name']) . "</strong><br>";
    echo "Database path: " . htmlspecialchars($pm['qrcode_image']) . "<br>";
    
    if ($pm['qrcode_image']) {
        $full_path = $pm['qrcode_image'];
        if (file_exists($full_path)) {
            echo "✅ File exists: " . $full_path . "<br>";
            echo "<img src='" . htmlspecialchars($full_path) . "' style='max-width: 100px; max-height: 100px; border: 1px solid #ddd;'><br>";
        } else {
            echo "❌ File not found: " . $full_path . "<br>";
        }
    } else {
        echo "⚠️ No image path in database<br>";
    }
    echo "</div>";
}

// Test 2: Check product images
echo "<h3>2. Product Images</h3>";
$stmt = $conn->query("SELECT name, image FROM products LIMIT 5");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $product) {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>" . htmlspecialchars($product['name']) . "</strong><br>";
    echo "Database path: " . htmlspecialchars($product['image']) . "<br>";
    
    if ($product['image']) {
        // Handle different path formats
        if (strpos($product['image'], 'uploads/') === 0) {
            $full_path = $product['image'];
        } elseif (strpos($product['image'], 'assets/') === 0) {
            $full_path = $product['image'];
        } else {
            $full_path = "assets/images/products/" . $product['image'];
        }
        
        if (file_exists($full_path)) {
            echo "✅ File exists: " . $full_path . "<br>";
            echo "<img src='" . htmlspecialchars($full_path) . "' style='max-width: 100px; max-height: 100px; border: 1px solid #ddd;'><br>";
        } else {
            echo "❌ File not found: " . $full_path . "<br>";
        }
    } else {
        echo "⚠️ No image path in database<br>";
    }
    echo "</div>";
}

// Test 3: Check category images
echo "<h3>3. Category Images</h3>";
$stmt = $conn->query("SELECT name, image FROM categories LIMIT 5");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $category) {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>" . htmlspecialchars($category['name']) . "</strong><br>";
    echo "Database path: " . htmlspecialchars($category['image']) . "<br>";
    
    if ($category['image']) {
        // Handle different path formats
        if (strpos($category['image'], 'uploads/') === 0) {
            $full_path = $category['image'];
        } elseif (strpos($category['image'], 'assets/') === 0) {
            $full_path = $category['image'];
        } else {
            $full_path = "assets/images/categories/" . $category['image'];
        }
        
        if (file_exists($full_path)) {
            echo "✅ File exists: " . $full_path . "<br>";
            echo "<img src='" . htmlspecialchars($full_path) . "' style='max-width: 100px; max-height: 100px; border: 1px solid #ddd;'><br>";
        } else {
            echo "❌ File not found: " . $full_path . "<br>";
        }
    } else {
        echo "⚠️ No image path in database<br>";
    }
    echo "</div>";
}

echo "<h3>4. Directory Structure</h3>";
echo "<strong>Uploads directory:</strong><br>";
if (is_dir('uploads')) {
    echo "✅ uploads/ exists<br>";
    if (is_dir('uploads/payment_methods')) {
        echo "✅ uploads/payment_methods/ exists<br>";
        $files = scandir('uploads/payment_methods');
        echo "Files in payment_methods: " . (count($files) - 2) . " files<br>";
    } else {
        echo "❌ uploads/payment_methods/ not found<br>";
    }
} else {
    echo "❌ uploads/ not found<br>";
}

echo "<strong>Assets directory:</strong><br>";
if (is_dir('assets')) {
    echo "✅ assets/ exists<br>";
    if (is_dir('assets/images')) {
        echo "✅ assets/images/ exists<br>";
        if (is_dir('assets/images/products')) {
            echo "✅ assets/images/products/ exists<br>";
        } else {
            echo "❌ assets/images/products/ not found<br>";
        }
    } else {
        echo "❌ assets/images/ not found<br>";
    }
} else {
    echo "❌ assets/ not found<br>";
}
?> 