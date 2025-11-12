<?php
/**
 * FARMLINK Image Helper
 * Provides consistent image path handling across the application
 */

class ImageHelper {
    
    /**
     * Normalize image path for consistent display
     * Handles various path formats stored in database
     * 
     * @param string $imagePath The image path from database
     * @param string $type The type of image ('products', 'profiles')
     * @return string Normalized web URL path
     */
    public static function normalizeImagePath($imagePath, $type = 'products') {
        if (empty($imagePath)) {
            return '';
        }
        
        $imageValue = trim($imagePath);
        $base = defined('BASE_URL') ? BASE_URL : '';
        
        // Handle different path formats
        if (strpos($imageValue, 'http') === 0) {
            // Full URL - use as is
            return $imageValue;
        } elseif (strpos($imageValue, $base . '/') === 0 || strpos($imageValue, '/FARMLINK/') === 0) {
            // Already has site prefix - use as is
            return $imageValue;
        } elseif (strpos($imageValue, "uploads/{$type}/") === 0) {
            // Relative path starting with uploads/type/
            return $base . '/' . $imageValue;
        } elseif (strpos($imageValue, '/') === 0) {
            // Starts with / but no FARMLINK prefix
            return $base . $imageValue;
        } else {
            // Just filename - add full path
            return $base . "/uploads/{$type}/" . basename($imageValue);
        }
    }
    
    /**
     * Get product image URL with fallback
     * 
     * @param string $imagePath The image path from database
     * @return string Product image URL or placeholder
     */
    public static function getProductImageUrl($imagePath) {
        $normalizedPath = self::normalizeImagePath($imagePath, 'products');
        $base = defined('BASE_URL') ? BASE_URL : '';
        return !empty($normalizedPath) ? $normalizedPath : $base . '/assets/img/product-placeholder.svg';
    }
    
    /**
     * Get profile picture URL with fallback
     * 
     * @param string $imagePath The image path from database
     * @return string Profile picture URL or default avatar
     */
    public static function getProfilePictureUrl($imagePath) {
        $normalizedPath = self::normalizeImagePath($imagePath, 'profiles');
        $base = defined('BASE_URL') ? BASE_URL : '';
        return !empty($normalizedPath) ? $normalizedPath : $base . '/assets/img/default-avatar.png';
    }
    
    /**
     * Generate HTML img tag with proper error handling
     * 
     * @param string $imagePath The image path from database
     * @param string $alt Alt text for the image
     * @param string $class CSS class for the image
     * @param string $type Image type ('products', 'profiles')
     * @param array $attributes Additional HTML attributes
     * @return string HTML img tag
     */
    public static function generateImageTag($imagePath, $alt, $class = '', $type = 'products', $attributes = []) {
        $imageUrl = self::normalizeImagePath($imagePath, $type);
        $base = defined('BASE_URL') ? BASE_URL : '';
        $fallbackUrl = $type === 'profiles' ? ($base . '/assets/img/default-avatar.png') : ($base . '/assets/img/product-placeholder.svg');
        
        $attrs = [];
        $attrs[] = 'src="' . htmlspecialchars($imageUrl) . '"';
        $attrs[] = 'alt="' . htmlspecialchars($alt) . '"';
        
        if (!empty($class)) {
            $attrs[] = 'class="' . htmlspecialchars($class) . '"';
        }
        
        $attrs[] = 'onerror="this.src=\'' . $fallbackUrl . '\'"';
        
        // Add additional attributes
        foreach ($attributes as $key => $value) {
            $attrs[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }
        
        return '<img ' . implode(' ', $attrs) . '>';
    }
}
?>
