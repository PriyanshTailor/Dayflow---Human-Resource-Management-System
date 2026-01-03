<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Require employee login
requireEmployee();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('Y-m');

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch base salary
    $stmt = $conn->prepare("SELECT basic_salary, allowances, deductions, currency FROM salary WHERE user_id = :uid");
    $stmt->bindParam(':uid', $userId);
    $stmt->execute();
    $salary = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salary) {
        echo json_encode(['success' => false, 'message' => 'Salary not set']);
        exit;
    }

    // Attendance summary for the month (only approved)
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as cnt
        FROM attendance
        WHERE user_id = :uid
          AND approval_status = 'Approved'
          AND DATE_FORMAT(date, '%Y-%m') = :month
        GROUP BY status
    ");
    $stmt->execute([':uid' => $userId, ':month' => $month]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = ['Present' => 0, 'Absent' => 0, 'Half-day' => 0, 'Leave' => 0];
    foreach ($rows as $r) {
        $counts[$r['status']] = (float) $r['cnt'];
    }

    $basic = (float) $salary['basic_salary'];
    $allowances = (float) $salary['allowances'];
    $baseDeductions = (float) $salary['deductions'];
    $gross = $basic + $allowances;

    // Daily rate based on 30-day month
    $dailyRate = $gross / 30.0;

    $presentDays = $counts['Present'];
    $leaveDays = $counts['Leave'];
    $halfDays = $counts['Half-day'];
    $absentDays = $counts['Absent'];

    // Leave treated as paid, Half-day as 0.5 paid
    $payableDays = $presentDays + $leaveDays + ($halfDays * 0.5);

    // Attendance-based deduction for unpaid days
    $attendanceDeduction = max(0, ($absentDays * $dailyRate) + (($halfDays * 0.5) * $dailyRate));

    // Net payable for the month
    $netPayable = max(0, $gross - $attendanceDeduction - $baseDeductions);

    // Upsert into payroll_history
    $upsert = $conn->prepare("
        INSERT INTO payroll_history (
            user_id, month, gross_salary, allowances, base_deductions, attendance_deductions,
            net_payable, present_days, half_days, leave_days, absent_days, payable_days
        ) VALUES (
            :uid, :month, :gross, :allowances, :base_ded, :att_ded,
            :net, :present, :half, :leave_days, :absent, :payable_days
        )
        ON DUPLICATE KEY UPDATE
            gross_salary = VALUES(gross_salary),
            allowances = VALUES(allowances),
            base_deductions = VALUES(base_deductions),
            attendance_deductions = VALUES(attendance_deductions),
            net_payable = VALUES(net_payable),
            present_days = VALUES(present_days),
            half_days = VALUES(half_days),
            leave_days = VALUES(leave_days),
            absent_days = VALUES(absent_days),
            payable_days = VALUES(payable_days)
    ");

    $upsert->execute([
        ':uid' => $userId,
        ':month' => $month,
        ':gross' => $gross,
        ':allowances' => $allowances,
        ':base_ded' => $baseDeductions,
        ':att_ded' => $attendanceDeduction,
        ':net' => $netPayable,
        ':present' => $presentDays,
        ':half' => $halfDays,
        ':leave_days' => $leaveDays,
        ':absent' => $absentDays,
        ':payable_days' => $payableDays
    ]);

    echo json_encode([
        'success' => true,
        'data' => [
            'month' => $month,
            'currency' => $salary['currency'],
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'gross_salary' => $gross,
            'base_deductions' => $baseDeductions,
            'attendance_deductions' => $attendanceDeduction,
            'net_payable' => $netPayable,
            'present_days' => $presentDays,
            'leave_days' => $leaveDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'payable_days' => $payableDays,
            'daily_rate' => $dailyRate
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to compute payroll'
    ]);
    error_log($e->getMessage());
}
