-- ============================================
-- CLEANUP SCRIPT - Xóa các bảng cũ không dùng
-- ============================================
-- Chạy script này TRƯỚC KHI chạy script.sql mới

-- Tắt foreign key checks tạm thời
SET FOREIGN_KEY_CHECKS = 0;

-- Drop các bảng cũ (nếu tồn tại)
DROP TABLE IF EXISTS inventories;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS loyalty_transactions;
DROP TABLE IF EXISTS order_vouchers;
DROP TABLE IF EXISTS vouchers;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS tables;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS point_transactions;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

-- Bật lại foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Sau khi chạy script này, chạy script.sql để tạo lại các bảng mới
