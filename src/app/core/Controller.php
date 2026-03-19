<?php

/**
 * Base Controller
 * 
 * Controller cơ sở cho tất cả controllers
 */

class Controller {
    
    /**
     * Trả về JSON response
     */
    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Success response
     */
    protected function success($data = null, $message = 'Success', $statusCode = 200) {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
    
    /**
     * Error response
     */
    protected function error($message = 'Error', $statusCode = 400, $errors = null) {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        $this->json($response, $statusCode);
    }
    
    /**
     * Get request body
     */
    protected function getBody() {
        $body = file_get_contents('php://input');
        return json_decode($body, true) ?? [];
    }
    
    /**
     * Get query params
     */
    protected function getQuery($key = null, $default = null) {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Validate required fields
     */
    protected function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && empty($data[$field])) {
                $errors[$field] = "Trường {$field} là bắt buộc";
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}
