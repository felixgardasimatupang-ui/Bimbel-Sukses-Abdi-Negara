<?php
/**
 * Advanced Security Enhancement Module - Bimbel Abdi Negara
 * Includes: CSRF Protection, Honeypot, Security Headers, XSS Protection, 
 * Clickjacking Protection, Brute Force Protection, CAPTCHA Support
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict'
    ]);
}

/**
 * Generate CSRF Token (Enhanced with expiration)
 */
function generateCSRFToken($expireSeconds = 3600) {
    if (empty($_SESSION['csrf_token']) || 
        !empty($_SESSION['csrf_token_expire']) && 
        $_SESSION['csrf_token_expire'] < time()) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expire'] = time() + $expireSeconds;
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF Token
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || 
        !isset($_SESSION['csrf_token_expire']) ||
        $_SESSION['csrf_token_expire'] < time()) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check Honeypot Field (Anti-Bot)
 * Returns true if honeypot is filled (bot detected)
 */
function checkHoneypot($fieldName = 'website_url') {
    return !empty($_POST[$fieldName]);
}

/**
 * Generate Honeypot Field HTML
 * Hidden field that should remain empty for humans
 */
function generateHoneypotField($fieldName = 'website_url') {
    return '<input type="text" name="' . $fieldName . '" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;">';
}

/**
 * Generate Time-based Honeypot (disappears after time)
 */
function generateTimeHoneypot($fieldName = 'honey_time', $seconds = 5) {
    $expireTime = time() + $seconds;
    return '<input type="hidden" name="' . $fieldName . '" value="' . $expireTime . '" data-min-time="' . $seconds . '">';
}

/**
 * Check Time-based Honeypot (reject if filled too fast)
 */
function checkTimeHoneypot($fieldName = 'honey_time', $minSeconds = 3) {
    if (empty($_POST[$fieldName])) {
        return true; // Bot didn't fill it
    }
    $submitTime = intval($_POST[$fieldName]);
    $currentTime = time();
    // If submitted before the honeypot expires, it's likely a bot
    if ($currentTime < $submitTime) {
        return true; // Bot detected
    }
    // If too fast even after expiration
    if (($currentTime - $submitTime) < $minSeconds && $submitTime > $currentTime) {
        return true;
    }
    return false;
}

/**
 * Set Security Headers (Advanced)
 */
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Control referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Prevent browser from detecting XSS
    header('X-XSS-Protection: 1; mode=block');
    
    // Control DNS prefetching
    header('X-DNS-Prefetch-Control: off');
    
    // Permissions policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    
    // Cache control for sensitive pages
    header('Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Remove server information
    header('Server: WebServer');
    header('X-Powered-By: PHP');
}

/**
 * Advanced XSS Protection
 */
function xssClean($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = xssClean($value);
        }
        return $data;
    }
    
    // Remove null bytes
    $data = str_replace("\0", '', $data);
    
    // Decode HTML entities
    $data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Remove script tags and event handlers
    $data = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $data);
    $data = preg_replace('/<iframe[^>]*>.*?<\/iframe>/si', '', $data);
    $data = preg_replace('/<object[^>]*>.*?<\/object>/si', '', $data);
    $data = preg_replace('/<embed[^>]*>/si', '', $data);
    $data = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/si', '', $data);
    $data = preg_replace('/on\w+\s*=\s*[^\s>]+/si', '', $data);
    $data = preg_replace('/javascript\s*:/si', '', $data);
    $data = preg_replace('/data\s*:/si', '', $data);
    
    // Encode for output
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize Input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
        return $data;
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate Email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

/**
 * Validate Phone Number (Indonesian format)
 */
function validatePhone($phone) {
    // Accepts formats: +628xx, 08xx, 628xx
    $clean = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^628[0-9]{8,11}$/', $clean) || preg_match('/^08[0-9]{8,11}$/', $clean);
}

/**
 * Log Security Event
 */
