<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Get user ID to update
    $userId = $input['user_id'] ?? 0;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    try {
        // Check if employee exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND role = 'employee'");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }
        
        // Build update query dynamically based on provided fields
        $updateFields = [];
        $params = [':user_id' => $userId];

        // Flag to allow salary-only updates
        $hasSalaryFields = isset($input['basic_salary']) || isset($input['allowances']) || isset($input['deductions']);
        
        // Personal details
        if (isset($input['name'])) {
            $updateFields[] = "name = :name";
            $params[':name'] = trim($input['name']);
        }
        if (isset($input['email'])) {
            // Check email uniqueness
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id");
            $stmt->execute([':email' => $input['email'], ':user_id' => $userId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit;
            }
            $updateFields[] = "email = :email";
            $params[':email'] = trim($input['email']);
        }
        if (isset($input['phone'])) {
            $updateFields[] = "phone = :phone";
            $params[':phone'] = trim($input['phone']);
        }
        if (isset($input['address'])) {
            $updateFields[] = "address = :address";
            $params[':address'] = trim($input['address']);
        }
        if (isset($input['date_of_birth'])) {
            $updateFields[] = "date_of_birth = :date_of_birth";
            $params[':date_of_birth'] = $input['date_of_birth'];
        }
        if (isset($input['gender'])) {
            $updateFields[] = "gender = :gender";
            $params[':gender'] = $input['gender'];
        }
        
        // Job details
        if (isset($input['employee_id'])) {
            // Check employee_id uniqueness
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE employee_id = :employee_id AND user_id != :user_id");
            $stmt->execute([':employee_id' => $input['employee_id'], ':user_id' => $userId]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Employee ID already exists']);
                exit;
            }
            $updateFields[] = "employee_id = :employee_id";
            $params[':employee_id'] = trim($input['employee_id']);
        }
        if (isset($input['department'])) {
            $updateFields[] = "department = :department";
            $params[':department'] = trim($input['department']);
        }
        if (isset($input['position'])) {
            $updateFields[] = "position = :position";
            $params[':position'] = trim($input['position']);
        }
        if (isset($input['date_of_joining'])) {
            $updateFields[] = "date_of_joining = :date_of_joining";
            $params[':date_of_joining'] = $input['date_of_joining'];
        }
        if (isset($input['status'])) {
            $updateFields[] = "status = :status";
            $params[':status'] = $input['status'];
        }
        if (isset($input['profile_picture'])) {
            $updateFields[] = "profile_picture = :profile_picture";
            $params[':profile_picture'] = trim($input['profile_picture']);
        }
        
        // Password update (if provided)
        if (isset($input['password']) && !empty($input['password'])) {
            $updateFields[] = "password = :password";
            $params[':password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        
        // If no user fields AND no salary fields, nothing to do
        if (empty($updateFields) && !$hasSalaryFields) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }

        // Update user record only when user fields provided
        if (!empty($updateFields)) {
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = :user_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }
        
        // Update salary if provided
        if ($hasSalaryFields) {
            $basicSalary = floatval($input['basic_salary'] ?? 0);
            $allowances = floatval($input['allowances'] ?? 0);
            $deductions = floatval($input['deductions'] ?? 0);
            
            // Check if salary record exists
            $stmt = $conn->prepare("SELECT salary_id FROM salary WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Update existing salary
                $stmt = $conn->prepare("
                    UPDATE salary 
                    SET basic_salary = :basic_salary,
                        allowances = :allowances,
                        deductions = :deductions
                    WHERE user_id = :user_id
                ");
            } else {
                // Insert new salary record
                $stmt = $conn->prepare("
                    INSERT INTO salary (user_id, basic_salary, allowances, deductions)
                    VALUES (:user_id, :basic_salary, :allowances, :deductions)
                ");
            }
            
            $stmt->execute([
                ':user_id' => $userId,
                ':basic_salary' => $basicSalary,
                ':allowances' => $allowances,
                ':deductions' => $deductions
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee updated successfully'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update employee'
        ]);
        error_log($e->getMessage());
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
