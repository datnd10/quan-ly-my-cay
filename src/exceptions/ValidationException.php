<?php

/**
 * Validation Exception
 * 
 * Exception cho validation errors
 */

class ValidationException extends Exception {
    public $errors = [];
    
    public function __construct($message = 'Dữ liệu không hợp lệ', $errors = []) {
        parent::__construct($message);
        $this->errors = $errors;
    }
    
    public function getErrors() {
        return $this->errors;
    }
}