function logSecurityEvent($event, $details = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $logFile = __DIR__ . '/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Check for SQL Injection patterns (Enhanced)
 */
function containsSQLInjection($data) {
    if (is_array($data)) {
        foreach ($data as $value) {
            if (containsSQLInjection($value)) {
                return true;
            }
        }
        return false;
    }
    
    $patterns = [
        '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
        '/(\%3D)|(=)[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
        '/\w*(\%27)|(\')((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i',
        '/((\%27)|(\')|)union/i',
        '/exec(\s|\+)+(s|x)p\w+/i',
        '/(\%77)|w/ix',
        '/(\%61)|a/ix',
        '/(\%73)|s/ix',
        '/(\%65)|e/ix',
        '/(\%72)|r/ix',
        '/(\%74)|t/ix',
        '/(\%70)|p/ix',
        '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|CREATE|TRUNCATE)\b/i',
        '/\b(LOAD_FILE|INTO OUTFILE|INTO DUMPFILE)\b/i',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $data)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Rate Limiting with Sliding Window (Enhanced)
 */
function checkRateLimit($maxRequests = 10, $windowSeconds = 300, $action = 'general') {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'rate_' . md5($ip) . '_' . $action;
    $now = time();
    
    // Use file-based storage for simplicity
    $rateFile = __DIR__ . '/rate_limit.json';
    $data = [];
    
    if (file_exists($rateFile)) {
        $content = file_get_contents($rateFile);
        $data = json_decode($content, true) ?: [];
    }
    
    // Remove expired entries
    foreach ($data as $k => $v) {
        if ($v['time'] < $now - $windowSeconds) {
            unset($data[$k]);
        }
    }
    
    // Count requests from this IP for this action
    $requestCount = 0;
    foreach ($data as $v) {
        if ($v['ip'] === $ip && $v['action'] === $action) {
            $requestCount++;
        }
    }
    
    // Check limit
    if ($requestCount >= $maxRequests) {
        logSecurityEvent('rate_limit_exceeded', ['ip' => $ip, 'action' => $action, 'count' => $requestCount]);
        return false;
    }
    
    // Add new request
    $data[] = ['ip' => $ip, 'action' => $action, 'time' => $now];
    file_put_contents($rateFile, json_encode($data), LOCK_EX);
    
    return true;
}

/**
 * Brute Force Protection
 */
function checkBruteForce($username, $maxAttempts = 5, $lockoutSeconds = 900) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'brute_' . md5($username . $ip);
    $now = time();
    
    $bruteFile = __DIR__ . '/brute_force.json';
    $data = [];
    
    if (file_exists($bruteFile)) {
        $data = json_decode(file_get_contents($bruteFile), true) ?: [];
    }
    
    // Clean old entries
    foreach ($data as $k => $v) {
        if ($v['time'] < $now - $lockoutSeconds) {
            unset($data[$k]);
        }
    }
    
    // Check if locked
    if (isset($data[$key]) && $data[$key]['attempts'] >= $maxAttempts) {
        $remaining = ($data[$key]['time'] + $lockoutSeconds) - $now;
        logSecurityEvent('brute_force_locked', ['username' => $username, 'ip' => $ip, 'remaining' => $remaining]);
        return ['locked' => true, 'remaining' => $remaining];
    }
    
    return ['locked' => false];
}

/**
 * Record Failed Login Attempt
 */
function recordFailedAttempt($username) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'brute_' . md5($username . $ip);
    $now = time();
    
    $bruteFile = __DIR__ . '/brute_force.json';
    $data = [];
    
    if (file_exists($bruteFile)) {
        $data = json_decode(file_get_contents($bruteFile), true) ?: [];
    }
    
    if (!isset($data[$key])) {
        $data[$key] = ['attempts' => 0, 'time' => $now];
    }
    
    $data[$key]['attempts']++;
    $data[$key]['time'] = $now;
    
    file_put_contents($bruteFile, json_encode($data), LOCK_EX);
    
    logSecurityEvent('failed_login_attempt', ['username' => $username, 'ip' => $ip, 'attempts' => $data[$key]['attempts']]);
}

/**
 * Reset Brute Force Counter
 */
function resetBruteForce($username) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'brute_' . md5($username . $ip);
    
    $bruteFile = __DIR__ . '/brute_force.json';
    if (file_exists($bruteFile)) {
        $data = json_decode(file_get_contents($bruteFile), true) ?: [];
        unset($data[$key]);
        file_put_contents($bruteFile, json_encode($data), LOCK_EX);
    }
}

/**
 * CAPTCHA Generation (Simple Math CAPTCHA)
 */
function generateCaptcha() {
    $num1 = rand(1, 20);
    $num2 = rand(1, 20);
    $operator = rand(0, 1) ? '+' : '-';
    
    if ($operator === '-') {
        if ($num1 < $num2) {
            $temp = $num1;
            $num1 = $num2;
            $num2 = $temp;
        }
        $answer = $num1 - $num2;
    } else {
        $answer = $num1 + $num2;
    }
    
    $_SESSION['captcha_answer'] = password_hash($answer, PASSWORD_DEFAULT);
    $_SESSION['captcha_time'] = time();
    
    return [
        'question' => "$num1 $operator $num2 = ?",
        'expires' => 300 // 5 minutes
    ];
}

