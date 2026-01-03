<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'all';

        if ($action === 'all') {
            $date = $_GET['date'] ?? date('Y-m-d');
            $employeeId = $_GET['employee_id'] ?? null;
            $status = $_GET['status'] ?? null;
            $approvalStatus = $_GET['approval_status'] ?? null;

            $sql = "
                SELECT 
                    a.attendance_id,
                    a.date,
                    a.check_in_time,
                    a.check_out_time,
                    a.working_hours,
                    a.status,
                    a.approval_status,
                    a.remarks,
                    u.user_id,
                    u.employee_id,
                    u.name as employee_name,
                    u.department,
                    u.position,
                    approver.name as approved_by_name
                FROM attendance a
                JOIN users u ON a.user_id = u.user_id
                LEFT JOIN users approver ON a.approved_by = approver.user_id
                WHERE a.date = :date
            ";

            $params = [':date' => $date];

            if ($employeeId) {
                $sql .= " AND u.employee_id = :employee_id";
                $params[':employee_id'] = $employeeId;
            }
            if ($status) {
                $sql .= " AND a.status = :status";
                $params[':status'] = $status;
            }
            if ($approvalStatus) {
                $sql .= " AND a.approval_status = :approval_status";
                $params[':approval_status'] = $approvalStatus;
            }

            $sql .= " ORDER BY u.name ASC";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $attendance
            ]);

        } elseif ($action === 'summary') {
            $month = $_GET['month'] ?? date('Y-m');

            $stmt = $conn->prepare("\n                SELECT \n                    u.user_id,\n                    u.employee_id,\n                    u.name,\n                    u.department,\n                    COUNT(a.attendance_id) as total_days,\n                    SUM(CASE WHEN a.approval_status = 'Approved' AND a.status = 'Present' THEN 1 ELSE 0 END) as present_days,\n                    SUM(CASE WHEN a.approval_status = 'Approved' AND a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,\n                    SUM(CASE WHEN a.approval_status = 'Approved' AND a.status = 'Half-day' THEN 1 ELSE 0 END) as half_days,\n                    SUM(CASE WHEN a.approval_status = 'Pending' THEN 1 ELSE 0 END) as pending_approvals,\n                    SUM(CASE WHEN a.approval_status = 'Approved' THEN a.working_hours ELSE 0 END) as total_hours\n                FROM users u\n                LEFT JOIN attendance a ON u.user_id = a.user_id \n                    AND DATE_FORMAT(a.date, '%Y-%m') = :month\n                WHERE u.role = 'employee' AND u.status = 'active'\n                GROUP BY u.user_id\n                ORDER BY u.name\n            ");
            $stmt->bindParam(':month', $month);
            $stmt->execute();
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $summary
            ]);

        } elseif ($action === 'employee') {
            $userId = $_GET['user_id'] ?? 0;
            $month = $_GET['month'] ?? date('Y-m');

            $stmt = $conn->prepare("\n                SELECT \n                    a.attendance_id,\n                    a.date,\n                    a.check_in_time,\n                    a.check_out_time,\n                    a.working_hours,\n                    a.status,\n                    a.approval_status,\n                    a.remarks,\n                    approver.name as approved_by_name\n                FROM attendance a\n                LEFT JOIN users approver ON a.approved_by = approver.user_id\n                WHERE a.user_id = :user_id\n                AND DATE_FORMAT(a.date, '%Y-%m') = :month\n                ORDER BY a.date DESC\n            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':month' => $month
            ]);

            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $attendance
            ]);

        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $attendanceId = $input['attendance_id'] ?? null;
        $approvalAction = $input['action'] ?? null;
        $adminComment = $input['comment'] ?? '';

        if (!$attendanceId || !$approvalAction) {
            echo json_encode(['success' => false, 'message' => 'Missing attendance_id or action']);
            exit;
        }

        if (!in_array($approvalAction, ['approve', 'reject'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
        }

        $adminId = $_SESSION['user_id'] ?? null;
        $approvalStatus = ($approvalAction === 'approve') ? 'Approved' : 'Rejected';

        $stmt = $conn->prepare("\n            UPDATE attendance \n            SET approval_status = :approval_status, \n                approved_by = :admin_id,\n                remarks = :comment\n            WHERE attendance_id = :attendance_id\n        ");
        $stmt->execute([
            ':approval_status' => $approvalStatus,
            ':admin_id' => $adminId,
            ':comment' => $adminComment,
            ':attendance_id' => $attendanceId
        ]);

        $stmt = $conn->prepare("\n            SELECT \n                a.attendance_id,\n                a.date,\n                a.check_in_time,\n                a.check_out_time,\n                a.working_hours,\n                a.status,\n                a.approval_status,\n                u.name as employee_name,\n                u.employee_id\n            FROM attendance a\n            JOIN users u ON a.user_id = u.user_id\n            WHERE a.attendance_id = :attendance_id\n        ");
        $stmt->bindParam(':attendance_id', $attendanceId);
        $stmt->execute();
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => ucfirst($approvalAction) . 'd successfully',
            'data' => $updated
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error processing attendance'
    ]);
    error_log($e->getMessage());
}
