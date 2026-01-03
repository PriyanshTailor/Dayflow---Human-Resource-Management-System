<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email.php';

// Require admin login
requireAdmin();

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'all';
    
    try {
        if ($action === 'pending') {
            // Get only pending leave requests
            $stmt = $conn->prepare("
                SELECT 
                    lr.*,
                    u.name as employee_name,
                    u.employee_id,
                    u.department,
                    u.email
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.user_id
                WHERE lr.status = 'Pending'
                ORDER BY lr.created_at DESC
            ");
        } else {
            // Get all leave requests
            $stmt = $conn->prepare("
                SELECT 
                    lr.*,
                    u.name as employee_name,
                    u.employee_id,
                    u.department,
                    u.email,
                    approver.name as approved_by_name
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.user_id
                LEFT JOIN users approver ON lr.approved_by = approver.user_id
                ORDER BY lr.created_at DESC
                LIMIT 100
            ");
        }
        
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
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'approve' || $action === 'reject') {
        $leaveId = $input['leave_id'] ?? 0;
        $adminComment = trim($input['admin_comment'] ?? '');
        $adminId = $_SESSION['user_id'];
        $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';
        
        try {
            // Get leave details
            $stmt = $conn->prepare("
                SELECT lr.*, u.email, u.name
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.user_id
                WHERE lr.leave_id = :leave_id
            ");
            $stmt->bindParam(':leave_id', $leaveId);
            $stmt->execute();
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                echo json_encode(['success' => false, 'message' => 'Leave request not found']);
                exit;
            }
            
            // Update leave status
            $stmt = $conn->prepare("
                UPDATE leave_requests 
                SET status = :status, 
                    approved_by = :admin_id, 
                    admin_comment = :comment,
                    updated_at = CURRENT_TIMESTAMP
                WHERE leave_id = :leave_id
            ");
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':admin_id', $adminId);
            $stmt->bindParam(':comment', $adminComment);
            $stmt->bindParam(':leave_id', $leaveId);
            $stmt->execute();
            
            // Send notification to employee (database)
            $message = "Your {$leave['leave_type']} leave request has been {$newStatus}";
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, message, type, related_id) 
                VALUES (:user_id, :message, 'leave', :leave_id)
            ");
            $stmt->bindParam(':user_id', $leave['user_id']);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':leave_id', $leaveId);
            $stmt->execute();
            
            // Send email notification (non-blocking on failure)
            try {
                $emailService = new EmailService();
                $emailService->sendLeaveNotification(
                    $leave['email'],
                    $newStatus,
                    $leave['leave_type'],
                    $leave['start_date'],
                    $leave['end_date'],
                    $adminComment
                );
            } catch (Throwable $mailErr) {
                error_log('Leave notification email failed: ' . $mailErr->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => "Leave request {$newStatus} successfully"
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update leave request'
            ]);
            error_log($e->getMessage());
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
