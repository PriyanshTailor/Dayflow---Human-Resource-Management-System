<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get total employees count
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_employees,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_employees
        FROM users WHERE role = 'employee'
    ");
    $employeeStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's attendance stats
    $stmt = $conn->query("
        SELECT 
            COUNT(DISTINCT a.user_id) as checked_in_today,
            SUM(CASE WHEN a.approval_status = 'Pending' THEN 1 ELSE 0 END) as pending_approvals
        FROM attendance a
        WHERE a.date = CURDATE()
    ");
    $attendanceToday = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current month attendance summary
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN approval_status = 'Approved' AND status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN approval_status = 'Approved' AND status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN approval_status = 'Pending' THEN 1 ELSE 0 END) as pending_count
        FROM attendance
        WHERE MONTH(date) = MONTH(CURRENT_DATE())
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $monthlyAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get leave requests summary
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
        FROM leave_requests
        WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $leaveStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get pending attendance approvals
    $stmt = $conn->query("
        SELECT 
            a.attendance_id,
            a.date,
            a.check_in_time,
            a.check_out_time,
            a.status,
            u.name as employee_name,
            u.employee_id,
            u.department
        FROM attendance a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.approval_status = 'Pending'
        ORDER BY a.date DESC
        LIMIT 10
    ");
    $pendingAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending leave approvals
    $stmt = $conn->query("
        SELECT 
            lr.leave_id,
            lr.leave_type,
            lr.start_date,
            lr.end_date,
            lr.reason,
            lr.created_at,
            u.name as employee_name,
            u.employee_id,
            u.department
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.user_id
        WHERE lr.status = 'Pending'
        ORDER BY lr.created_at DESC
        LIMIT 10
    ");
    $pendingLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent employees
    $stmt = $conn->query("
        SELECT 
            user_id,
            employee_id,
            name,
            email,
            department,
            position,
            date_of_joining,
            status
        FROM users
        WHERE role = 'employee'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get department-wise employee count
    $stmt = $conn->query("
        SELECT 
            department,
            COUNT(*) as employee_count
        FROM users
        WHERE role = 'employee' AND status = 'active'
        GROUP BY department
        ORDER BY employee_count DESC
    ");
    $departmentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build dashboard response
    $dashboard = [
        'employee_stats' => $employeeStats,
        'attendance_today' => $attendanceToday,
        'monthly_attendance' => $monthlyAttendance,
        'leave_stats' => $leaveStats,
        'pending_attendance' => $pendingAttendance,
        'pending_leaves' => $pendingLeaves,
        'recent_employees' => $recentEmployees,
        'department_stats' => $departmentStats
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
