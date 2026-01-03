<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    try {
        if ($action === 'list') {
            // Get all employees with their salary
            $stmt = $conn->prepare("
                SELECT 
                    u.user_id,
                    u.employee_id,
                    u.name,
                    u.email,
                    u.department,
                    u.position,
                    s.salary_id,
                    s.basic_salary,
                    s.allowances,
                    s.deductions,
                    s.net_salary,
                    s.currency,
                    s.effective_date
                FROM users u
                LEFT JOIN salary s ON u.user_id = s.user_id
                WHERE u.role = 'employee' AND u.status = 'active'
                ORDER BY u.name
            ");
            $stmt->execute();
            $payroll = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $payroll
            ]);
        } elseif ($action === 'view' && isset($_GET['user_id'])) {
            // Get salary details for specific employee
            $userId = $_GET['user_id'];
            
            $stmt = $conn->prepare("
                SELECT 
                    u.user_id,
                    u.employee_id,
                    u.name,
                    u.email,
                    u.department,
                    u.position,
                    s.salary_id,
                    s.basic_salary,
                    s.allowances,
                    s.deductions,
                    s.net_salary,
                    s.currency,
                    s.effective_date,
                    s.updated_at
                FROM users u
                LEFT JOIN salary s ON u.user_id = s.user_id
                WHERE u.user_id = :user_id
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch payroll data'
        ]);
        error_log($e->getMessage());
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'update') {
        $userId = $input['user_id'] ?? 0;
        $basicSalary = floatval($input['basic_salary'] ?? 0);
        $allowances = floatval($input['allowances'] ?? 0);
        $deductions = floatval($input['deductions'] ?? 0);
        $effectiveDate = $input['effective_date'] ?? date('Y-m-d');
        
        try {
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
                        deductions = :deductions,
                        effective_date = :effective_date
                    WHERE user_id = :user_id
                ");
            } else {
                // Insert new salary record
                $stmt = $conn->prepare("
                    INSERT INTO salary (user_id, basic_salary, allowances, deductions, effective_date)
                    VALUES (:user_id, :basic_salary, :allowances, :deductions, :effective_date)
                ");
            }
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':basic_salary', $basicSalary);
            $stmt->bindParam(':allowances', $allowances);
            $stmt->bindParam(':deductions', $deductions);
            $stmt->bindParam(':effective_date', $effectiveDate);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Payroll updated successfully'
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update payroll'
            ]);
            error_log($e->getMessage());
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
