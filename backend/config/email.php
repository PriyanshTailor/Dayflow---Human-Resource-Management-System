<?php
/**
 * Email Utility for sending emails to employees
 * Uses PHP's mail() function or can be configured for SMTP
 */

class EmailService {
    private $from_email = 'noreply@hrms.com';
    private $from_name = 'Dayflow HRMS';
    private $smtp_host;
    private $smtp_port;
    private $smtp_user;
    private $smtp_pass;
    private $smtp_encryption; // '', 'tls', or 'ssl'

    public function __construct() {
        $this->loadEnv();

        $this->from_email = getenv('SMTP_FROM_EMAIL') ?: $this->from_email;
        $this->from_name  = getenv('SMTP_FROM_NAME') ?: $this->from_name;

        $this->smtp_host = getenv('SMTP_HOST') ?: '';
        $this->smtp_port = getenv('SMTP_PORT') ?: '';
        $this->smtp_user = getenv('SMTP_USERNAME') ?: '';
        $this->smtp_pass = getenv('SMTP_PASSWORD') ?: '';
        $this->smtp_encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');
    }
    
    /**
     * Send welcome email to new employee with credentials
     */
    public function sendWelcomeEmail($employeeData, $password) {
        $to = $employeeData['email'];
        $subject = 'Welcome to Dayflow HRMS - Your Account Credentials';
        
        $message = $this->getWelcomeEmailTemplate($employeeData, $password);
        
        return $this->deliver($to, $subject, $message);
    }
    
    /**
     * Send notification about attendance status
     */
    public function sendAttendanceNotification($employeeEmail, $status, $date) {
        $to = $employeeEmail;
        $subject = 'Attendance Status Update - ' . $date;
        
        $message = $this->getAttendanceNotificationTemplate($status, $date);
        
        return $this->deliver($to, $subject, $message);
    }
    
    /**
     * Send notification about leave status
     */
    public function sendLeaveNotification($employeeEmail, $status, $leaveType, $startDate, $endDate, $comment = '') {
        $to = $employeeEmail;
        $subject = 'Leave Request ' . $status . ' - ' . $leaveType;
        
        $message = $this->getLeaveNotificationTemplate($status, $leaveType, $startDate, $endDate, $comment);
        
        return $this->deliver($to, $subject, $message);
    }

    /**
     * Deliver email via SMTP if configured; otherwise fall back to mail().
     */
    private function deliver($to, $subject, $message) {
        $headers = "MIME-Version: 1.0\r\n" .
                   "Content-type:text/html;charset=UTF-8\r\n" .
                   "From: {$this->from_name} <{$this->from_email}>\r\n";

        $sent = false;

        if (!empty($this->smtp_host) && !empty($this->smtp_port) && !empty($this->smtp_user) && !empty($this->smtp_pass)) {
            $sent = $this->sendViaSmtp($to, $subject, $message, $headers);
        }

        if (!$sent) {
            $sent = mail($to, $subject, $message, $headers);
        }

        if (!$sent) {
            $this->logEmailFallback($to, $subject, $message);
        }

        return $sent;
    }

    /**
     * Minimal SMTP client with AUTH LOGIN and optional STARTTLS/SSL.
     */
    private function sendViaSmtp($to, $subject, $message, $headers) {
        $host = $this->smtp_host;
        $port = (int)$this->smtp_port;
        $timeout = 20;
        $errno = 0; $errstr = '';

        $transport = ($this->smtp_encryption === 'ssl') ? "ssl://{$host}" : $host;
        $fp = @stream_socket_client("{$transport}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            error_log("SMTP connect failed: {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($fp, $timeout);
        $expect = function($code) use ($fp) {
            $resp = stream_get_line($fp, 8192, "\n");
            if (strpos($resp, (string)$code) !== 0) {
                throw new Exception("SMTP unexpected response: {$resp}");
            }
        };

        $write = function($cmd) use ($fp) {
            fwrite($fp, $cmd . "\r\n");
        };

        try {
            $expect(220);
            $write('EHLO hrms.local');
            $expect(250);

            if ($this->smtp_encryption === 'tls') {
                $write('STARTTLS');
                $expect(220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Failed to enable TLS');
                }
                $write('EHLO hrms.local');
                $expect(250);
            }

            $write('AUTH LOGIN');
            $expect(334);
            $write(base64_encode($this->smtp_user));
            $expect(334);
            $write(base64_encode($this->smtp_pass));
            $expect(235);

            $write('MAIL FROM: <' . $this->from_email . '>');
            $expect(250);
            $write('RCPT TO: <' . $to . '>');
            $expect(250);
            $write('DATA');
            $expect(354);

            $data  = $headers;
            $data .= 'To: ' . $to . "\r\n";
            $data .= 'Subject: ' . $subject . "\r\n";
            $data .= "\r\n" . $message . "\r\n";
            $data .= ".";
            $write($data);
            $expect(250);

            $write('QUIT');
        } catch (Exception $e) {
            error_log('SMTP send failed: ' . $e->getMessage());
            fclose($fp);
            return false;
        }

        fclose($fp);
        return true;
    }

    /**
     * Fallback logger to store email content when mail() is not configured (e.g., local dev/XAMPP)
     */
    private function logEmailFallback($to, $subject, $message) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/email_fallback.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] TO: {$to}\nSUBJECT: {$subject}\n{$message}\n---------------------------\n";
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }

