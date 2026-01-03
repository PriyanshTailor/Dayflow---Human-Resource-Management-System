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
    
    // Get employee basic info
    $stmt = $conn->prepare("
        SELECT 
            user_id,
            employee_id,
            name,
            email,
            department,
            position,
            profile_picture
        FROM users
        WHERE user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's attendance
    $stmt = $conn->prepare("
        SELECT 
            attendance_id,
            check_in_time,
            check_out_time,
            working_hours,
            status,
            approval_status
        FROM attendance
        WHERE user_id = :user_id AND date = CURDATE()
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current month attendance summary
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN approval_status = 'Approved' AND status = 'Present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN approval_status = 'Approved' AND status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN approval_status = 'Approved' AND status = 'Half-day' THEN 1 ELSE 0 END) as half_days,
            SUM(CASE WHEN approval_status = 'Pending' THEN 1 ELSE 0 END) as pending_approvals,
            SUM(CASE WHEN approval_status = 'Approved' THEN working_hours ELSE 0 END) as total_hours
        FROM attendance
        WHERE user_id = :user_id 
        AND MONTH(date) = MONTH(CURRENT_DATE())
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $attendanceSummary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get leave summary
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
        FROM leave_requests
        WHERE user_id = :user_id
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $leaveSummary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent leave requests
    $stmt = $conn->prepare("
        SELECT 
            leave_id,
            leave_type,
            start_date,
            end_date,
            status,
            created_at
        FROM leave_requests
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $recentLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get salary information
    $stmt = $conn->prepare("
        SELECT 
            basic_salary,
            allowances,
            deductions,
            net_salary,
            currency
        FROM salary
        WHERE user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $salary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent notifications
    $stmt = $conn->prepare("
        SELECT 
            notification_id,
            message,
            type,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build dashboard response
    $dashboard = [
        'employee' => $employee,
        'today_attendance' => $todayAttendance ?: null,
        'attendance_summary' => $attendanceSummary,
        'leave_summary' => $leaveSummary,
        'recent_leaves' => $recentLeaves,
        'salary' => $salary ?: null,
        'notifications' => $notifications
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $dashboard
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch dashboard data'
    ]);
    error_log($e->getMessage());
}
