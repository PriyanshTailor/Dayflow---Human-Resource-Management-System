<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require login
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $userId = $_SESSION['user_id'];
    $file = $_FILES['profile_picture'];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload error']);
        exit;
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed']);
        exit;
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB']);
        exit;
    }
    
    try {
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/../../uploads/profile_pictures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save file']);
            exit;
        }
        
        // Update database with new profile picture path
        $db = new Database();
        $conn = $db->getConnection();
        
        $profilePicturePath = 'uploads/profile_pictures/' . $filename;
        
        $stmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE user_id = :user_id");
        $stmt->execute([
            ':profile_picture' => $profilePicturePath,
            ':user_id' => $userId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'profile_picture' => $profilePicturePath
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile picture'
        ]);
        error_log($e->getMessage());
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
