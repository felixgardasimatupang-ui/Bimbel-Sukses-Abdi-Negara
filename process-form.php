<?php
/**
 * Form Handler Backend - Bimbel Abdi Negara
 * Advanced Security + Database Integration
 */

// Include security module
require_once __DIR__ . '/security.php';

// Include database connection
require_once __DIR__ . '/includes/db.php';

// Set response content type
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// CORS headers
header('Access-Control-Allow-Origin: https://www.bimbelabdinegara.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false, 'message' => ''];
$clientInfo = getClientInfo();

try {
    // ========================================
    // 1. Rate Limiting (5 requests per 5 minutes)
    // ========================================
    if (!checkRateLimit(5, 300, 'form_submit')) {
        $response['message'] = 'Terlalu banyak percobaan. Silakan coba lagi dalam 5 menit.';
        logSecurityEvent('rate_limit_exceeded', [
            'client' => $clientInfo,
            'action' => 'form_submit'
        ]);
        echo json_encode($response);
        exit;
    }
    
    // ========================================
    // 2. Validate Request Method
    // ========================================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Metode tidak diizinkan.';
        logSecurityEvent('invalid_method', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'client' => $clientInfo
        ]);
        echo json_encode($response);
        exit;
    }
    
    // ========================================
    // 3. CSRF Token Validation
    // ========================================
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        $response['message'] = 'Token keamanan tidak valid.';
        logSecurityEvent('csrf_failure', [
            'client' => $clientInfo,
            'has_token' => !empty($csrfToken)
        ]);
        echo json_encode($response);
        exit;
    }
    
    // ========================================
    // 4. Honeypot Check (Anti-Bot)
    // ========================================
    if (checkHoneypot('website_url')) {
        // Bot detected - silent fail
        logSecurityEvent('bot_detected_honeypot', ['client' => $clientInfo]);
        $response['success'] = true;
        $response['message'] = 'Pendaftaran berhasil! Kami akan segera menghubungi Anda.';
        echo json_encode($response);
        exit;
    }
    
    // ========================================
    // 5. Time-based Honeypot Check (3+ seconds)
    // ========================================
    if (checkTimeHoneypot('honey_time', 3)) {
        logSecurityEvent('bot_detected_time_honeypot', ['client' => $clientInfo]);
        $response['success'] = true;
        $response['message'] = 'Pendaftaran berhasil! Kami akan segera menghubungi Anda.';
        echo json_encode($response);
        exit;
    }
    
    // ========================================
    // 6. CAPTCHA Validation (if enabled)
    // ========================================
    if (isset($_POST['captcha_answer'])) {
        if (!validateCaptcha($_POST['captcha_answer'])) {
            $response['message'] = 'Jawaban verifikasi tidak valid.';
            logSecurityEvent('captcha_failure', ['client' => $clientInfo]);
            echo json_encode($response);
            exit;
        }
    }
    
    // ========================================
    // 7. SQL Injection Detection
    // ========================================
    $email = $_POST['email'] ?? '';
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $program = $_POST['program'] ?? 'general';
    $message = $_POST['message'] ?? '';
    
    // Use advanced XSS cleaning
    $email = xssClean($email);
    $name = xssClean($name);
    $phone = xssClean($phone);
    $program = xssClean($program);
    $message = xssClean($message);
    
    // Check for SQL injection patterns
    if (containsSQLInjection($email) || containsSQLInjection($name) || 
        containsSQLInjection($phone) || containsSQLInjection($program) || containsSQLInjection($message)) {
        $response['message'] = 'Input tidak valid.';
        logSecurityEvent('sql_injection_attempt', [
            'client' => $clientInfo,
            'fields' => ['email' => !empty($email), 'name' => !empty($name)]
        ]);
        echo json_encode($response);
        exit;
    }
    
    // ========================================
    // 8. Input Validation & Sanitization
    // ========================================
    
    // Validate email
    if (empty($email)) {
        $response['message'] = 'Email tidak boleh kosong.';
        echo json_encode($response);
        exit;
    }
    
    if (!validateEmail($email)) {
        $response['message'] = 'Format email tidak valid.';
        echo json_encode($response);
        exit;
    }
    
    // Validate name
    if (!empty($name)) {
        if (strlen($name) < 2 || strlen($name) > 100) {
            $response['message'] = 'Nama harus antara 2-100 karakter.';
            echo json_encode($response);
            exit;
        }
    }
    
    // Validate phone (Indonesian format)
    if (!empty($phone)) {
        if (!validatePhone($phone)) {
            $response['message'] = 'Format nomor telepon tidak valid.';
            echo json_encode($response);
            exit;
        }
    }
    
    // Validate message
    if (!empty($message)) {
        if (strlen($message) < 10 || strlen($message) > 1000) {
            $response['message'] = 'Pesan harus antara 10-1000 karakter.';
            echo json_encode($response);
            exit;
        }
    }
    
    // ========================================
    // 9. Save to Database
    // ========================================
    try {
        $db = getDB();
        
        // Check for duplicate email (within last 24 hours)
        $existing = dbFetchOne(
            "SELECT id FROM registrations WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$email]
        );
        
        if ($existing) {
            $response['message'] = 'Email ini sudah mendaftar dalam 24 jam terakhir. Tim kami akan segera menghubungi Anda.';
            $response['success'] = true;
            echo json_encode($response);
            exit;
        }
        
        // Insert registration
        $registrationId = dbInsert('registrations', [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'program' => $program,
            'message' => $message,
            'ip_address' => $clientInfo['ip'],
            'user_agent' => substr($clientInfo['user_agent'], 0, 500)
        ]);
        
        // ========================================
        // 10. Send Email Notification
        // ========================================
        $to = 'info@bimbelabdinegara.com';
        $subject = '[Website] Pendaftaran Baru - ' . $email;
        
        $safeEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        $emailBody = "=== Formulir Pendaftaran Baru ===\n\n";
        $emailBody .= "Tanggal: " . date('Y-m-d H:i:s') . " (UTC+7)\n";
        $emailBody .= "ID Registrasi: #" . $registrationId . "\n";
        $emailBody .= "IP: " . $clientInfo['ip'] . "\n\n";
        
        if (!empty($name)) {
            $emailBody .= "Nama: " . $name . "\n";
        }
        $emailBody .= "Email: " . $safeEmail . "\n";
        
        if (!empty($phone)) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $emailBody .= "Telepon: " . $cleanPhone . "\n";
        }
        
        if (!empty($program) && $program !== 'general') {
            $emailBody .= "Program: " . ucfirst($program) . "\n";
        }
        
        if (!empty($message)) {
            $emailBody .= "\nPesan:\n" . $message . "\n";
        }
        
        // Email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: Website Bimbel <noreply@bimbelabdinegara.com>',
            'Reply-To: ' . $safeEmail,
            'X-Mailer: PHP/' . phpversion(),
            'X-Originating-IP: ' . $clientInfo['ip']
        ];
        
        $mailSent = mail($to, $subject, $emailBody, implode("\r\n", $headers));
        
        // ========================================
        // 11. Send Confirmation to User
        // ========================================
        $confirmationSubject = 'Terima Kasih - Bimbel Abdi Negara';
        $confirmationBody = "Halo" . (!empty($name) ? " $name" : "") . ",\n\n";
        $confirmationBody .= "Terima kasih telah menghubungi Bimbel Abdi Negara.\n\n";
        $confirmationBody .= "Kami telah menerima pendaftaran informasi Anda.\n";
        $confirmationBody .= "ID Registrasi: #" . $registrationId . "\n\n";
        $confirmationBody .= "Tim kami akan segera menghubungi Anda dalam 1x24 jam kerja.\n\n";
        $confirmationBody .= "Salam,\nTim Bimbel Abdi Negara\n\n";
        $confirmationBody .= "---\n";
        $confirmationBody .= "Pesan ini dikirimkan ke: " . $safeEmail . "\n";
        
        $confirmationHeaders = $headers;
        mail($safeEmail, $confirmationSubject, $confirmationBody, implode("\r\n", $confirmationHeaders));
        
        $response['success'] = true;
        $response['message'] = 'Pendaftaran berhasil! Kami akan segera menghubungi Anda.';
        
        logSecurityEvent('form_success', [
            'registration_id' => $registrationId,
            'email' => md5($safeEmail),
            'client' => $clientInfo
        ]);
        
    } catch (Exception $e) {
        // If database fails, still send success response (fail gracefully)
        error_log('Database error in form handler: ' . $e->getMessage());
        
        // Try to send email notification about database issue
        mail('admin@bimbelabdinegara.com', '[Website] Database Error', 
            "Error: " . $e->getMessage() . "\n\nForm data:\nEmail: $email\nName: $name\nPhone: $phone");
        
        $response['success'] = true;
        $response['message'] = 'Pendaftaran berhasil! Kami akan segera menghubungi Anda.';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    error_log('Form error: ' . $e->getMessage());
    logSecurityEvent('exception', [
        'error' => $e->getMessage(),
        'client' => $clientInfo
    ]);
}

echo json_encode($response);
exit;
