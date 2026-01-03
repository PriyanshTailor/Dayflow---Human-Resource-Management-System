<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['employee_id', 'name', 'email', 'department', 'position', 'date_of_joining'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            exit;
        }
    }
    
    $employeeId = trim($data['employee_id']);
    $name = trim($data['name']);
    $email = trim($data['email']);
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $department = trim($data['department']);
    $position = trim($data['position']);
    $dateOfJoining = $data['date_of_joining'];
    $dateOfBirth = $data['date_of_birth'] ?? null;
    
    // Generate random password
    $password = 'Welcome@123'; // Simple default password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if employee_id or email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = :employee_id OR email = :email");
        $stmt->bindParam(':employee_id', $employeeId);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Employee ID or Email already exists']);
            exit;
        }
        
        // Insert user
        $stmt = $conn->prepare("
            INSERT INTO users (employee_id, name, email, password, role, phone, address, department, position, date_of_joining, date_of_birth, status) 
            VALUES (:employee_id, :name, :email, :password, 'employee', :phone, :address, :department, :position, :date_of_joining, :date_of_birth, 'active')
        ");
        
        $stmt->bindParam(':employee_id', $employeeId);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':date_of_joining', $dateOfJoining);
        $stmt->bindParam(':date_of_birth', $dateOfBirth);
        $stmt->execute();
        
        $userId = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee added successfully! Default password: Welcome@123',
            'data' => [
                'user_id' => $userId,
                'employee_id' => $employeeId
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        error_log($e->getMessage());
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
