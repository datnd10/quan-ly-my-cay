<?php

/**
 * Email Service
 * 
 * Gửi email sử dụng PHP mail() hoặc SMTP
 */

class EmailService {
    private $from;
    private $fromName;
    
    public function __construct() {
        $config = require __DIR__ . '/../../config/app.php';
        $this->from = $config['mail']['from'] ?? 'noreply@spicynoodle.com';
        $this->fromName = $config['mail']['from_name'] ?? 'Spicy Noodle';
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
        $config = require __DIR__ . '/../../config/app.php';
        $mailEnabled = $config['mail']['enabled'] ?? false;
        
        if (!$mailEnabled) {
            // Chế độ development: chỉ log, không gửi thật
            error_log("=== EMAIL DEBUG ===");
            error_log("To: {$to}");
            error_log("Subject: {$subject}");
            error_log("Message: " . strip_tags($message));
            error_log("==================");
            
            return true; // Giả lập gửi thành công
        }
        
        // Gửi email thật
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            "From: {$this->fromName} <{$this->from}>",
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $success = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if (!$success) {
            throw new Exception('Không thể gửi email. Vui lòng thử lại sau.');
        }
        
        return true;
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
