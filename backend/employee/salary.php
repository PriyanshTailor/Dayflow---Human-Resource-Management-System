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
    
    // Get salary details
    $stmt = $conn->prepare("
        SELECT 
            s.basic_salary,
            s.allowances,
            s.deductions,
            s.net_salary,
            s.currency,
            s.effective_date,
            u.name,
            u.employee_id,
            u.department,
            u.position
        FROM salary s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $salary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($salary) {
        echo json_encode([
            'success' => true,
            'data' => $salary
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No salary information available'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch salary information'
    ]);
    error_log($e->getMessage());
}
