<?php
// =============================================================================
// mail_helper.php - Email Delivery Helper for BarangayGo
// =============================================================================
// FUNCTION SUMMARY:
// - Nagpapadala ng opisyal na HTML email verification code gamit ang Gmail SMTP.
// - Naka-encrypt gamit ang STARTTLS (Port 587) para ligtas ang transmission.
// =============================================================================

// =============================================================================
// [GMAIL SENDER CONFIGURATION] DITO NAKALAGAY ANG EMAIL AT PASSWORD NG SENDER
// =============================================================================
if (!defined('SMTP_HOST'))      define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT'))      define('SMTP_PORT', 587); // Gmail STARTTLS Port
if (!defined('SMTP_USER'))      define('SMTP_USER', 'your-email@gmail.com'); // Iyong Gmail Sender address
if (!defined('SMTP_PASS'))      define('SMTP_PASS', 'your-16-char-app-password'); // 16-character Google App Password
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'BarangayGo Portal');

/**
 * =============================================================================
 * [FUNCTION 1] send_password_reset_code()
 * =============================================================================
 * Binubuo ang HTML email template na may 6-digit OTP code at ipinapadala sa residente.
 *
 * @param string $recipient_email - Email address ng residente
 * @param string $recipient_name  - Pangalan ng residente
 * @param string $code            - 6-digit random verification code
 * @return array ['success' => bool, 'message' => string]
 */
