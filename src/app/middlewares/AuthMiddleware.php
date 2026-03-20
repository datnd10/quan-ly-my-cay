<?php

/**
 * Auth Middleware
 * 
 * Xác thực JWT token và lấy thông tin user
 */

class AuthMiddleware {
    private $jwtConfig;
    
    public function __construct() {
        $config = require __DIR__ . '/../../config/app.php';
        $this->jwtConfig = $config['jwt'];
    }
    
    /**
     * Xác thực token và trả về user info
     */
    public function authenticate() {
        $token = $this->getBearerToken();
        
        if (!$token) {
            $this->unauthorized('Token không được cung cấp');
        }
        
        try {
            $payload = $this->verifyJWT($token);
            
            // Kiểm tra token hết hạn
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                $this->unauthorized('Token đã hết hạn');
            }
            
            // Trả về thông tin user từ token
            return [
                'user_id' => $payload['user_id'],
                'username' => $payload['username'],
                'role' => $payload['role']
            ];
            
        } catch (Exception $e) {
            $this->unauthorized('Token không hợp lệ');
        }
    }
    
    /**
     * Kiểm tra role
     */
    public function checkRole($user, $allowedRoles) {
        if (!in_array($user['role'], $allowedRoles)) {
            $this->forbidden('Bạn không có quyền truy cập');
        }
    }
    
    /**
     * Lấy Bearer token từ header
     */
    private function getBearerToken() {
        $headers = $this->getAuthorizationHeader();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Lấy Authorization header
     */
    private function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)), 
                array_values($requestHeaders)
            );
            
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }
    
    /**
     * Verify JWT token
     */
    private function verifyJWT($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new Exception('Token không hợp lệ');
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        // Verify signature
        $signature = $this->base64UrlDecode($base64UrlSignature);
        $expectedSignature = hash_hmac(
            'sha256',
            $base64UrlHeader . "." . $base64UrlPayload,
            $this->jwtConfig['secret'],
            true
        );
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Chữ ký token không hợp lệ');
        }
        
        // Decode payload
        $payload = json_decode($this->base64UrlDecode($base64UrlPayload), true);
        
        if (!$payload) {
            throw new Exception('Payload không hợp lệ');
        }
        
        return $payload;
    }
    
    /**
     * Base64 URL decode
     */
    private function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Trả về lỗi 401 Unauthorized
     */
    private function unauthorized($message) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Trả về lỗi 403 Forbidden
     */
    private function forbidden($message) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}
