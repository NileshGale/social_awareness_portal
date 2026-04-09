<?php
/**
 * send_email.php — Fixed version
 * 
 * FIXES:
 * 1. Correct PHPMailer path: looks inside frontend/PHPMailer/ (your actual structure)
 * 2. Falls back to php mail() if PHPMailer unavailable
 * 3. Always logs OTP to a file (otp_debug.log) for local dev
 * 4. Returns $sent result so api.php can warn if email failed
 */

function sendOTPEmail($to_email, $otp, $purpose) {
    $subject = "Your OTP for $purpose - AwareX";

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { font-size: 24px; margin: 0 0 8px 0; }
            .header p { opacity: 0.85; margin: 0; font-size: 14px; }
            .content { padding: 40px 30px; }
            .otp-box { background: #f0f7ff; border: 2px dashed #3b82f6; padding: 25px; margin: 25px 0; text-align: center; border-radius: 12px; }
            .otp { font-size: 42px; font-weight: bold; color: #1e3a8a; letter-spacing: 10px; }
            .validity { color: #6b7280; font-size: 13px; margin-top: 10px; }
            .footer { text-align: center; padding: 20px 30px; background: #f9fafb; color: #9ca3af; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>AwareX</h1>
                <p>Creating Awareness for a Better Society</p>
            </div>
            <div class='content'>
                <h2 style='color:#1e3a8a; margin-bottom: 16px;'>$purpose Verification</h2>
                <p style='color:#4b5563;'>Hello,</p>
                <p style='color:#4b5563;'>Your One-Time Password (OTP) for <strong>$purpose</strong> is:</p>
                <div class='otp-box'>
                    <div class='otp'>$otp</div>
                    <div class='validity'>Valid for 3 minutes only</div>
                </div>
                <p style='color:#4b5563;'>If you did not request this, please ignore this email or contact us immediately.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2026 AwareX. All rights reserved.</p>
                <p>Nagpur, Maharashtra 440016, India</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // ── Always log OTP to file (useful for local dev / debugging) ──────────
    $logFile = dirname(__DIR__) . '/otp_debug.log';
    $logEntry = date('Y-m-d H:i:s') . " | TO: $to_email | PURPOSE: $purpose | OTP: $otp\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);

    return sendEmailCore($to_email, $subject, $message);
}

function sendNotificationEmail($to_email, $subject, $body_html, $reply_to = null) {
    return sendEmailCore($to_email, $subject, $body_html, $reply_to);
}

function sendEmailCore($to_email, $subject, $message, $reply_to = null) {
    // ── Try PHPMailer ──────────────────────────────────────────────────────
    // Check all common PHPMailer locations
    $phpmailerPaths = [
        // Composer autoload (project root or backend)
        dirname(__DIR__) . '/vendor/autoload.php',
        dirname(__FILE__)  . '/../vendor/autoload.php',
        // ── YOUR STRUCTURE: SOCIAL_AWARENESS/PHPMailer/ ──
        // backend/ is inside SOCIAL_AWARENESS/, PHPMailer/ is sibling of backend/
        dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php',
        // Manual install inside frontend folder
        dirname(__DIR__) . '/frontend/PHPMailer/src/PHPMailer.php',
        // Manual install in backend folder
        dirname(__FILE__)  . '/PHPMailer/src/PHPMailer.php',
    ];

    $composerAutoload = null;
    $manualPHPMailer  = null;
    foreach ($phpmailerPaths as $path) {
        if (file_exists($path)) {
            if (substr($path, -12) === 'autoload.php') {
                $composerAutoload = $path;
            } else {
                $manualPHPMailer = dirname($path); // .../PHPMailer/src/
            }
            break;
        }
    }

    // Validate SMTP credentials before even trying
    $smtpConfigured = (
        defined('SMTP_USERNAME') &&
        SMTP_USERNAME !== '' &&
        SMTP_USERNAME !== 'your-email@gmail.com' &&
        defined('SMTP_PASSWORD') &&
        SMTP_PASSWORD !== '' &&
        SMTP_PASSWORD !== 'your-app-password'
    );

    if ($smtpConfigured && ($composerAutoload || $manualPHPMailer)) {
        if ($composerAutoload) {
            require_once $composerAutoload;
        } else {
            // Manual PHPMailer require
            require_once $manualPHPMailer . '/Exception.php';
            require_once $manualPHPMailer . '/PHPMailer.php';
            require_once $manualPHPMailer . '/SMTP.php';
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to_email);
            if ($reply_to && filter_var($reply_to['email'], FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($reply_to['email'], $reply_to['name'] ?? '');
            }
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->send();
            return ['sent' => true, 'method' => 'phpmailer'];
        } catch (\Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            // Fall through to php mail() fallback
        }
    }

    // ── Fallback: php mail() ────────────────────────────────────────────────
    if ($smtpConfigured) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        if ($reply_to && filter_var($reply_to['email'], FILTER_VALIDATE_EMAIL)) {
            $r_name = $reply_to['name'] ?? '';
            $headers .= "Reply-To: $r_name <" . $reply_to['email'] . ">\r\n";
        }
        $sent = @mail($to_email, $subject, $message, $headers);
        if ($sent) {
            return ['sent' => true, 'method' => 'mail'];
        }
    }

    // ── SMTP not configured or all methods failed ───────────────────────────
    return ['sent' => false, 'method' => 'none'];
}
?>