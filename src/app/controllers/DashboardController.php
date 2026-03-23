<?php

/**
 * Dashboard Controller
 * 
 * API thống kê cho dashboard (Admin/Staff)
 */

class DashboardController extends Controller {
    private $orderRepo;
    private $customerRepo;
    private $productRepo;
    private $tableRepo;
    private $voucherRepo;
    private $db;
    
    public function __construct() {
        $this->orderRepo = new OrderRepository();
        $this->customerRepo = new CustomerRepository();
        $this->productRepo = new ProductRepository();
        $this->tableRepo = new TableRepository();
        $this->voucherRepo = new VoucherRepository();
        $this->db = Database::getInstance();
    }
    
    /**
     * GET /dashboard/overview
     * Tổng quan: Doanh thu, đơn hàng, khách hàng
     */
    public function overview() {
        try {
            $this->requireRole(['ADMIN', 'STAFF']);
            
            // Lấy tham số thời gian (mặc định: tháng này)
            $period = $this->getQuery('period', 'month'); // today, week, month, year
            $dateRange = $this->getDateRange($period);
            
            // 1. Doanh thu
            $revenue = $this->getRevenue($dateRange);
            
            // 2. Số đơn hàng
            $orders = $this->getOrdersCount($dateRange);
            
            // 3. Số khách hàng
            $customers = $this->getCustomersCount($dateRange);
            
            // 4. Sản phẩm bán chạy
            $topProducts = $this->getTopProducts($dateRange, 5);
            
            // 5. Trạng thái bàn
            $tableStatus = $this->getTableStatus();
            
            $this->success([
                'period' => $period,
                'date_range' => $dateRange,
                'revenue' => $revenue,
                'orders' => $orders,
                'customers' => $customers,
                'top_products' => $topProducts,
                'table_status' => $tableStatus
            ]);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /dashboard/revenue-chart
     * Biểu đồ doanh thu theo ngày/tuần/tháng
     */
    public function revenueChart() {
        try {
            $this->requireRole(['ADMIN', 'STAFF']);
            
            $period = $this->getQuery('period', 'month'); // week, month, year
            $groupBy = $this->getQuery('group_by', 'day'); // day, week, month
            
            $dateRange = $this->getDateRange($period);
            
            $sql = "SELECT 
                        DATE(completed_at) as date,
                        COUNT(*) as order_count,
                        SUM(final_amount) as revenue
                    FROM orders
                    WHERE status = 'COMPLETED'
                    AND completed_at BETWEEN :from_date AND :to_date
                    GROUP BY DATE(completed_at)
                    ORDER BY date ASC";
            
            $data = $this->db->fetchAll($sql, [
                ':from_date' => $dateRange['from'],
                ':to_date' => $dateRange['to']
            ]);
            
            // Format data
            $chartData = array_map(function($row) {
                return [
                    'date' => $row['date'],
                    'order_count' => (int)$row['order_count'],
                    'revenue' => (float)$row['revenue']
                ];
            }, $data);
            
            $this->success([
                'period' => $period,
                'group_by' => $groupBy,
                'date_range' => $dateRange,
                'data' => $chartData
            ]);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /dashboard/customer-stats
     * Thống kê khách hàng: Mới/Cũ, Tích điểm
     */
    public function customerStats() {
        try {
            $this->requireRole(['ADMIN', 'STAFF']);
            
            $period = $this->getQuery('period', 'month');
            $dateRange = $this->getDateRange($period);
            
            // Khách hàng mới
            $newCustomersSql = "SELECT COUNT(*) as count 
                                FROM customers 
                                WHERE created_at BETWEEN :from_date AND :to_date";
            
            $newCustomers = $this->db->fetchOne($newCustomersSql, [
                ':from_date' => $dateRange['from'],
                ':to_date' => $dateRange['to']
            ]);
            
            // Khách hàng quay lại (có > 1 đơn)
            $returningCustomersSql = "SELECT COUNT(DISTINCT customer_id) as count
                                      FROM orders
                                      WHERE customer_id IS NOT NULL
                                      AND completed_at BETWEEN :from_date AND :to_date
                                      AND customer_id IN (
                                          SELECT customer_id 
                                          FROM orders 
                                          WHERE customer_id IS NOT NULL
                                          GROUP BY customer_id 
                                          HAVING COUNT(*) > 1
                                      )";
            
            $returningCustomers = $this->db->fetchOne($returningCustomersSql, [
                ':from_date' => $dateRange['from'],
                ':to_date' => $dateRange['to']
            ]);
            
            // Top khách hàng theo điểm
            $topCustomersSql = "SELECT c.id, c.name, c.phone, c.points,
                                       COUNT(o.id) as order_count,
                                       SUM(o.final_amount) as total_spent
                                FROM customers c
                                LEFT JOIN orders o ON c.id = o.customer_id AND o.status = 'COMPLETED'
                                GROUP BY c.id
                                ORDER BY c.points DESC
                                LIMIT 10";
            
            $topCustomers = $this->db->fetchAll($topCustomersSql);
            
            $this->success([
                'period' => $period,
                'date_range' => $dateRange,
                'new_customers' => (int)($newCustomers['count'] ?? 0),
                'returning_customers' => (int)($returningCustomers['count'] ?? 0),
                'top_customers' => $topCustomers
            ]);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /dashboard/product-stats
     * Thống kê sản phẩm: Bán chạy, tồn kho thấp
     */
    public function productStats() {
        try {
            $this->requireRole(['ADMIN', 'STAFF']);
            
            $period = $this->getQuery('period', 'month');
            $dateRange = $this->getDateRange($period);
            
            // Top sản phẩm bán chạy
            $topProducts = $this->getTopProducts($dateRange, 10);
            
            // Sản phẩm tồn kho thấp
            $lowStockSql = "SELECT p.*, c.name as category_name
                            FROM products p
                            LEFT JOIN categories c ON p.category_id = c.id
                            WHERE p.stock_quantity <= p.min_stock
                            AND p.status = 1
                            ORDER BY p.stock_quantity ASC
                            LIMIT 10";
            
            $lowStock = $this->db->fetchAll($lowStockSql);
            
            // Thống kê theo danh mục
            $categorySql = "SELECT c.name as category,
                                   COUNT(DISTINCT p.id) as product_count,
                                   COALESCE(SUM(oi.quantity), 0) as total_sold,
                                   COALESCE(SUM(oi.subtotal), 0) as revenue
                            FROM categories c
                            LEFT JOIN products p ON c.id = p.category_id
                            LEFT JOIN order_items oi ON p.id = oi.product_id
                            LEFT JOIN orders o ON oi.order_id = o.id 
                                AND o.status = 'COMPLETED'
                                AND o.completed_at BETWEEN :from_date AND :to_date
                            GROUP BY c.id
                            ORDER BY revenue DESC";
            
            $categoryStats = $this->db->fetchAll($categorySql, [
                ':from_date' => $dateRange['from'],
                ':to_date' => $dateRange['to']
            ]);
            
            $this->success([
                'period' => $period,
                'date_range' => $dateRange,
                'top_products' => $topProducts,
                'low_stock' => $lowStock,
                'category_stats' => $categoryStats
            ]);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * GET /dashboard/voucher-stats
     * Thống kê voucher: Được dùng nhiều nhất
     */
    public function voucherStats() {
        try {
            $this->requireRole(['ADMIN', 'STAFF']);
            
            $period = $this->getQuery('period', 'month');
            $dateRange = $this->getDateRange($period);
            
            $sql = "SELECT v.id, v.code, v.discount_type, v.discount_value,
                           COUNT(o.id) as usage_count,
                           SUM(o.voucher_discount) as total_discount
                    FROM vouchers v
                    LEFT JOIN orders o ON v.id = o.voucher_id 
                        AND o.status = 'COMPLETED'
                        AND o.completed_at BETWEEN :from_date AND :to_date
                    WHERE v.status = 1
                    GROUP BY v.id
                    ORDER BY usage_count DESC
                    LIMIT 10";
            
            $data = $this->db->fetchAll($sql, [
                ':from_date' => $dateRange['from'],
                ':to_date' => $dateRange['to']
            ]);
            
            $this->success([
                'period' => $period,
                'date_range' => $dateRange,
                'vouchers' => $data
            ]);
            
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    
    // ========== HELPER METHODS ==========
    
    private function getDateRange($period) {
        $now = date('Y-m-d H:i:s');
        
        switch ($period) {
            case 'today':
                return [
                    'from' => date('Y-m-d 00:00:00'),
                    'to' => date('Y-m-d 23:59:59')
                ];
            
            case 'week':
                return [
                    'from' => date('Y-m-d 00:00:00', strtotime('monday this week')),
                    'to' => date('Y-m-d 23:59:59', strtotime('sunday this week'))
                ];
            
            case 'month':
                return [
                    'from' => date('Y-m-01 00:00:00'),
                    'to' => date('Y-m-t 23:59:59')
                ];
            
            case 'year':
                return [
                    'from' => date('Y-01-01 00:00:00'),
                    'to' => date('Y-12-31 23:59:59')
                ];
            
            default:
                return [
                    'from' => date('Y-m-01 00:00:00'),
                    'to' => date('Y-m-t 23:59:59')
                ];
        }
    }
    
    private function getRevenue($dateRange) {
        $sql = "SELECT 
                    COALESCE(SUM(final_amount), 0) as current_revenue,
                    COUNT(*) as current_orders
                FROM orders
                WHERE status = 'COMPLETED'
                AND completed_at BETWEEN :from_date AND :to_date";
        
        $current = $this->db->fetchOne($sql, [
            ':from_date' => $dateRange['from'],
            ':to_date' => $dateRange['to']
        ]);
        
        // So sánh với kỳ trước
        $previousRange = $this->getPreviousPeriod($dateRange);
        $previous = $this->db->fetchOne($sql, [
            ':from_date' => $previousRange['from'],
            ':to_date' => $previousRange['to']
        ]);
        
        $currentRevenue = (float)($current['current_revenue'] ?? 0);
        $previousRevenue = (float)($previous['current_revenue'] ?? 0);
        
        $change = 0;
        if ($previousRevenue > 0) {
            $change = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
        }
        
        return [
            'current' => $currentRevenue,
            'previous' => $previousRevenue,
            'change_percent' => round($change, 1)
        ];
    }
    
    private function getOrdersCount($dateRange) {
        $sql = "SELECT COUNT(*) as count
                FROM orders
                WHERE status = 'COMPLETED'
                AND completed_at BETWEEN :from_date AND :to_date";
        
        $current = $this->db->fetchOne($sql, [
            ':from_date' => $dateRange['from'],
            ':to_date' => $dateRange['to']
        ]);
        
        $previousRange = $this->getPreviousPeriod($dateRange);
        $previous = $this->db->fetchOne($sql, [
            ':from_date' => $previousRange['from'],
            ':to_date' => $previousRange['to']
        ]);
        
        $currentCount = (int)($current['count'] ?? 0);
        $previousCount = (int)($previous['count'] ?? 0);
        
        $change = 0;
        if ($previousCount > 0) {
            $change = (($currentCount - $previousCount) / $previousCount) * 100;
        }
        
        return [
            'current' => $currentCount,
            'previous' => $previousCount,
            'change_percent' => round($change, 1)
        ];
    }
    
    private function getCustomersCount($dateRange) {
        $sql = "SELECT COUNT(DISTINCT customer_id) as count
                FROM orders
                WHERE customer_id IS NOT NULL
                AND status = 'COMPLETED'
                AND completed_at BETWEEN :from_date AND :to_date";
        
        $current = $this->db->fetchOne($sql, [
            ':from_date' => $dateRange['from'],
            ':to_date' => $dateRange['to']
        ]);
        
        $previousRange = $this->getPreviousPeriod($dateRange);
        $previous = $this->db->fetchOne($sql, [
            ':from_date' => $previousRange['from'],
            ':to_date' => $previousRange['to']
        ]);
        
        $currentCount = (int)($current['count'] ?? 0);
        $previousCount = (int)($previous['count'] ?? 0);
        
        $change = 0;
        if ($previousCount > 0) {
            $change = (($currentCount - $previousCount) / $previousCount) * 100;
        }
        
        return [
            'current' => $currentCount,
            'previous' => $previousCount,
            'change_percent' => round($change, 1)
        ];
    }
    
    private function getTopProducts($dateRange, $limit = 5) {
        $sql = "SELECT p.id, p.name, p.price, p.image_url,
                       c.name as category_name,
                       SUM(oi.quantity) as total_sold,
                       SUM(oi.subtotal) as revenue
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.status = 'COMPLETED'
                AND o.completed_at BETWEEN :from_date AND :to_date
                GROUP BY p.id
                ORDER BY total_sold DESC
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, [
            ':from_date' => $dateRange['from'],
            ':to_date' => $dateRange['to'],
            ':limit' => $limit
        ]);
    }
    
    private function getTableStatus() {
        $sql = "SELECT status, COUNT(*) as count
                FROM tables
                GROUP BY status";
        
        $data = $this->db->fetchAll($sql);
        
        $result = [
            'AVAILABLE' => 0,
            'OCCUPIED' => 0,
            'RESERVED' => 0,
            'MAINTENANCE' => 0
        ];
        
        foreach ($data as $row) {
            $result[$row['status']] = (int)$row['count'];
        }
        
        return $result;
    }
    
    private function getPreviousPeriod($dateRange) {
        $from = new DateTime($dateRange['from']);
        $to = new DateTime($dateRange['to']);
        $diff = $from->diff($to)->days + 1;
        
        $previousFrom = clone $from;
        $previousFrom->modify("-{$diff} days");
        
        $previousTo = clone $from;
        $previousTo->modify('-1 day');
        
        return [
            'from' => $previousFrom->format('Y-m-d H:i:s'),
            'to' => $previousTo->format('Y-m-d 23:59:59')
        ];
    }
}
