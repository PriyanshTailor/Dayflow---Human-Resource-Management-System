<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require employee login
requireEmployee();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Employees can only update limited fields
        $updateFields = [];
        $params = [':user_id' => $userId];
        
        // Allowed fields for employee self-update
        if (isset($input['phone'])) {
            $updateFields[] = "phone = :phone";
            $params[':phone'] = trim($input['phone']);
        }
        if (isset($input['address'])) {
            $updateFields[] = "address = :address";
            $params[':address'] = trim($input['address']);
        }
        if (isset($input['profile_picture'])) {
            $updateFields[] = "profile_picture = :profile_picture";
            $params[':profile_picture'] = trim($input['profile_picture']);
        }
        
        if (empty($updateFields)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }
        
        // Update user record
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = :user_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile'
        ]);
        error_log($e->getMessage());
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
