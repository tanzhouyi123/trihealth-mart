<?php
require_once 'config/database.php';
require_once 'includes/image_helper.php';

echo "<h1>Categories Test</h1>";

try {
    // Test database connection
    echo "<h2>Database Connection Test</h2>";
    $stmt = $conn->query("SELECT COUNT(*) FROM categories");
    $count = $stmt->fetchColumn();
    echo "<p>✅ Database connected successfully. Found {$count} categories.</p>";

    // Get all categories with product count
    echo "<h2>Current Categories</h2>";
    $stmt = $conn->query('
        SELECT c.*, 
               COUNT(p.id) as product_count,
               COALESCE(SUM(p.stock), 0) as total_stock
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = "active"
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($categories)) {
        echo "<p>No categories found in database.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th>";
        echo "<th>Name</th>";
        echo "<th>Description</th>";
        echo "<th>Image</th>";
        echo "<th>Status</th>";
        echo "<th>Products</th>";
        echo "<th>Total Stock</th>";
        echo "<th>Created</th>";
        echo "<th>Updated</th>";
        echo "</tr>";

        foreach ($categories as $category) {
            echo "<tr>";
            echo "<td>{$category['id']}</td>";
            echo "<td><strong>{$category['name']}</strong></td>";
            echo "<td>" . htmlspecialchars($category['description'] ?: 'No description') . "</td>";
            echo "<td>";
            if ($category['image']) {
                $image_path = getImagePath($category['image'], 'categories');
                echo "<img src='{$image_path}' style='width: 50px; height: 50px; object-fit: cover; border-radius: 5px;' onerror='this.style.display=\"none\"; this.nextSibling.style.display=\"inline\";'>";
                echo "<span style='display: none; color: red;'>Image not found</span>";
            } else {
                echo "<span style='color: gray;'>No image</span>";
            }
            echo "</td>";
            echo "<td><span style='color: " . ($category['status'] === 'active' ? 'green' : 'red') . ";'>{$category['status']}</span></td>";
            echo "<td>{$category['product_count']}</td>";
            echo "<td>" . number_format($category['total_stock']) . "</td>";
            echo "<td>" . date('Y-m-d H:i', strtotime($category['created_at'])) . "</td>";
            echo "<td>" . date('Y-m-d H:i', strtotime($category['updated_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test image helper function
    echo "<h2>Image Helper Test</h2>";
    $test_images = [
        'test.jpg',
        'uploads/categories/test.jpg',
        'assets/images/categories/test.jpg',
        ''
    ];

    foreach ($test_images as $test_image) {
        $result = getImagePath($test_image, 'categories');
        echo "<p>Input: '{$test_image}' → Output: '{$result}'</p>";
    }

    // Check upload directories
    echo "<h2>Upload Directories Test</h2>";
    $directories = [
        'uploads/categories',
        'assets/images/categories'
    ];

    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            echo "<p>✅ Directory '{$dir}' exists</p>";
            $files = glob($dir . '/*');
            echo "<p>Files in {$dir}: " . count($files) . "</p>";
        } else {
            echo "<p>❌ Directory '{$dir}' does not exist</p>";
        }
    }

    // Test statistics
    echo "<h2>Statistics Test</h2>";
    $stats = [
        'total_categories' => $conn->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'active_categories' => $conn->query('SELECT COUNT(*) FROM categories WHERE status = "active"')->fetchColumn(),
        'total_products' => $conn->query('SELECT COUNT(*) FROM products WHERE status = "active"')->fetchColumn(),
        'categories_with_products' => $conn->query('SELECT COUNT(DISTINCT category_id) FROM products WHERE status = "active"')->fetchColumn()
    ];

    echo "<ul>";
    foreach ($stats as $key => $value) {
        echo "<li><strong>" . ucwords(str_replace('_', ' ', $key)) . ":</strong> {$value}</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='admin/categories.php'>Go to Categories Management</a></p>";
echo "<p><a href='admin/add_category.php'>Add New Category</a></p>";
?> 