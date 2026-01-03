<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonAuthError('Invalid request method', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$emailOrId = trim($input['login'] ?? '');
$password = $input['password'] ?? '';

if ($emailOrId === '' || $password === '') {
    jsonAuthError('Email/Employee ID and password are required', 400);
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :login OR employee_id = :login2 LIMIT 1");
    $stmt->bindParam(':login', $emailOrId);
    $stmt->bindParam(':login2', $emailOrId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        jsonAuthError('Invalid credentials', 401);
    }

    if ($user['status'] === 'inactive') {
        jsonAuthError('Account inactive. Contact administrator.', 403);
    }

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    error_log($e->getMessage());
}
