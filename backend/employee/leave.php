<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require employee login
requireEmployee();

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get employee's leave requests
    try {
        $stmt = $conn->prepare("
            SELECT 
                lr.leave_id,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.reason,
                lr.status,
                lr.admin_comment,
                lr.created_at,
                approver.name as approved_by_name
            FROM leave_requests lr
            LEFT JOIN users approver ON lr.approved_by = approver.user_id
            WHERE lr.user_id = :user_id
            ORDER BY lr.created_at DESC
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $leaves
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch leave requests'
        ]);
        error_log($e->getMessage());
    }
    
} elseif ($method === 'POST') {
    // Apply for leave
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $leaveType = $input['leave_type'] ?? '';
    $startDate = $input['start_date'] ?? '';
    $endDate = $input['end_date'] ?? '';
    $reason = trim($input['reason'] ?? '');
    
    if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required (leave_type, start_date, end_date, reason)'
        ]);
        exit;
    }
    
    // Validate leave type
    $validLeaveTypes = ['Paid', 'Sick', 'Unpaid'];
    if (!in_array($leaveType, $validLeaveTypes)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid leave type. Must be Paid, Sick, or Unpaid'
        ]);
        exit;
    }
    
    // Validate dates
    if (strtotime($startDate) > strtotime($endDate)) {
        echo json_encode([
            'success' => false,
            'message' => 'End date must be after start date'
        ]);
        exit;
    }
    
    try {
        // Check for overlapping leave requests
        $stmt = $conn->prepare("
            SELECT leave_id FROM leave_requests
            WHERE user_id = :user_id
            AND status IN ('Pending', 'Approved')
            AND (
                (start_date <= :end_date AND end_date >= :start_date)
            )
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'You already have a leave request for this period'
            ]);
            exit;
        }
        
        // Create leave request
        $stmt = $conn->prepare("
            INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status)
            VALUES (:user_id, :leave_type, :start_date, :end_date, :reason, 'Pending')
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':leave_type' => $leaveType,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':reason' => $reason
        ]);
        
        $leaveId = $conn->lastInsertId();
        
        // Get employee name
        $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify all admins
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $message = "{$employee['name']} applied for {$leaveType} leave from {$startDate} to {$endDate}";
        $notifyStmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, type, related_id)
            VALUES (:user_id, :message, 'leave', :related_id)
        ");
        
        foreach ($admins as $admin) {
            $notifyStmt->execute([
                ':user_id' => $admin['user_id'],
                ':message' => $message,
                ':related_id' => $leaveId
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'leave_id' => $leaveId
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to submit leave request'
        ]);
        error_log($e->getMessage());
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
