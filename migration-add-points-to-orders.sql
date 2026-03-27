-- =============================================
-- Migration: Thêm cột points vào bảng orders
-- Dùng cho use case: Dùng điểm thanh toán
-- =============================================

ALTER TABLE orders
    ADD COLUMN points_used INT DEFAULT 0 COMMENT 'Số điểm khách đã dùng để thanh toán' AFTER voucher_discount,
    ADD COLUMN points_discount DECIMAL(12,2) DEFAULT 0 COMMENT 'Số tiền giảm từ điểm (points_used * 10000)' AFTER points_used;

-- Tạo index cho tra cứu nhanh
CREATE INDEX idx_order_points_used ON orders(points_used);
