<?php

/**
 * Password Validator
 * 
 * Validate password theo quy tắc strong password
 */

class PasswordValidator {
    
    /**
     * Validate password
     * 
     * @param string $password
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validate($password) {
        $config = require __DIR__ . '/../config/app.php';
        $rules = $config['password'];
        
        $errors = [];
        
        // Kiểm tra độ dài
        if (strlen($password) < $rules['min_length']) {
            $errors[] = "Mật khẩu phải có ít nhất {$rules['min_length']} ký tự";
        }
        
        // Kiểm tra chữ hoa
        if ($rules['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Mật khẩu phải có ít nhất 1 chữ hoa";
        }
        
        // Kiểm tra chữ thường
        if ($rules['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Mật khẩu phải có ít nhất 1 chữ thường";
        }
        
        // Kiểm tra số
        if ($rules['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Mật khẩu phải có ít nhất 1 chữ số";
        }
        
        // Kiểm tra ký tự đặc biệt
        if ($rules['require_special_chars'] && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "Mật khẩu phải có ít nhất 1 ký tự đặc biệt (!@#$%^&*...)";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Generate strong random password
     * 
     * @param int $length
     * @return string
     */
    public static function generate($length = 12) {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*';
        
        // Đảm bảo có ít nhất 1 ký tự mỗi loại
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        // Thêm các ký tự ngẫu nhiên còn lại
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle để không có pattern cố định
        return str_shuffle($password);
    }
    
    /**
     * Get password requirements as string
     * 
     * @return string
     */
    public static function getRequirements() {
        $config = require __DIR__ . '/../config/app.php';
        $rules = $config['password'];
        
        $requirements = [];
        $requirements[] = "Ít nhất {$rules['min_length']} ký tự";
        
        if ($rules['require_uppercase']) {
            $requirements[] = "Có chữ hoa (A-Z)";
        }
        
        if ($rules['require_lowercase']) {
            $requirements[] = "Có chữ thường (a-z)";
        }
        
        if ($rules['require_numbers']) {
            $requirements[] = "Có chữ số (0-9)";
        }
        
        if ($rules['require_special_chars']) {
            $requirements[] = "Có ký tự đặc biệt (!@#$%^&*...)";
        }
        
        return implode(', ', $requirements);
    }
}
