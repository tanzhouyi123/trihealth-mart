<?php
/**
 * Helper function to get the correct image path
 * @param string $image_path The image path from database
 * @param string $type The type of image (products, categories, payment_methods)
 * @return string The correct image path
 */
function getImagePath($image_path, $type = 'products') {
    if (empty($image_path)) {
        return '';
    }
    
    // If the path already contains uploads/ or assets/, use it as is
    if (strpos($image_path, 'uploads/') === 0 || strpos($image_path, 'assets/') === 0) {
        return $image_path;
    }
    
    // Otherwise, construct the path based on type
    switch ($type) {
        case 'products':
            return "assets/images/products/" . $image_path;
        case 'categories':
            return "assets/images/categories/" . $image_path;
        case 'payment_methods':
            return "uploads/payment_methods/" . $image_path;
        default:
            return $image_path;
    }
}

/**
 * Helper function to check if image exists and return fallback
 * @param string $image_path The image path
 * @param string $fallback_path Fallback image path
 * @return string The image path or fallback
 */
function getImagePathWithFallback($image_path, $fallback_path = 'assets/images/no-image.jpg') {
    $path = getImagePath($image_path);
    if (empty($path) || !file_exists($path)) {
        return $fallback_path;
    }
    return $path;
}
?> 