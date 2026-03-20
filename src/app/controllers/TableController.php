<?php

/**
 * Table Controller
 * 
 * Quản lý bàn ăn
 */

class TableController extends Controller {
    private $tableService;
    
    public function __construct() {
        $this->tableService = new TableService();
    }
    
    /**
     * Lấy tất cả tables (không phân trang)
     * GET /api/tables/all?status=AVAILABLE
     * Public - cho khách chọn bàn
     */
    public function all() {
        $filters = [];
        
        if ($status = $this->getQuery('status')) {
            $filters['status'] = $status;
        }
        
        if ($minCapacity = $this->getQuery('min_capacity')) {
            $filters['min_capacity'] = $minCapacity;
        }
        
        try {
            $tables = $this->tableService->getAllTables($filters);
            return $this->success($tables);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy danh sách tables (có phân trang)
     * GET /api/tables?page=1&per_page=20&status=AVAILABLE&search=A
     * Admin/Staff
     */
    public function index() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $page = max(1, (int)$this->getQuery('page', 1));
        $perPage = min(100, max(1, (int)$this->getQuery('per_page', 20)));
        
        $filters = [];
        
        if ($status = $this->getQuery('status')) {
            $filters['status'] = $status;
        }
        
        if ($minCapacity = $this->getQuery('min_capacity')) {
            $filters['min_capacity'] = $minCapacity;
        }
        
        if ($search = $this->getQuery('search')) {
            $filters['search'] = $search;
        }
        
        try {
            $result = $this->tableService->getTables($page, $perPage, $filters);
            return $this->paginate($result['tables'], $result['total'], $page, $perPage);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy chi tiết table
     * GET /api/tables/{id}
     * Public
     */
    public function show($id) {
        try {
            $table = $this->tableService->getTableById($id);
            return $this->success($table);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
    
    /**
     * Tạo table mới
     * POST /api/tables
     * Body: { "table_number": "A01", "capacity": 4, "status": "AVAILABLE" }
     * Admin/Staff only
     */
    public function store() {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        try {
            $table = $this->tableService->createTable($data);
            return $this->success($table, 'Tạo bàn thành công', 201);
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : 500;
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Cập nhật table
     * PUT /api/tables/{id}
     * Body: { "table_number": "A02", "capacity": 6 }
     * Admin/Staff only
     */
    public function update($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        try {
            $table = $this->tableService->updateTable($id, $data);
            return $this->success($table, 'Cập nhật bàn thành công');
            
        } catch (Exception $e) {
            $statusCode = isset($e->errors) ? 422 : ($e->getMessage() === 'Không tìm thấy bàn' ? 404 : 500);
            $errors = isset($e->errors) ? $e->errors : null;
            return $this->error($e->getMessage(), $statusCode, $errors);
        }
    }
    
    /**
     * Cập nhật status
     * PUT /api/tables/{id}/status
     * Body: { "status": "OCCUPIED" }
     * Admin/Staff only
     */
    public function updateStatus($id) {
        // Yêu cầu role ADMIN hoặc STAFF
        $this->requireRole([ROLE_ADMIN, ROLE_STAFF]);
        
        $data = $this->getBody();
        
        if (empty($data['status'])) {
            return $this->error('Status không được để trống', 422);
        }
        
        try {
            $table = $this->tableService->updateStatus($id, $data['status']);
            return $this->success($table, 'Cập nhật trạng thái bàn thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy bàn') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Xóa table
     * DELETE /api/tables/{id}
     * Admin only
     */
    public function destroy($id) {
        // Yêu cầu role ADMIN
        $this->requireRole([ROLE_ADMIN]);
        
        try {
            $this->tableService->deleteTable($id);
            return $this->success(null, 'Xóa bàn thành công');
            
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === 'Không tìm thấy bàn') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
    
    /**
     * Lấy danh sách bàn trống
     * GET /api/tables/available
     * Public - cho khách chọn bàn
     */
    public function available() {
        try {
            $tables = $this->tableService->getAvailableTables();
            return $this->success($tables);
            
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
