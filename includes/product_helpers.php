<?php
/**
 * Display a product image with fallback to placeholder
 * 
 * @param string $imagePath The path to the product image
 * @param string $altText Alternative text for the image
 * @param array $attributes Additional HTML attributes for the image tag
 * @return string HTML for the image or placeholder
 */
function displayProductImage($imagePath, $altText = 'Product Image', $attributes = []) {
    $defaultAttributes = [
        'class' => 'product-image',
        'style' => 'width: 200px; height: 200px; object-fit: cover;',
        'loading' => 'lazy',
        'onerror' => "this.onerror=null; this.src='/FARMLINK/assets/img/placeholder.jpg';"
    ];
    
    // Merge default attributes with custom ones
    $attributes = array_merge($defaultAttributes, $attributes);
    
    // Build attributes string
    $attrString = '';
    foreach ($attributes as $key => $value) {
        $attrString .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
    }
    
    // If no image path is provided, use placeholder
    if (empty($imagePath) || !file_exists($_SERVER['DOCUMENT_ROOT'] . parse_url($imagePath, PHP_URL_PATH))) {
        $imagePath = '/FARMLINK/assets/img/placeholder.jpg';
    }
    
    return '<img src="' . htmlspecialchars($imagePath, ENT_QUOTES) . '" alt="' . htmlspecialchars($altText) . '"' . $attrString . '>';
}

/**
 * Handle file upload for product images
 * 
 * @param array $file $_FILES array element
 * @param string $uploadDir Directory to upload to (relative to document root)
 * @return string|bool Path to the uploaded file or false on failure
 */
function handleImageUpload($file, $uploadDir = '/FARMLINK/uploads/products/') {
    $targetDir = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return false;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . $extension;
    $targetPath = $targetDir . $filename;
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $uploadDir . $filename;
    }
    
    return false;
}

/**
 * Delete a product image file
 * 
 * @param string $imagePath Path to the image file
 * @return bool True on success, false on failure
 */
function deleteProductImage($imagePath) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($imagePath, PHP_URL_PATH);
    
    // Don't delete the placeholder image
    if (strpos($fullPath, '/FARMLINK/assets/img/placeholder.jpg') !== false) {
        return true;
    }
    
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    
    return true; // If file doesn't exist, consider it deleted
}
