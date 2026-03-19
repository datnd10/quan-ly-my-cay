<?php

/**
 * Base Model
 * 
 * Model cơ sở cho tất cả models
 */

class Model {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy tất cả records
     */
    public function all() {
        $sql = "SELECT * FROM {$this->table}";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Tìm record theo ID
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
    
    /**
     * Tạo record mới
     */
    public function create($data) {
        $filtered = $this->filterFillable($data);
        return $this->db->insert($this->table, $filtered);
    }
    
    /**
     * Cập nhật record
     */
    public function update($id, $data) {
        $filtered = $this->filterFillable($data);
        $where = "{$this->primaryKey} = :id";
        return $this->db->update($this->table, $filtered, $where, ['id' => $id]);
    }
    
    /**
     * Xóa record
     */
    public function delete($id) {
        $where = "{$this->primaryKey} = :id";
        return $this->db->delete($this->table, $where, ['id' => $id]);
    }
    
    /**
     * Lọc dữ liệu theo fillable
     */
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Query builder đơn giản
     */
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} :value";
        return $this->db->fetchAll($sql, ['value' => $value]);
    }
}
