<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Email Service
 * 
 * Gửi email sử dụng PHPMailer với SMTP
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
     * Gửi email
     */
    private function send($to, $subject, $message) {
        // Kiểm tra có bật gửi email thật không
        if (!$this->enabled) {
            // Chế độ development: chỉ log, không gửi thật
            error_log("=== EMAIL DEBUG ===");
            error_log("To: {$to}");
            error_log("Subject: {$subject}");
            error_log("Message: " . strip_tags($message));
            error_log("==================");
            
            return true; // Giả lập gửi thành công
        }
        
        // Kiểm tra SMTP config
        if (empty($this->smtpHost) || empty($this->smtpUser) || empty($this->smtpPass)) {
            error_log("SMTP not configured. Email not sent to: {$to}");
            // Không throw exception để không block flow
            return true;
        }
        
        try {
            $mail = new PHPMailer(true);
            
            // SMTP settings
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUser;
            $mail->Password = $this->smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort;
            $mail->CharSet = 'UTF-8';
            
            // Sender & recipient
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            $mail->send();
            return true;
            
        } catch (PHPMailerException $e) {
            error_log("Email send failed: " . $e->getMessage());
            // Không throw exception để không block flow
            return false;
        }
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
