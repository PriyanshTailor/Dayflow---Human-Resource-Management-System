<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if requesting individual employee by ID
    if (isset($_GET['id'])) {
        $userId = $_GET['id'];
        
        $stmt = $conn->prepare("
            SELECT 
                u.user_id,
                u.employee_id,
                u.name as full_name,
                u.email,
                u.phone,
                u.department,
                u.position as role,
                u.date_of_joining as joining_date,
                u.status,
                u.profile_picture,
                s.basic_salary,
                s.allowances,
                s.deductions,
                s.net_salary
            FROM users u
            LEFT JOIN salary s ON u.user_id = s.user_id
            WHERE u.user_id = :user_id
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            // Get attendance stats
            $stmt = $conn->prepare("
                SELECT COUNT(*) as working_days
                FROM attendance
                WHERE user_id = :user_id AND status = 'Present'
                AND YEAR(date) = YEAR(CURRENT_DATE())
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            $employee['working_days'] = $attendance['working_days'] ?? 0;
            $employee['working_hours'] = ($employee['working_days'] ?? 0) * 8;
            
            // Get leave balance
            $stmt = $conn->prepare("
                SELECT COUNT(*) as leaves_taken
                FROM leave_requests
                WHERE user_id = :user_id AND status = 'Approved'
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $leaves = $stmt->fetch(PDO::FETCH_ASSOC);
            $employee['leaves_left'] = 20 - ($leaves['leaves_taken'] ?? 0);
            
            // Get today's attendance status
            $stmt = $conn->prepare("
                SELECT status FROM attendance
                WHERE user_id = :user_id AND date = CURDATE()
                LIMIT 1
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $todayStatus = $stmt->fetch(PDO::FETCH_ASSOC);
            $employee['attendance_status'] = $todayStatus ? strtolower($todayStatus['status']) : 'absent';
            
            echo json_encode([
                'success' => true,
                'employee' => $employee
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Employee not found'
            ]);
        }
    } else {
        // Get all employees
        $stmt = $conn->prepare("
            SELECT 
                u.user_id,
                u.employee_id,
                u.name as full_name,
                u.email,
                u.phone,
                u.department,
                u.position,
                u.date_of_joining,
                u.status,
                u.profile_picture,
                s.basic_salary,
                s.net_salary,
                (SELECT 'present' FROM attendance WHERE user_id = u.user_id AND date = CURDATE() LIMIT 1) as attendance_status
            FROM users u
            LEFT JOIN salary s ON u.user_id = s.user_id
            WHERE u.role = 'employee'
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set default attendance status if null
        foreach ($employees as &$emp) {
            if ($emp['attendance_status'] === null) {
                $emp['attendance_status'] = 'absent';
            }
        }
        
        echo json_encode([
            'success' => true,
            'employees' => $employees
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log($e->getMessage());
}