    /**
     * Lightweight .env loader for SMTP settings
     */
    private function loadEnv() {
        $envPath = realpath(__DIR__ . '/../../.env');
        if (!$envPath || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                }
            }
        }
    }
    
    private function getWelcomeEmailTemplate($employeeData, $password) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #7c3aed, #9333ea); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                .credentials { background: white; padding: 20px; border-left: 4px solid #7c3aed; margin: 20px 0; }
                .button { display: inline-block; padding: 12px 30px; background: #7c3aed; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🌊 Welcome to Dayflow HRMS</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($employeeData['name']) . ',</h2>
                    <p>Welcome to our organization! Your account has been successfully created in our HR Management System.</p>
                    
                    <div class="credentials">
                        <h3>Your Login Credentials</h3>
                        <p><strong>Employee ID:</strong> ' . htmlspecialchars($employeeData['employee_id']) . '</p>
                        <p><strong>Email/Username:</strong> ' . htmlspecialchars($employeeData['email']) . '</p>
                        <p><strong>Default Password:</strong> <code style="background:#f3f4f6;padding:5px 10px;border-radius:3px;font-size:16px;font-weight:bold;">' . htmlspecialchars($password) . '</code></p>
                        <p><strong>Department:</strong> ' . htmlspecialchars($employeeData['department']) . '</p>
                        <p><strong>Position:</strong> ' . htmlspecialchars($employeeData['position']) . '</p>
                    </div>
                    
                    <p><strong>⚠️ Important:</strong> Please change your password immediately after your first login for security purposes. All employees use the same default password initially.</p>
                    
                    <a href="http://localhost/HRMS/auth/login.php" class="button">Login to Your Account</a>
                    
                    <p style="margin-top: 30px;">If you have any questions or need assistance, please contact the HR department.</p>
                </div>
                <div class="footer">
                    <p>This is an automated email. Please do not reply.</p>
                    <p>&copy; 2026 Dayflow HRMS. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function getAttendanceNotificationTemplate($status, $date) {
        $statusColor = $status === 'Approved' ? '#22c55e' : '#ef4444';
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #7c3aed, #9333ea); color: white; padding: 20px; text-align: center; }
                .content { background: #f9fafb; padding: 30px; }
                .status { background: ' . $statusColor . '; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Attendance Status Update</h2>
                </div>
                <div class="content">
                    <p>Your attendance for <strong>' . $date . '</strong> has been:</p>
                    <p><span class="status">' . $status . '</span></p>
                    <p>You can view your complete attendance record by logging into the HRMS portal.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function getLeaveNotificationTemplate($status, $leaveType, $startDate, $endDate, $comment) {
        $statusColor = $status === 'Approved' ? '#22c55e' : '#ef4444';
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #7c3aed, #9333ea); color: white; padding: 20px; text-align: center; }
                .content { background: #f9fafb; padding: 30px; }
                .status { background: ' . $statusColor . '; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #7c3aed; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Leave Request Update</h2>
                </div>
                <div class="content">
                    <p>Your leave request has been <span class="status">' . $status . '</span></p>
                    <div class="details">
                        <p><strong>Leave Type:</strong> ' . $leaveType . '</p>
                        <p><strong>Start Date:</strong> ' . $startDate . '</p>
                        <p><strong>End Date:</strong> ' . $endDate . '</p>
                        ' . ($comment ? '<p><strong>Admin Comment:</strong> ' . htmlspecialchars($comment) . '</p>' : '') . '
                    </div>
                    <p>Please login to the HRMS portal to view complete details.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
}
