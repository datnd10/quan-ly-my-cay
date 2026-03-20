<?php

/**
 * Database Migration Helper
 * 
 * Tự động tạo bảng từ code
 */

class Migration {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Chạy tất cả migrations
     */
    public function run() {
        $this->createSettingsTable();
        $this->createUsersTable();
        $this->createCustomersTable();
        $this->createCategoriesTable();
        $this->createProductsTable();
        $this->createBranchesTable();
        $this->createInventoriesTable();
        $this->createTablesTable();
        $this->createOrdersTable();
        $this->createOrderStatusHistoryTable();
        $this->createOrderItemsTable();
        $this->createPaymentsTable();
        $this->createVouchersTable();
        $this->createOrderVouchersTable();
        $this->createLoyaltyTransactionsTable();
        $this->createReservationsTable();
        $this->createIndexes();
    }
    
    private function createSettingsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS settings (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(100) UNIQUE,
            config_value TEXT,
            description VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->db->query($sql);
    }
    
    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'CUSTOMER',
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->db->query($sql);
    }
    
    private function createCustomersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS customers (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL,
            name VARCHAR(100),
            phone VARCHAR(20) UNIQUE,
            email VARCHAR(100),
            points INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $this->db->query($sql);
    }
    
    private function createCategoriesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->query($sql);
    }
    
    private function createProductsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS products (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            category_id BIGINT,
            name VARCHAR(150) NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            description TEXT,
            image_url TEXT COMMENT 'Multiple images separated by |',
            stock_quantity INT DEFAULT 0,
            min_stock INT DEFAULT 0,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )";
        $this->db->query($sql);
    }
    
    private function createBranchesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS branches (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            address VARCHAR(255),
            phone VARCHAR(20),
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->query($sql);
    }
    
    private function createInventoriesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS inventories (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT,
            branch_id BIGINT,
            stock_quantity INT DEFAULT 0,
            min_stock INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            UNIQUE(product_id, branch_id)
        )";
        $this->db->query($sql);
    }
    
    private function createTablesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS tables (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            branch_id BIGINT,
            table_number VARCHAR(20) NOT NULL,
            capacity INT,
            status VARCHAR(20) DEFAULT 'AVAILABLE',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            UNIQUE(branch_id, table_number)
        )";
        $this->db->query($sql);
    }
    
    private function createOrdersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS orders (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT,
            table_id BIGINT,
            branch_id BIGINT,
            status VARCHAR(20) DEFAULT 'PENDING',
            total_amount DECIMAL(12,2) DEFAULT 0,
            discount_amount DECIMAL(12,2) DEFAULT 0,
            final_amount DECIMAL(12,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            paid_at TIMESTAMP NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
        )";
        $this->db->query($sql);
    }
    
    private function createOrderStatusHistoryTable() {
        $sql = "CREATE TABLE IF NOT EXISTS order_status_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT NOT NULL,
            old_status VARCHAR(20),
            new_status VARCHAR(20) NOT NULL,
            changed_by BIGINT,
            note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
        )";
        $this->db->query($sql);
    }
    
    private function createOrderItemsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS order_items (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT,
            product_id BIGINT,
            quantity INT NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            subtotal DECIMAL(12,2) GENERATED ALWAYS AS (quantity * price) STORED,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
            UNIQUE(order_id, product_id)
        )";
        $this->db->query($sql);
    }
    
    private function createPaymentsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS payments (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT,
            method VARCHAR(50) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'PENDING',
            transaction_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )";
        $this->db->query($sql);
    }
    
    private function createVouchersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS vouchers (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            discount_type VARCHAR(20) NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL,
            min_order_amount DECIMAL(12,2) DEFAULT 0,
            max_discount DECIMAL(12,2),
            usage_limit INT DEFAULT 1,
            used_count INT DEFAULT 0,
            start_date DATETIME,
            expired_at DATETIME,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->query($sql);
    }
    
    private function createOrderVouchersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS order_vouchers (
            order_id BIGINT,
            voucher_id BIGINT,
            discount_applied DECIMAL(12,2),
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (order_id, voucher_id),
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE
        )";
        $this->db->query($sql);
    }
    
    private function createLoyaltyTransactionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS loyalty_transactions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT,
            order_id BIGINT,
            points INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            description VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        )";
        $this->db->query($sql);
    }
    
    private function createReservationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS reservations (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT,
            table_id BIGINT,
            branch_id BIGINT,
            reservation_time DATETIME NOT NULL,
            guest_count INT,
            status VARCHAR(20) DEFAULT 'PENDING',
            note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
        )";
        $this->db->query($sql);
    }
    
    private function createIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_order_customer ON orders(customer_id)",
            "CREATE INDEX IF NOT EXISTS idx_order_table ON orders(table_id)",
            "CREATE INDEX IF NOT EXISTS idx_order_branch ON orders(branch_id)",
            "CREATE INDEX IF NOT EXISTS idx_order_status ON orders(status)",
            "CREATE INDEX IF NOT EXISTS idx_order_created_at ON orders(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_inventory_product ON inventories(product_id)",
            "CREATE INDEX IF NOT EXISTS idx_inventory_branch ON inventories(branch_id)",
            "CREATE INDEX IF NOT EXISTS idx_product_category ON products(category_id)",
            "CREATE INDEX IF NOT EXISTS idx_product_status ON products(status)",
        ];
        
        foreach ($indexes as $sql) {
            try {
                $this->db->query($sql);
            } catch (Exception $e) {
                // Ignore if index exists
            }
        }
    }
}
