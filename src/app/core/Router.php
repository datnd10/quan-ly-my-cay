<?php

/**
 * Router Class
 * 
 * Xử lý routing cho API
 */

class Router {
    private $routes = [];
    private $middlewares = [];
    
    /**
     * Đăng ký route GET
     */
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
        return $this;
    }
    
    /**
     * Đăng ký route POST
     */
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
        return $this;
    }
    
    /**
     * Đăng ký route PUT
     */
    public function put($path, $handler) {
        $this->addRoute('PUT', $path, $handler);
        return $this;
    }
    
    /**
     * Đăng ký route DELETE
     */
    public function delete($path, $handler) {
        $this->addRoute('DELETE', $path, $handler);
        return $this;
    }
    
    /**
     * Thêm route vào danh sách
     */
    private function addRoute($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => []
        ];
    }
    
    /**
     * Thêm middleware cho route cuối cùng
     */
    public function middleware($middleware) {
        if (!empty($this->routes)) {
            $lastIndex = count($this->routes) - 1;
            $this->routes[$lastIndex]['middlewares'][] = $middleware;
        }
        return $this;
    }
    
    /**
     * Xử lý request
     */
    public function dispatch($method, $uri) {
        // Hỗ trợ method override cho PUT/PATCH/DELETE qua POST
        // Kiểm tra _method trong query string hoặc POST data
        if ($method === 'POST') {
            // Check query string: ?_method=PUT
            if (isset($_GET['_method'])) {
                $method = strtoupper($_GET['_method']);
            }
            // Check POST data: _method=PUT
            elseif (isset($_POST['_method'])) {
                $method = strtoupper($_POST['_method']);
            }
            // Check JSON body
            else {
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                if (isset($data['_method'])) {
                    $method = strtoupper($data['_method']);
                }
            }
        }
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            $pattern = $this->convertToRegex($route['path']);
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                
                // Chạy middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $result = $this->runMiddleware($middleware);
                    if ($result !== true) {
                        return $result;
                    }
                }
                
                // Chạy handler
                return $this->runHandler($route['handler'], $matches);
            }
        }
        
        return null;
    }
    
    /**
     * Convert path thành regex pattern
     */
    private function convertToRegex($path) {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * Chạy middleware
     */
    private function runMiddleware($middleware) {
        if (is_callable($middleware)) {
            return $middleware();
        }
        
        if (is_string($middleware) && class_exists($middleware)) {
            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                return $instance->handle();
            }
        }
        
        return true;
    }
    
    /**
     * Chạy handler
     */
    private function runHandler($handler, $params = []) {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return true; // Đánh dấu đã xử lý
        }
        
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($controller, $method) = explode('@', $handler);
            
            if (class_exists($controller)) {
                $instance = new $controller();
                if (method_exists($instance, $method)) {
                    call_user_func_array([$instance, $method], $params);
                    return true; // Đánh dấu đã xử lý
                }
            }
        }
        
        return null;
    }
}
