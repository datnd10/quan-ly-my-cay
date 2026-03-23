<?php

/**
 * Order Service
 * 
 * Business logic cho orders
 */

class OrderService {
    private $orderRepo;
    private $productRepo;
    private $tableRepo;
    private $customerRepo;
    private $voucherService;
    private $db;
    
    public function __construct() {
        $this->orderRepo = new OrderRepository();
        $this->productRepo = new ProductRepository();
        $this->tableRepo = new TableRepository();
        $this->customerRepo = new CustomerRepository();
        $this->voucherService = new VoucherService();
        $this->db = Database::getInstance();
    }
    
    /**
     * Lấy danh sách orders
     */
    public function getOrders($page, $perPage, $filters) {
        return $this->orderRepo->getOrders($page, $perPage, $filters);
    }
    
    /**
     * Lấy order theo ID
     */
    public function getOrderById($id) {
        $order = $this->orderRepo->findById($id);
        
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        return $order;
    }
    
    /**
     * Tạo order mới (cho bàn)
     */
    public function createOrder($data, $userId) {
        // Validate
        $errors = $this->validateCreateOrder($data);
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
        
        // Kiểm tra bàn
        if (!empty($data['table_id'])) {
            $table = $this->tableRepo->findById($data['table_id']);
            
            if (!$table) {
                throw new Exception('Không tìm thấy bàn');
            }
            
            // Kiểm tra bàn đã có order chưa
            $existingOrder = $this->orderRepo->findActiveByTable($data['table_id']);
            if ($existingOrder) {
                throw new Exception('Bàn này đang có đơn hàng. Vui lòng chọn bàn khác hoặc gọi thêm món vào đơn hiện tại.');
            }
            
            // Kiểm tra trạng thái bàn
            if ($table->status !== 'AVAILABLE') {
                throw new Exception('Bàn không khả dụng');
            }
        }
        
        // Kiểm tra customer (nếu có)
        if (!empty($data['customer_id'])) {
            $customer = $this->customerRepo->findById($data['customer_id']);
            if (!$customer) {
                throw new Exception('Không tìm thấy khách hàng');
            }
        }
        
        $this->db->beginTransaction();
        
        try {
            // Tạo order với status PENDING (không dùng DRAFT cho in-restaurant)
            $order = $this->orderRepo->create([
                'customer_id' => $data['customer_id'] ?? null,
                'table_id' => $data['table_id'] ?? null,
                'status' => Order::STATUS_PENDING,
                'total_amount' => 0,
                'discount_amount' => 0,
                'final_amount' => 0
            ]);
            
            // Cập nhật trạng thái bàn sang OCCUPIED
            if (!empty($data['table_id'])) {
                $this->tableRepo->updateStatus($data['table_id'], 'OCCUPIED');
            }
            
            // Log status history
            $this->orderRepo->logStatusChange(
                $order->id,
                null,
                Order::STATUS_PENDING,
                $userId,
                'Tạo đơn hàng mới'
            );
            
            $this->db->commit();
            
            return $order;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    
    /**
     * Update items của order (thêm/sửa/xóa tất cả trong 1 API)
     */
    public function updateItems($orderId, $items, $userId) {
        // Validate
        if (!is_array($items)) {
            throw new ValidationException('Dữ liệu không hợp lệ', ['items' => 'Items phải là array']);
        }
        
        // Lấy order
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        // Kiểm tra order có thể sửa không
        if (!$order->canEdit()) {
            throw new Exception('Không thể sửa đơn hàng đã xác nhận');
        }
        
        // Validate từng item
        $errors = [];
        $productIds = [];
        
        foreach ($items as $index => $item) {
            if (empty($item['product_id'])) {
                $errors["items.$index.product_id"] = 'Sản phẩm không được để trống';
            } else {
                $productIds[] = $item['product_id'];
            }
            
            if (!isset($item['quantity']) || $item['quantity'] < 1) {
                $errors["items.$index.quantity"] = 'Số lượng phải lớn hơn 0';
            }
        }
        
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
        
        // Lấy thông tin products
        $products = [];
        foreach ($productIds as $productId) {
            $product = $this->productRepo->findById($productId);
            
            if (!$product) {
                throw new Exception("Không tìm thấy sản phẩm ID: $productId");
            }
            
            if ($product->status !== 'ACTIVE') {
                throw new Exception("Sản phẩm '{$product->name}' không khả dụng");
            }
            
            $products[$productId] = $product;
        }
        
        $this->db->beginTransaction();
        
        try {
            // Xóa tất cả items cũ
            $this->orderRepo->deleteAllItems($orderId);
            
            // Thêm items mới
            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                
                $this->orderRepo->addItem(
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $product->price
                );
            }
            
            // Recalculate order totals
            $this->recalculateOrder($orderId);
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Áp dụng voucher
     */
    public function applyVoucher($orderId, $voucherCode, $userId) {
        // Lấy order
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        // Kiểm tra order có thể sửa không
        if (!$order->canEdit()) {
            throw new Exception('Không thể áp dụng voucher cho đơn hàng đã xác nhận');
        }
        
        // Kiểm tra đã có voucher chưa
        if ($order->voucher_id) {
            throw new Exception('Đơn hàng đã có voucher. Vui lòng hủy voucher hiện tại trước.');
        }
        
        // Validate voucher
        $result = $this->voucherService->validateAndCalculate($voucherCode, $order->total_amount);
        $voucher = $result['voucher'];
        $discount = $result['discount'];
        
        $this->db->beginTransaction();
        
        try {
            // Apply voucher
            $this->voucherService->applyVoucher($voucher->id);
            
            // Update order
            $this->orderRepo->update($orderId, [
                'voucher_id' => $voucher->id,
                'voucher_discount' => $discount,
                'discount_amount' => $discount,
                'final_amount' => max(0, $order->total_amount - $discount)
            ]);
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Hủy voucher
     */
    public function removeVoucher($orderId, $userId) {
        // Lấy order
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        // Kiểm tra order có thể sửa không
        if (!$order->canEdit()) {
            throw new Exception('Không thể hủy voucher cho đơn hàng đã xác nhận');
        }
        
        // Kiểm tra có voucher không
        if (!$order->voucher_id) {
            throw new Exception('Đơn hàng chưa có voucher');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Revert voucher
            $this->voucherService->revertVoucher($order->voucher_id);
            
            // Update order
            $this->orderRepo->update($orderId, [
                'voucher_id' => null,
                'voucher_discount' => 0,
                'discount_amount' => 0,
                'final_amount' => $order->total_amount
            ]);
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Xác nhận đơn hàng (PENDING → CONFIRMED)
     */
    public function confirmOrder($orderId, $userId) {
        // Lấy order
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        // Kiểm tra status
        if (!$order->canConfirm()) {
            throw new Exception('Chỉ có thể xác nhận đơn hàng PENDING');
        }
        
        // Kiểm tra có items không
        if (empty($order->items)) {
            throw new Exception('Đơn hàng phải có ít nhất 1 món');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Trừ stock cho từng item
            foreach ($order->items as $item) {
                $this->productRepo->updateStock($item->product_id, 'subtract', $item->quantity);
            }
            
            // Update status
            $this->orderRepo->updateStatus($orderId, Order::STATUS_CONFIRMED);
            
            // Log history
            $this->orderRepo->logStatusChange(
                $orderId,
                Order::STATUS_PENDING,
                Order::STATUS_CONFIRMED,
                $userId,
                'Xác nhận đơn hàng'
            );
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    
    /**
     * Thay đổi trạng thái order
     */
    public function updateStatus($orderId, $newStatus, $userId, $note = null) {
        // Lấy order
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        $oldStatus = $order->status;
        
        // Validate status transition
        $this->validateStatusTransition($oldStatus, $newStatus);
        
        $this->db->beginTransaction();
        
        try {
            // Update status
            $this->orderRepo->updateStatus($orderId, $newStatus, $note);
            
            // Xử lý logic đặc biệt cho từng status
            if ($newStatus === Order::STATUS_COMPLETED) {
                // Cộng điểm cho customer (nếu có)
                if ($order->customer_id) {
                    $this->addPointsToCustomer($order);
                }
                
                // Free bàn
                if ($order->table_id) {
                    $this->tableRepo->updateStatus($order->table_id, 'AVAILABLE');
                }
            }
            
            // Log history
            $this->orderRepo->logStatusChange(
                $orderId,
                $oldStatus,
                $newStatus,
                $userId,
                $note
            );
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Hủy đơn hàng
     */
    public function cancelOrder($orderId, $userId, $reason = null) {
        // Lấy order
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        // Kiểm tra có thể hủy không
        if (!$order->canCancel()) {
            throw new Exception('Không thể hủy đơn hàng này');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Hoàn stock nếu đã confirm
            if ($order->status === Order::STATUS_CONFIRMED || 
                $order->status === Order::STATUS_PREPARING ||
                $order->status === Order::STATUS_READY) {
                foreach ($order->items as $item) {
                    $this->productRepo->updateStock($item->product_id, 'add', $item->quantity);
                }
            }
            
            // Revert voucher nếu có
            if ($order->voucher_id) {
                $this->voucherService->revertVoucher($order->voucher_id);
            }
            
            // Free bàn
            if ($order->table_id) {
                $this->tableRepo->updateStatus($order->table_id, 'AVAILABLE');
            }
            
            // Update status
            $this->orderRepo->updateStatus($orderId, Order::STATUS_CANCELLED);
            
            // Log history
            $this->orderRepo->logStatusChange(
                $orderId,
                $order->status,
                Order::STATUS_CANCELLED,
                $userId,
                $reason ?? 'Hủy đơn hàng'
            );
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Xóa order (chỉ DRAFT/PENDING chưa có items)
     */
    public function deleteOrder($orderId, $userId) {
        // Lấy order
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        // Chỉ cho phép xóa DRAFT hoặc PENDING chưa có items
        if (!in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_PENDING])) {
            throw new Exception('Chỉ có thể xóa đơn hàng DRAFT hoặc PENDING');
        }
        
        if (!empty($order->items)) {
            throw new Exception('Không thể xóa đơn hàng đã có món. Vui lòng hủy đơn thay vì xóa.');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Free bàn nếu có
            if ($order->table_id) {
                $this->tableRepo->updateStatus($order->table_id, 'AVAILABLE');
            }
            
            // Delete order
            $this->orderRepo->delete($orderId);
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Lấy lịch sử status
     */
    public function getStatusHistory($orderId) {
        // Kiểm tra order tồn tại
        $order = $this->orderRepo->findById($orderId, false);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        return $this->orderRepo->getStatusHistory($orderId);
    }
    
    /**
     * Recalculate order totals
     */
    private function recalculateOrder($orderId) {
        $order = $this->orderRepo->findById($orderId);
        
        // Tính total_amount từ items
        $totalAmount = 0;
        foreach ($order->items as $item) {
            $totalAmount += $item->subtotal;
        }
        
        // Tính lại discount nếu có voucher
        $discount = 0;
        if ($order->voucher_id) {
            $voucher = $this->voucherService->getVoucherById($order->voucher_id);
            $discount = $voucher->calculateDiscount($totalAmount);
        }
        
        // Update order
        $this->orderRepo->update($orderId, [
            'total_amount' => $totalAmount,
            'discount_amount' => $discount,
            'voucher_discount' => $discount,
            'final_amount' => max(0, $totalAmount - $discount)
        ]);
    }
    
    /**
     * Validate status transition
     */
    private function validateStatusTransition($oldStatus, $newStatus) {
        $validTransitions = [
            Order::STATUS_DRAFT => [Order::STATUS_PENDING, Order::STATUS_CANCELLED],
            Order::STATUS_PENDING => [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLED],
            Order::STATUS_CONFIRMED => [Order::STATUS_PREPARING, Order::STATUS_CANCELLED],
            Order::STATUS_PREPARING => [Order::STATUS_READY, Order::STATUS_CANCELLED],
            Order::STATUS_READY => [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
        ];
        
        if (!isset($validTransitions[$oldStatus]) || !in_array($newStatus, $validTransitions[$oldStatus])) {
            throw new Exception("Không thể chuyển từ trạng thái $oldStatus sang $newStatus");
        }
    }
    
    /**
     * Cộng điểm cho customer
     */
    private function addPointsToCustomer($order) {
        // Tính điểm: 1 điểm / 10,000 VND
        $points = floor($order->final_amount / 10000);
        
        if ($points > 0) {
            $customerRepo = new CustomerRepository();
            $pointRepo = new PointTransactionRepository();
            
            // Cộng điểm
            $customerRepo->updatePoints($order->customer_id, 'add', $points);
            
            // Log transaction
            $pointRepo->create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'type' => 'EARN',
                'points' => $points,
                'description' => "Tích điểm từ đơn hàng #$order->id"
            ]);
        }
    }
    
    /**
     * Validate create order
     */
    private function validateCreateOrder($data) {
        $errors = [];
        
        // Table ID (optional nhưng nếu có thì phải hợp lệ)
        if (isset($data['table_id']) && empty($data['table_id'])) {
            $errors['table_id'] = 'Bàn không hợp lệ';
        }
        
        return $errors;
    }
}
