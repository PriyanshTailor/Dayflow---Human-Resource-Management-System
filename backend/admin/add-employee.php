<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email.php';

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
    $basicSalary = floatval($data['basic_salary'] ?? 0);
    $allowances = floatval($data['allowances'] ?? 0);
    $deductions = floatval($data['deductions'] ?? 0);
    
    // Use default password for all employees on first login
    $password = 'Password@123';
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
        
        // Insert salary record if provided
        if ($basicSalary > 0) {
            $stmt = $conn->prepare("
                INSERT INTO salary (user_id, basic_salary, allowances, deductions, effective_date) 
                VALUES (:user_id, :basic_salary, :allowances, :deductions, :effective_date)
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':basic_salary', $basicSalary);
            $stmt->bindParam(':allowances', $allowances);
            $stmt->bindParam(':deductions', $deductions);
            $stmt->bindParam(':effective_date', $dateOfJoining);
            $stmt->execute();
        }
        
        // Send welcome email
        $emailService = new EmailService();
        $employeeData = [
            'employee_id' => $employeeId,
            'name' => $name,
            'email' => $email,
            'department' => $department,
            'position' => $position
        ];
        
        $emailSent = $emailService->sendWelcomeEmail($employeeData, $password);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee added successfully' . ($emailSent ? '. Welcome email sent.' : '. Email sending failed.'),
            'data' => [
                'user_id' => $userId,
                'employee_id' => $employeeId,
                'default_password' => $password,
                'email' => $email,
                'email_sent' => $emailSent,
                'login_note' => 'Employee can login with email and default password. Password should be changed on first login.'
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred'
        ]);
        error_log($e->getMessage());
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
