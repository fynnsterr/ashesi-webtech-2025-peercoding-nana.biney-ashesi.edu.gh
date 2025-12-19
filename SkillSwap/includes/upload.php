<?php

/**
 * Upload file with validation
 * @param array $file $_FILES array element
 * @param string $type 'profile' or 'service'
 * @param int $userId User ID for profile images
 * @return array ['success' => bool, 'message' => string, 'filename' => string|null]
 */
function uploadFile($file, $type = 'service', $userId = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error', 'filename' => null];
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only images are allowed.', 'filename' => null];
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit', 'filename' => null];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid($type . '_', true) . '.' . $extension;
    
    // Determine upload directory
    $uploadDir = __DIR__ . '/../uploads/';
    if ($type === 'profile') {
        $uploadDir .= 'profiles/';
    } else {
        $uploadDir .= 'services/';
    }
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to save file', 'filename' => null];
    }
    
    // Return relative path from uploads directory
    $relativePath = 'uploads/' . ($type === 'profile' ? 'profiles/' : 'services/') . $filename;
    
    return ['success' => true, 'message' => 'File uploaded successfully', 'filename' => $relativePath];
}

/**
 * Delete file
 * @param string $filepath Relative path from project root
 * @return bool
 */
function deleteFile($filepath) {
    if (empty($filepath)) {
        return false;
    }
    
    $fullPath = __DIR__ . '/../' . $filepath;
    
    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    
    return false;
}

/**
 * Get file URL
 * @param string $filepath Relative path from project root
 * @return string
 */
function getFileUrl($filepath) {
    if (empty($filepath)) {
        return BASE_URL . '/assets/images/placeholder.png';
    }
    
    return BASE_URL . '/' . ltrim($filepath, '/');
}