/**
 * Validate CAPTCHA
 */
function validateCaptcha($userAnswer) {
    if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
        return false;
    }
    
    // Check expiration (5 minutes)
    if (time() - $_SESSION['captcha_time'] > 300) {
        unset($_SESSION['captcha_answer'], $_SESSION['captcha_time']);
        return false;
    }
    
    $valid = password_verify($userAnswer, $_SESSION['captcha_answer']);
    
    // Clear captcha after validation
    unset($_SESSION['captcha_answer'], $_SESSION['captcha_time']);
    
    return $valid;
}

/**
 * Generate HTML for CAPTCHA
 */
function getCaptchaHTML() {
    $captcha = generateCaptcha();
    return '
    <div class="security-captcha">
        <label for="captcha_answer">Verification:</label>
        <span class="captcha-question">' . htmlspecialchars($captcha['question']) . '</span>
        <input type="number" name="captcha_answer" id="captcha_answer" required autocomplete="off">
        <small>Answer the simple math question above</small>
    </div>';
}

/**
 * Get Client Info
 */
function getClientInfo() {
    return [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
    ];
}

/**
 * Check for Suspicious Patterns
 */
function checkSuspiciousActivity() {
    $client = getClientInfo();
    $suspicious = false;
    $reasons = [];
    
    // Check for missing user agent
    if (empty($client['user_agent']) || $client['user_agent'] === 'unknown') {
        $suspicious = true;
        $reasons[] = 'Missing user agent';
    }
    
    // Check for suspicious user agents
    $badAgents = ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'java/'];
    foreach ($badAgents as $bad) {
        if (stripos($client['user_agent'], $bad) !== false) {
            $suspicious = true;
            $reasons[] = 'Suspicious user agent: ' . $bad;
            break;
        }
    }
    
    // Check for missing referer on POST
    if ($client['method'] === 'POST' && empty($client['referer'])) {
        $suspicious = true;
        $reasons[] = 'POST without referer';
    }
    
    // Check for external referer
    if (!empty($client['referer']) && strpos($client['referer'], $_SERVER['HTTP_HOST'] ?? '') === false) {
        // This might be suspicious but can be legitimate
        logSecurityEvent('external_referer_post', ['referer' => $client['referer']]);
    }
    
    if ($suspicious) {
        logSecurityEvent('suspicious_activity', ['reasons' => $reasons, 'client' => $client]);
    }
    
    return $suspicious;
}

/**
 * Secure File Upload Validation
 */
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf'], $maxSizeMB = 5) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload error code: ' . $file['error'];
        return $errors;
    }
    
    // Check size
    $maxSize = $maxSizeMB * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $errors[] = 'File too large. Maximum size: ' . $maxSizeMB . 'MB';
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes);
    }
    
    // Check MIME type
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf'
    ];
    
    $detectedMime = mime_content_type($file['tmp_name']);
    if (isset($allowedMimes[$ext]) && $detectedMime !== $allowedMimes[$ext]) {
        $errors[] = 'MIME type mismatch';
    }
    
    // Check for executable content in image
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        $handle = fopen($file['tmp_name'], 'rb');
        $firstKB = fread($handle, 1024);
        fclose($handle);
        
        if (preg_match('/<\?php|<\?|eval\(/i', $firstKB)) {
            $errors[] = 'Suspicious content detected in file';
        }
    }
    
    return $errors;
}

/**
 * Generate Nonce for CSP
 */
function generateNonce() {
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['csp_nonce'] = $nonce;
    return $nonce;
}

/**
 * Get CSP Nonce
 */
function getCSPNonce() {
    return $_SESSION['csp_nonce'] ?? generateNonce();
}

// Apply security headers on every include
setSecurityHeaders();

// Check suspicious activity
checkSuspiciousActivity();

// Handle AJAX requests for CSRF token
if (isset($_GET['action']) && $_GET['action'] === 'get_csrf') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['token' => generateCSRFToken(), 'nonce' => getCSPNonce()]);
    exit;
}

// Handle AJAX requests for CAPTCHA
if (isset($_GET['action']) && $_GET['action'] === 'get_captcha') {
    header('Content-Type: text/html');
    header('Cache-Control: no-store');
    echo getCaptchaHTML();
    exit;
}
?>
