-- =========================
-- SETTINGS
-- =========================
CREATE TABLE settings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE,
    config_value TEXT,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- USERS (LOGIN ONLY)
-- =========================
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'CUSTOMER',
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- CUSTOMERS (PROFILE)
-- =========================
CREATE TABLE customers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNIQUE NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20) UNIQUE,
    email VARCHAR(100),
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =========================
-- POINT TRANSACTIONS (LỊCH SỬ ĐIỂM)
-- =========================
CREATE TABLE point_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    points INT NOT NULL COMMENT 'Số điểm (+ là cộng, - là trừ)',
    type VARCHAR(20) NOT NULL COMMENT 'EARN, REDEEM, ADJUST, REFUND',
    description VARCHAR(255) COMMENT 'Mô tả giao dịch',
    reference_type VARCHAR(50) COMMENT 'order, manual, voucher',
    reference_id BIGINT COMMENT 'ID của order/voucher nếu có',
    balance_after INT NOT NULL COMMENT 'Số điểm sau giao dịch',
    created_by BIGINT COMMENT 'User ID của người tạo (nếu là admin/staff)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer_created (customer_id, created_at DESC)
);

-- =========================
-- CATEGORY & PRODUCT
-- =========================
CREATE TABLE categories (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    stock_quantity INT DEFAULT 0 COMMENT 'Số lượng tồn kho',
    min_stock INT DEFAULT 0 COMMENT 'Cảnh báo khi < min_stock',
    status TINYINT DEFAULT 1 COMMENT '1=available, 0=unavailable',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- =========================
-- TABLES (BÀN ĂN)
-- =========================
CREATE TABLE tables (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(20) NOT NULL UNIQUE,
    capacity INT DEFAULT 4 COMMENT 'Số chỗ ngồi',
    status VARCHAR(20) DEFAULT 'AVAILABLE' COMMENT 'AVAILABLE, OCCUPIED, RESERVED, MAINTENANCE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =========================
-- ORDERS (CẢI TIẾN)
-- =========================
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT,
    table_id BIGINT,
    status VARCHAR(20) DEFAULT 'PENDING',
    total_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    final_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL
);

-- =========================
-- ORDER STATUS HISTORY (MỚI - AUDIT TRAIL)
-- =========================
CREATE TABLE order_status_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    old_status VARCHAR(20),
    new_status VARCHAR(20) NOT NULL,
    changed_by BIGINT,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =========================
-- ORDER ITEMS
-- =========================
CREATE TABLE order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT,
    product_id BIGINT,
    quantity INT NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) GENERATED ALWAYS AS (quantity * price) STORED,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    UNIQUE(order_id, product_id)
);

-- =========================
-- PAYMENTS
-- =========================
CREATE TABLE payments (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT,
    method VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- =========================
-- VOUCHERS (CẢI TIẾN)
-- =========================
CREATE TABLE vouchers (
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
);

CREATE TABLE order_vouchers (
    order_id BIGINT,
    voucher_id BIGINT,
    discount_applied DECIMAL(12,2),
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id, voucher_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE
);

-- =========================
-- LOYALTY
-- =========================
CREATE TABLE loyalty_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT,
    order_id BIGINT,
    points INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- =========================
-- RESERVATIONS
-- =========================
CREATE TABLE reservations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT,
    table_id BIGINT,
    reservation_time DATETIME NOT NULL,
    guest_count INT,
    status VARCHAR(20) DEFAULT 'PENDING',
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL
);

-- =========================
-- INDEXES (CẢI TIẾN)
-- =========================
-- Orders indexes
CREATE INDEX idx_order_customer ON orders(customer_id);
CREATE INDEX idx_order_table ON orders(table_id);
CREATE INDEX idx_order_status ON orders(status);
CREATE INDEX idx_order_created_at ON orders(created_at);
CREATE INDEX idx_order_completed_at ON orders(completed_at);
CREATE INDEX idx_order_paid_at ON orders(paid_at);

-- Order status history indexes
CREATE INDEX idx_order_status_history_order ON order_status_history(order_id);
CREATE INDEX idx_order_status_history_created ON order_status_history(created_at);

-- Product indexes
CREATE INDEX idx_product_category ON products(category_id);
CREATE INDEX idx_product_status ON products(status);

-- Voucher indexes
CREATE INDEX idx_voucher_code ON vouchers(code);
CREATE INDEX idx_voucher_expired ON vouchers(expired_at);
CREATE INDEX idx_voucher_status ON vouchers(status);

-- Reservation indexes
CREATE INDEX idx_reservation_customer ON reservations(customer_id);
CREATE INDEX idx_reservation_time ON reservations(reservation_time);
CREATE INDEX idx_reservation_status ON reservations(status);

-- Loyalty indexes
CREATE INDEX idx_loyalty_customer ON loyalty_transactions(customer_id);
CREATE INDEX idx_loyalty_created ON loyalty_transactions(created_at);

-- Payment indexes
CREATE INDEX idx_payment_order ON payments(order_id);
CREATE INDEX idx_payment_status ON payments(status);
