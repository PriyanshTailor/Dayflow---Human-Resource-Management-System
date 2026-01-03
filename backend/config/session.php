<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function jsonAuthError($message = 'Unauthorized', $code = 401) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function requireLogin() {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        jsonAuthError('Please login first', 401);
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        jsonAuthError('Admin access required', 403);
    }
}

function requireEmployee() {
    requireLogin();
    if ($_SESSION['role'] !== 'employee') {
        jsonAuthError('Employee access required', 403);
    }
}

function currentUser() {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'name' => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ];
}
