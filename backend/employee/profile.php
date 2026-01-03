<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require employee login
requireEmployee();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get complete employee profile
    $stmt = $conn->prepare("
        SELECT 
            u.user_id,
            u.employee_id,
            u.name,
            u.email,
            u.phone,
            u.address,
            u.date_of_birth,
            u.gender,
            u.department,
            u.position,
            u.date_of_joining,
            u.profile_picture,
            u.status,
            u.created_at,
            s.basic_salary,
            s.allowances,
            s.deductions,
            s.net_salary,
            s.currency,
            s.effective_date
        FROM users u
        LEFT JOIN salary s ON u.user_id = s.user_id
        WHERE u.user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile) {
        // Don't send password hash to frontend
        unset($profile['password']);
        
        echo json_encode([
            'success' => true,
            'data' => $profile
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Profile not found'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch profile'
    ]);
    error_log($e->getMessage());
}
