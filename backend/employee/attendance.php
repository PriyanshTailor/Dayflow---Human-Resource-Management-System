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
    $action = $_GET['action'] ?? 'list';
    
    try {
        if ($action === 'today') {
            // Get today's attendance
            $stmt = $conn->prepare("
                SELECT 
                    attendance_id,
                    date,
                    check_in_time,
                    check_out_time,
                    working_hours,
                    status,
                    approval_status,
                    remarks
                FROM attendance
                WHERE user_id = :user_id AND date = CURDATE()
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $attendance ?: null
            ]);
            
        } elseif ($action === 'daily') {
            // Get daily attendance for specific date or current month
            $date = $_GET['date'] ?? date('Y-m');
            
            $stmt = $conn->prepare("
                SELECT 
                    attendance_id,
                    date,
                    check_in_time,
                    check_out_time,
                    working_hours,
                    status,
                    approval_status,
                    remarks
                FROM attendance
                WHERE user_id = :user_id 
                AND DATE_FORMAT(date, '%Y-%m') = :month
                ORDER BY date DESC
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':month' => $date
            ]);
            
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $attendance
            ]);
            
        } elseif ($action === 'weekly') {
            // Get weekly attendance (current week or specified week)
            $weekStart = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
            
            $stmt = $conn->prepare("
                SELECT 
                    attendance_id,
                    date,
                    check_in_time,
                    check_out_time,
                    working_hours,
                    status,
                    approval_status,
                    remarks
                FROM attendance
                WHERE user_id = :user_id 
                AND date BETWEEN :week_start AND :week_end
                ORDER BY date ASC
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':week_start' => $weekStart,
                ':week_end' => $weekEnd
            ]);
            
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $attendance,
                'week_start' => $weekStart,
                'week_end' => $weekEnd
            ]);
            
        } elseif ($action === 'summary') {
            // Get monthly summary
            $month = $_GET['month'] ?? date('Y-m');
            
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
                AND DATE_FORMAT(date, '%Y-%m') = :month
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':month' => $month
            ]);
            
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get leave days
            $stmt = $conn->prepare("
                SELECT COUNT(*) as leave_days
                FROM leave_requests
                WHERE user_id = :user_id 
                AND status = 'Approved'
                AND DATE_FORMAT(start_date, '%Y-%m') = :month
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':month' => $month
            ]);
            
            $leaves = $stmt->fetch(PDO::FETCH_ASSOC);
            $summary['leave_days'] = $leaves['leave_days'];
            
            echo json_encode([
                'success' => true,
                'data' => $summary
            ]);
        } else {
            // Default: list all attendance
            $stmt = $conn->prepare("
                SELECT 
                    attendance_id,
                    date,
                    check_in_time,
                    check_out_time,
                    working_hours,
                    status,
                    approval_status,
                    remarks
                FROM attendance
                WHERE user_id = :user_id
                ORDER BY date DESC
                LIMIT 100
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $attendance
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch attendance'
        ]);
        error_log($e->getMessage());
    }
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        if ($action === 'checkin') {
            // Check if already checked in today
            $stmt = $conn->prepare("
                SELECT attendance_id FROM attendance 
                WHERE user_id = :user_id AND date = CURDATE()
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Already checked in today']);
                exit;
            }
            
            // Create attendance record
            $checkInTime = date('H:i:s');
            $status = $input['status'] ?? 'Present';
            $remarks = $input['remarks'] ?? '';
            
            $stmt = $conn->prepare("
                INSERT INTO attendance (user_id, date, check_in_time, status, approval_status, remarks)
                VALUES (:user_id, CURDATE(), :check_in_time, :status, 'Pending', :remarks)
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':check_in_time' => $checkInTime,
                ':status' => $status,
                ':remarks' => $remarks
            ]);
            
            $attendanceId = $conn->lastInsertId();
            
            // Notify all admins
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $message = "{$employee['name']} checked in at {$checkInTime}";
            $notifyStmt = $conn->prepare("
                INSERT INTO notifications (user_id, message, type, related_id)
                VALUES (:user_id, :message, 'attendance', :related_id)
            ");
            
            foreach ($admins as $admin) {
                $notifyStmt->execute([
                    ':user_id' => $admin['user_id'],
                    ':message' => $message,
                    ':related_id' => $attendanceId
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Checked in successfully',
                'attendance_id' => $attendanceId,
                'check_in_time' => $checkInTime
            ]);
            
        } elseif ($action === 'checkout') {
            // Get today's attendance
            $stmt = $conn->prepare("
                SELECT attendance_id, check_in_time FROM attendance 
                WHERE user_id = :user_id AND date = CURDATE()
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attendance) {
                echo json_encode(['success' => false, 'message' => 'No check-in record found for today']);
                exit;
            }
            
            if ($attendance['check_out_time']) {
                echo json_encode(['success' => false, 'message' => 'Already checked out']);
                exit;
            }
            
            // Update with checkout time
            $checkOutTime = date('H:i:s');
            $checkInTime = $attendance['check_in_time'];
            
            // Calculate working hours
            $checkIn = new DateTime($checkInTime);
            $checkOut = new DateTime($checkOutTime);
            $interval = $checkIn->diff($checkOut);
            $workingHours = $interval->h + ($interval->i / 60);
            
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET check_out_time = :check_out_time,
                    working_hours = :working_hours
                WHERE attendance_id = :attendance_id
            ");
            $stmt->execute([
                ':check_out_time' => $checkOutTime,
                ':working_hours' => $workingHours,
                ':attendance_id' => $attendance['attendance_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Checked out successfully',
                'check_out_time' => $checkOutTime,
                'working_hours' => round($workingHours, 2)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to process attendance'
        ]);
        error_log($e->getMessage());
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
