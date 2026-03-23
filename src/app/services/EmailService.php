<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Email Service
 * 
 * Hỗ trợ 2 phương thức gửi email:
 * 1. Resend API (ưu tiên) - Không bị Railway block port
 * 2. SMTP (fallback) - SendGrid, Mailgun, AWS SES
 */

class EmailService {
    private $from;
    private $fromName;
    private $enabled;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    
    public function __construct() {
        $config = require __DIR__ . '/../../config/app.php';
        $this->from = $config['mail']['from'] ?? 'noreply@spicynoodle.com';
        $this->fromName = $config['mail']['from_name'] ?? 'Spicy Noodle';
        $this->enabled = $config['mail']['enabled'] ?? false;
        
        // SMTP config
        $this->smtpHost = getenv('SMTP_HOST') ?: '';
        $this->smtpPort = getenv('SMTP_PORT') ?: 587;
        $this->smtpUser = getenv('SMTP_USER') ?: '';
        $this->smtpPass = getenv('SMTP_PASS') ?: '';
    }
    
    /**
     * Gửi email reset password
     */
    public function sendResetPassword($to, $name, $newPassword) {
        $subject = 'Mật khẩu mới của bạn';
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ff6b6b; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .password { background: #fff; padding: 15px; border-left: 4px solid #ff6b6b; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🌶️ Spicy Noodle Management</h2>
                </div>
                <div class='content'>
                    <p>Xin chào <strong>{$name}</strong>,</p>
                    <p>Bạn đã yêu cầu đặt lại mật khẩu. Mật khẩu mới của bạn là:</p>
                    <div class='password'>
                        <strong style='font-size: 18px; color: #ff6b6b;'>{$newPassword}</strong>
                    </div>
                    <p>Vui lòng đăng nhập và đổi mật khẩu ngay sau khi nhận được email này.</p>
                    <p><strong>Lưu ý:</strong> Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p>
                </div>
                <div class='footer'>
                    <p>© 2026 Spicy Noodle Management System</p>
                    <p>Email này được gửi tự động, vui lòng không trả lời.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Gửi email qua PHP mail() function (dùng mail server của hosting)
     */
    private function sendViaPhpMail($to, $subject, $message) {
        error_log("Sending email via PHP mail() to: {$to}");
        $startTime = microtime(true);
        
        try {
            $mail = new PHPMailer(false); // false = không dùng exceptions
            
            // Dùng PHP mail() thay vì SMTP
            $mail->isMail();
            
            $mail->CharSet = 'UTF-8';
            
            // Sender & recipient
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            $result = $mail->send();
            
            $duration = round(microtime(true) - $startTime, 2);
            
            if ($result) {
                error_log("✅ Email sent via PHP mail() successfully in {$duration}s");
                return true;
            } else {
                error_log("❌ PHP mail() failed after {$duration}s: " . $mail->ErrorInfo);
                return false;
            }
            
        } catch (Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            error_log("❌ PHP mail() error after {$duration}s: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gửi email qua Resend API (không dùng SMTP)
     */
    private function sendViaResendAPI($to, $subject, $message) {
        $apiKey = getenv('RESEND_API_KEY');
        
        if (empty($apiKey)) {
            error_log("Resend API key not configured");
            return false;
        }
        
        error_log("Sending email via Resend API to: {$to}");
        $startTime = microtime(true);
        
        $data = [
            'from' => $this->from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $message
        ];
        
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $duration = round(microtime(true) - $startTime, 2);
        
        if ($httpCode === 200) {
            error_log("✅ Email sent via Resend API successfully in {$duration}s");
            error_log("Response: " . $response);
            return true;
        } else {
            error_log("❌ Resend API failed after {$duration}s (HTTP {$httpCode})");
            error_log("Response: " . $response);
            if ($error) {
                error_log("cURL Error: " . $error);
            }
            return false;
        }
    }
    
    /**
     * Gửi email qua SMTP (SendGrid, Mailgun, AWS SES)
     */
    private function sendViaSMTP($to, $subject, $message) {
        // Kiểm tra SMTP config
        if (empty($this->smtpHost) || empty($this->smtpUser) || empty($this->smtpPass)) {
            error_log("SMTP not configured properly:");
            error_log("  Host: " . ($this->smtpHost ?: 'EMPTY'));
            error_log("  User: " . ($this->smtpUser ?: 'EMPTY'));
            error_log("  Pass: " . (empty($this->smtpPass) ? 'EMPTY' : 'SET'));
            return false;
        }
        
        error_log("Sending email via SMTP to: {$to}");
        $startTime = microtime(true);
        
        try {
            $mail = new PHPMailer(true);
            
            // SMTP settings
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUser;
            $mail->Password = $this->smtpPass;
            
            // Tự động chọn encryption dựa vào port
            if ($this->smtpPort == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
            }
            
            $mail->Port = $this->smtpPort;
            $mail->CharSet = 'UTF-8';
            
            // Timeout settings
            $mail->Timeout = 10;
            $mail->SMTPDebug = 0;
            $mail->SMTPKeepAlive = false;
            $mail->SMTPAutoTLS = true;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Sender & recipient
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            $mail->send();
            
            $duration = round(microtime(true) - $startTime, 2);
            error_log("✅ Email sent via SMTP successfully in {$duration}s");
            return true;
            
        } catch (PHPMailerException $e) {
            $duration = round(microtime(true) - $startTime, 2);
            error_log("❌ SMTP send failed after {$duration}s: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            error_log("❌ SMTP error after {$duration}s: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gửi email (auto-select phương thức tốt nhất)
     */
    private function send($to, $subject, $message) {
        error_log("EmailService->send() called for: {$to}");
        error_log("MAIL_ENABLED: " . ($this->enabled ? 'true' : 'false'));
        
        // Kiểm tra có bật gửi email thật không
        if (!$this->enabled) {
            // Chế độ development: chỉ log, không gửi thật
            error_log("=== EMAIL DEBUG (Development Mode) ===");
            error_log("To: {$to}");
            error_log("Subject: {$subject}");
            error_log("Message: " . strip_tags($message));
            error_log("==================");
            
            return true; // Giả lập gửi thành công
        }
        
        // Ưu tiên 1: Resend API (không bị Railway block port)
        if (getenv('RESEND_API_KEY')) {
            error_log("📧 Using Resend API for email delivery");
            return $this->sendViaResendAPI($to, $subject, $message);
        }
        
        // Ưu tiên 2: PHP mail() function (dùng mail server của hosting)
        if (getenv('USE_PHP_MAIL') === 'true') {
            error_log("📧 Using PHP mail() for email delivery");
            return $this->sendViaPhpMail($to, $subject, $message);
        }
        
        // Ưu tiên 3: SMTP (SendGrid, Mailgun, AWS SES, Brevo)
        error_log("📧 Using SMTP for email delivery");
        return $this->sendViaSMTP($to, $subject, $message);
    }
    
    /**
     * Generate random password
     */
    public static function generateRandomPassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }
}