function send_password_reset_code($recipient_email, $recipient_name, $code) {
    if (empty(SMTP_USER) || empty(SMTP_PASS)) {
        return [
            'success' => false,
            'message' => 'Email service is not yet configured. Please set the Gmail sender address and 16-character App Password in mail_helper.php so verification codes can be delivered directly to resident inboxes.'
        ];
    }

    $subject = "BarangayGo Verification Code: " . $code;
    $safe_name = !empty($recipient_name) ? htmlspecialchars($recipient_name, ENT_QUOTES) : 'Resident';

    // =========================================================================
    // [HTML EMAIL TEMPLATE DESIGN & COLORS]
    // Dito nakatakda ang mga kulay ng matatanggap na email sa Gmail:
    // - Pinaka-background ng email: background-color: #f8fafc (Light grayish blue)
    // - Background ng card/kahon: background: #ffffff (Puti)
    // - Kulay ng Header Banner: background-color: #6d4df2 (Purple / BarangayGo Accent)
    // - Kulay ng OTP Box: background-color: #f1f5f9 (Soft gray) na may kulay #6d4df2 na numero
    // =========================================================================
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset Code</title>
</head>
<!-- [KULAY NG BACKGROUND SA LABAS NG EMAIL: #f8fafc] -->
<body style="margin: 0; padding: 24px; font-family: 'Verdana', Geneva, Tahoma, sans-serif; background-color: #f8fafc; color: #0f172a;">
    
    <!-- [KULAY NG KAHON NG EMAIL CARD: #ffffff] -->
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        
        <!-- [KULAY NG HEADER BANNER: #6d4df2 PURPLE] -->
        <div style="background-color: #6d4df2; padding: 22px 24px; text-align: center; color: #ffffff;">
            <h1 style="margin: 0; font-size: 20px; letter-spacing: 0.5px; font-weight: 700; color: #ffffff;">BARANGAYGO PORTAL</h1>
            <p style="margin: 6px 0 0; font-size: 13px; opacity: 0.95; color: #e0e7ff;">Password Reset Verification Code</p>
        </div>
        
        <!-- Nilalaman ng Email -->
        <div style="padding: 28px 24px;">
            <p style="margin: 0 0 16px; font-size: 14px; line-height: 1.6;">Hello <strong>{$safe_name}</strong>,</p>
            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6;">We received a request to reset your BarangayGo account password. Please use the 6-digit verification code below to reset your password:</p>
            
            <!-- [KULAY NG 6-DIGIT OTP BOX: background-color: #f1f5f9; text-color: #6d4df2] -->
            <div style="text-align: center; margin: 24px 0; padding: 18px; background-color: #f1f5f9; border-radius: 8px; border: 1px dashed #cbd5e1;">
                <span style="font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #6d4df2; font-family: 'Consolas', 'Courier New', monospace;">{$code}</span>
            </div>

            <p style="margin: 0 0 12px; font-size: 13px; color: #475569; line-height: 1.5;">This verification code will expire in <strong>15 minutes</strong>. If you did not request this password reset, please ignore this email or review your account security.</p>
        </div>
        
        <!-- Footer ng Email -->
        <div style="background-color: #f8fafc; padding: 14px 24px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">
            This is an automated notification from BarangayGo Portal. Please do not reply directly to this email.
        </div>
    </div>
</body>
</html>
HTML;

    // Tawagin ang function para ipadala sa pamamagitan ng SMTP socket
    return send_smtp_mail($recipient_email, $recipient_name, $subject, $html);
}

/**
 * =============================================================================
 * [FUNCTION 2] send_smtp_mail()
 * =============================================================================
 * Pure PHP Socket Client na kumokonekta sa Gmail SMTP (smtp.gmail.com:587).
 * Nagpapadala gamit ang STARTTLS encryption nang walang dependency sa third-party libraries.
 */
function send_smtp_mail($to, $to_name, $subject, $body_html) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $from_name = SMTP_FROM_NAME;

    $timeout = 15;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ]
    ]);

    // 1. Kumonekta sa SMTP Server (smtp.gmail.com:587)
    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['success' => false, 'message' => "Could not connect to SMTP server: $errstr ($errno)"];
    }

    // Helper function para magbasa ng response mula sa Gmail
    $read = function($expected_code) use ($socket) {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            $data .= $line;
            if (empty($line) || (isset($line[3]) && $line[3] === ' ')) {
                break;
            }
        }
        $code = substr($data, 0, 3);
        if ($expected_code !== null && $code !== (string)$expected_code) {
            return false;
        }
        return $data;
    };

    // Helper function para magpadala ng command sa Gmail
    $write = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    // 2. Handshake at STARTTLS encryption negotiation
    if (!$read('220')) {
        fclose($socket);
        return ['success' => false, 'message' => 'Invalid greeting from SMTP server.'];
    }

    $write('EHLO ' . gethostname());
    if (!$read('250')) {
        fclose($socket);
        return ['success' => false, 'message' => 'EHLO handshake failed.'];
    }

    $write('STARTTLS');
    if (!$read('220')) {
        fclose($socket);
        return ['success' => false, 'message' => 'STARTTLS negotiation failed.'];
    }

    $crypto_ok = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if (!$crypto_ok) {
        fclose($socket);
        return ['success' => false, 'message' => 'TLS encryption handshake failed.'];
    }

    $write('EHLO ' . gethostname());
    if (!$read('250')) {
        fclose($socket);
        return ['success' => false, 'message' => 'Post-TLS EHLO failed.'];
    }

    // 3. Authentication gamit ang Gmail username at 16-character App Password
    $write('AUTH LOGIN');
    if (!$read('334')) {
        fclose($socket);
        return ['success' => false, 'message' => 'AUTH LOGIN failed.'];
    }

    $write(base64_encode($user));
    if (!$read('334')) {
        fclose($socket);
        return ['success' => false, 'message' => 'Username rejected by SMTP server.'];
    }

    $write(base64_encode($pass));
    if (!$read('235')) {
        fclose($socket);
        return ['success' => false, 'message' => 'Authentication failed. Please verify your Gmail address and 16-character App Password.'];
    }

    // 4. Ipadala ang nagpadala (MAIL FROM) at tatanggap (RCPT TO)
    $write('MAIL FROM: <' . $user . '>');
    if (!$read('250')) {
        fclose($socket);
        return ['success' => false, 'message' => 'Sender address rejected.'];
    }

    $write('RCPT TO: <' . $to . '>');
    if (!$read('250')) {
        fclose($socket);
        return ['success' => false, 'message' => 'Recipient address rejected.'];
    }

    // 5. Ipadala ang mismong mensahe (DATA at HTML body)
    $write('DATA');
    if (!$read('354')) {
        fclose($socket);
        return ['success' => false, 'message' => 'DATA command rejected.'];
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <" . $user . ">\r\n";
    $headers .= "To: <" . $to . ">\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "X-Mailer: BarangayGo SMTP\r\n";

    $write($headers . "\r\n" . $body_html . "\r\n.");
    if (!$read('250')) {
        fclose($socket);
        return ['success' => false, 'message' => 'Message body rejected by SMTP server.'];
    }

    // 6. Tapusin ang koneksyon (QUIT)
    $write('QUIT');
    fclose($socket);

    return ['success' => true, 'message' => 'Email sent successfully.'];
}

