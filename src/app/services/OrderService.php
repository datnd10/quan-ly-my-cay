<?php

/**
 * Order Service - SIMPLIFIED
 * 
 * Business logic cho orders (đơn giản hóa)
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
        
        // Lấy items của order
        $items = $this->orderRepo->getOrderItems($id);
        $order->items = $items;
        
        return $order;
    }
    
    /**
     * Lấy order đang ACTIVE của 1 bàn
     * Dùng để gọi thêm món cho bàn đang ăn
     */
    public function getActiveOrderByTable($tableId) {
        $order = $this->orderRepo->findActiveByTable($tableId);
        
        if (!$order) {
            throw new Exception('Bàn này chưa có order hoặc order đã hoàn thành');
        }
        
        // Lấy items của order
        $items = $this->orderRepo->getOrderItems($order->id);
        $order->items = $items;
        
        return $order;
    }
    
    /**
     * Tạo order mới - Có thể tạo kèm items luôn
     */
    public function createOrder($data, $userId) {
        error_log("OrderService->createOrder() - Input data: " . json_encode($data));
        
        // Validate table
        if (!empty($data['table_id'])) {
            error_log("Validating table_id: " . $data['table_id']);
            $table = $this->tableRepo->findById($data['table_id']);
            
            if (!$table) {
                throw new Exception('Không tìm thấy bàn');
            }
            
            // Kiểm tra bàn đã có order chưa
            $existingOrder = $this->orderRepo->findActiveByTable($data['table_id']);
            if ($existingOrder) {
                throw new Exception('Bàn này đang có đơn hàng');
            }
            
            if ($table->status !== 'AVAILABLE') {
                throw new Exception('Bàn không khả dụng');
            }
        }
        
        // Customer không bắt buộc khi tạo order, sẽ thêm khi thanh toán
        
        $this->db->beginTransaction();
        
        try {
            // Tạo order với status ACTIVE
            $order = $this->orderRepo->create([
                'customer_id' => $data['customer_id'] ?? null,
                'table_id' => $data['table_id'] ?? null,
                'status' => Order::STATUS_ACTIVE,
                'total_amount' => 0,
                'discount_amount' => 0,
                'final_amount' => 0
            ]);
            
            // Cập nhật bàn sang OCCUPIED
            if (!empty($data['table_id'])) {
                $this->tableRepo->updateStatus($data['table_id'], 'OCCUPIED');
            }
            
            // Thêm items nếu có
            if (!empty($data['items']) && is_array($data['items'])) {
                error_log("Adding " . count($data['items']) . " items to order");
                
                foreach ($data['items'] as $item) {
                    error_log("Processing item: " . json_encode($item));
                    
                    if (empty($item['product_id']) || empty($item['quantity'])) {
                        error_log("Skipping item - missing product_id or quantity");
                        continue;
                    }
                    
                    $product = $this->productRepo->findById($item['product_id']);
                    if (!$product || $product->status !== 1) {
                        error_log("Skipping item - product not found or inactive: " . $item['product_id']);
                        continue;
                    }
                    
                    // Kiểm tra tồn kho
                    if ($product->stock_quantity < $item['quantity']) {
                        throw new Exception("Sản phẩm '{$product->name}' không đủ số lượng (còn {$product->stock_quantity})");
                    }
                    
                    error_log("Adding item: product_id={$item['product_id']}, quantity={$item['quantity']}, price={$product->price}");
                    
                    $this->orderRepo->addItem(
                        $order->id,
                        $item['product_id'],
                        $item['quantity'],
                        $product->price
                    );
                    
                    // Trừ stock ngay khi thêm item
                    $this->productRepo->adjustStock($item['product_id'], -$item['quantity']);
                    error_log("Deducted stock: product_id={$item['product_id']}, quantity={$item['quantity']}");
                }
                
                // Recalculate
                $this->recalculateOrder($order->id);
            } else {
                error_log("No items to add - items: " . json_encode($data['items'] ?? null));
            }
            
            $this->db->commit();
            
            return $this->orderRepo->findById($order->id);
            
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
            throw new Exception('Không thể sửa đơn hàng đã hoàn thành');
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
            
            
            $products[$productId] = $product;
        }
        
        // Validate stock cho từng item
        foreach ($items as $item) {
            $product = $products[$item['product_id']];
            
            if ($product->stock_quantity < $item['quantity']) {
                throw new Exception("Sản phẩm '{$product->name}' không đủ số lượng (còn {$product->stock_quantity})");
            }
        }
        $this->db->beginTransaction();
        
        try {
            // Lấy items cũ để hoàn lại stock
            $oldItems = $order->items;
            
            // Hoàn lại stock của items cũ
            foreach ($oldItems as $oldItem) {
                // OrderItem có thể là object hoặc array, xử lý cả 2 trường hợp
                $productId = is_array($oldItem) ? $oldItem['product_id'] : $oldItem->product_id;
                $quantity = is_array($oldItem) ? $oldItem['quantity'] : $oldItem->quantity;
                
                $this->productRepo->adjustStock($productId, $quantity);
                error_log("Restored stock: product_id={$productId}, quantity={$quantity}");
            }
            
            // Xóa tất cả items cũ
            $this->orderRepo->deleteAllItems($orderId);
            
            // Thêm items mới và trừ stock
            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                
                $this->orderRepo->addItem(
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $product->price
                );
                
                // Trừ stock mới
                $this->productRepo->adjustStock($item['product_id'], -$item['quantity']);
                error_log("Deducted stock: product_id={$item['product_id']}, quantity={$item['quantity']}");
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
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        if (!$order->canEdit()) {
            throw new Exception('Không thể áp dụng voucher cho đơn hàng đã hoàn thành');
        }
        
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
            $pointsDiscount = (float)$order->points_discount;
            $totalDiscount = $discount + $pointsDiscount;
            $this->orderRepo->update($orderId, [
                'voucher_id' => $voucher->id,
                'voucher_discount' => $discount,
                'discount_amount' => $totalDiscount,
                'final_amount' => max(0, $order->total_amount - $totalDiscount)
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
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        if (!$order->canEdit()) {
            throw new Exception('Không thể hủy voucher cho đơn hàng đã hoàn thành');
        }
        
        if (!$order->voucher_id) {
            throw new Exception('Đơn hàng chưa có voucher');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Revert voucher
            $this->voucherService->revertVoucher($order->voucher_id);
            
            // Update order
            $pointsDiscount = (float)$order->points_discount;
            $this->orderRepo->update($orderId, [
                'voucher_id' => null,
                'voucher_discount' => 0,
                'discount_amount' => $pointsDiscount,
                'final_amount' => max(0, $order->total_amount - $pointsDiscount)
            ]);
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Áp dụng điểm tích lũy vào đơn hàng
     * Mỗi điểm = giảm 1,000 VND
     */
    public function applyPoint($orderId, $points, $userId) {
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        if (!$order->canEdit()) {
            throw new Exception('Không thể áp dụng điểm cho đơn hàng đã hoàn thành');
        }
        
        if ($order->points_used > 0) {
            throw new Exception('Đơn hàng đã sử dụng điểm. Vui lòng hủy điểm hiện tại trước.');
        }
        
        if (!$order->customer_id) {
            throw new Exception('Đơn hàng chưa có khách hàng. Vui lòng gán khách hàng trước.');
        }
        
        $points = (int)$points;
        if ($points <= 0) {
            throw new Exception('Số điểm phải lớn hơn 0');
        }
        
        // Kiểm tra khách hàng có đủ điểm không
        $customerRepo = new CustomerRepository();
        $customer = $customerRepo->findById($order->customer_id);
        if (!$customer) {
            throw new Exception('Không tìm thấy khách hàng');
        }
        
        $customerPoints = is_array($customer) ? (int)$customer['points'] : (int)$customer->points;
        if ($customerPoints < $points) {
            throw new Exception("Khách hàng chỉ có {$customerPoints} điểm, không đủ để sử dụng {$points} điểm");
        }
        
        // Tính giảm giá từ điểm (1 điểm = 1,000 VND)
        $pointsDiscount = $points * 1000;
        
        // Không cho giảm quá tổng tiền sau voucher
        $voucherDiscount = (float)$order->voucher_discount;
        $maxDiscount = $order->total_amount - $voucherDiscount;
        if ($pointsDiscount > $maxDiscount) {
            throw new Exception('Số tiền giảm từ điểm vượt quá tổng tiền đơn hàng. Tối đa có thể dùng ' . floor($maxDiscount / 1000) . ' điểm.');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Trừ điểm khách hàng
            $customerRepo->updatePoints($order->customer_id, -$points);
            
            // Lấy số dư sau khi trừ
            $customerAfter = $customerRepo->findById($order->customer_id);
            $balanceAfter = is_array($customerAfter) ? (int)$customerAfter['points'] : (int)$customerAfter->points;
            
            // Log point transaction
            $pointRepo = new PointTransactionRepository();
            $pointRepo->create([
                'customer_id' => $order->customer_id,
                'points' => -$points,
                'type' => 'REDEEM',
                'description' => "Sử dụng điểm cho đơn hàng #{$order->id}",
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'balance_after' => $balanceAfter
            ]);
            
            // Update order
            $totalDiscount = $voucherDiscount + $pointsDiscount;
            $this->orderRepo->update($orderId, [
                'points_used' => $points,
                'points_discount' => $pointsDiscount,
                'discount_amount' => $totalDiscount,
                'final_amount' => max(0, $order->total_amount - $totalDiscount)
            ]);
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Hủy sử dụng điểm cho đơn hàng
     */
    public function removePoint($orderId, $userId) {
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        if (!$order->canEdit()) {
            throw new Exception('Không thể hủy điểm cho đơn hàng đã hoàn thành');
        }
        
        if ($order->points_used <= 0) {
            throw new Exception('Đơn hàng chưa sử dụng điểm');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Hoàn lại điểm cho khách hàng
            $customerRepo = new CustomerRepository();
            $customerRepo->updatePoints($order->customer_id, $order->points_used);
            
            // Lấy số dư sau khi hoàn
            $customerAfter = $customerRepo->findById($order->customer_id);
            $balanceAfter = is_array($customerAfter) ? (int)$customerAfter['points'] : (int)$customerAfter->points;
            
            // Log point transaction
            $pointRepo = new PointTransactionRepository();
            $pointRepo->create([
                'customer_id' => $order->customer_id,
                'points' => $order->points_used,
                'type' => 'REFUND',
                'description' => "Hoàn điểm từ đơn hàng #{$order->id}",
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'balance_after' => $balanceAfter
            ]);
            
            // Update order
            $voucherDiscount = (float)$order->voucher_discount;
            $this->orderRepo->update($orderId, [
                'points_used' => 0,
                'points_discount' => 0,
                'discount_amount' => $voucherDiscount,
                'final_amount' => max(0, $order->total_amount - $voucherDiscount)
            ]);
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Thanh toán đơn hàng (ACTIVE → COMPLETED)
     */
    public function payOrder($orderId, $paymentData, $userId) {
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        if (!$order->canPay()) {
            throw new Exception('Chỉ có thể thanh toán đơn hàng ACTIVE');
        }
        
        if (empty($order->items)) {
            throw new Exception('Đơn hàng phải có ít nhất 1 món');
        }
        
        // Validate customer nếu có
        if (!empty($paymentData['customer_id'])) {
            $customer = $this->customerRepo->findById($paymentData['customer_id']);
            if (!$customer) {
                throw new Exception('Không tìm thấy khách hàng');
            }
        }
        
        $this->db->beginTransaction();
        
        try {
            // Stock đã được trừ khi tạo order (ACTIVE), không cần trừ lại
            
            // Thêm customer nếu có (khi thanh toán mới biết khách hàng)
            if (!empty($paymentData['customer_id'])) {
                $this->orderRepo->update($orderId, [
                    'customer_id' => $paymentData['customer_id']
                ]);
                
                // Reload order để có customer_id mới
                $order = $this->orderRepo->findById($orderId);
            }
            
            // Update status và payment info
            $this->orderRepo->updateStatus($orderId, Order::STATUS_COMPLETED);
            
            $this->orderRepo->update($orderId, [
                'payment_method' => $paymentData['payment_method'] ?? 'CASH',
                'payment_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Cộng điểm cho customer (nếu có)
            if ($order->customer_id) {
                $this->addPointsToCustomer($order);
            }
            
            // Free bàn
            if ($order->table_id) {
                $this->tableRepo->updateStatus($order->table_id, 'AVAILABLE');
            }
            
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
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        if (!$order->canCancel()) {
            throw new Exception('Không thể hủy đơn hàng này');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Hoàn lại stock cho từng item
            foreach ($order->items as $item) {
                // OrderItem có thể là object hoặc array
                $productId = is_array($item) ? $item['product_id'] : $item->product_id;
                $quantity = is_array($item) ? $item['quantity'] : $item->quantity;
                
                $this->productRepo->adjustStock($productId, $quantity);
                error_log("Restored stock on cancel: product_id={$productId}, quantity={$quantity}");
            }
            
            // Revert voucher nếu có
            if ($order->voucher_id) {
                $this->voucherService->revertVoucher($order->voucher_id);
            }
            
            // Hoàn lại điểm nếu đã sử dụng
            if ($order->points_used > 0) {
                $customerRepo = new CustomerRepository();
                $customerRepo->updatePoints($order->customer_id, $order->points_used);
                
                $customerAfter = $customerRepo->findById($order->customer_id);
                $balanceAfter = is_array($customerAfter) ? (int)$customerAfter['points'] : (int)$customerAfter->points;
                
                $pointRepo = new PointTransactionRepository();
                $pointRepo->create([
                    'customer_id' => $order->customer_id,
                    'points' => $order->points_used,
                    'type' => 'REFUND',
                    'description' => "Hoàn điểm do hủy đơn hàng #{$order->id}",
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'balance_after' => $balanceAfter
                ]);
            }
            
            // Free bàn
            if ($order->table_id) {
                $this->tableRepo->updateStatus($order->table_id, 'AVAILABLE');
            }
            
            // Update status
            $this->orderRepo->updateStatus($orderId, Order::STATUS_CANCELLED);
            
            $this->db->commit();
            
            return $this->orderRepo->findById($orderId);
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Xóa order (chỉ ACTIVE chưa có items)
     */
    public function deleteOrder($orderId, $userId) {
        $order = $this->orderRepo->findById($orderId);
        if (!$order) {
            throw new Exception('Không tìm thấy đơn hàng');
        }
        
        if ($order->status !== Order::STATUS_ACTIVE) {
            throw new Exception('Chỉ có thể xóa đơn hàng ACTIVE');
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
        
        // Tính lại points discount nếu có
        $pointsDiscount = (float)$order->points_discount;
        
        // Tổng discount = voucher + points
        $totalDiscount = $discount + $pointsDiscount;
        
        // Update order
        $this->orderRepo->update($orderId, [
            'total_amount' => $totalAmount,
            'voucher_discount' => $discount,
            'discount_amount' => $totalDiscount,
            'final_amount' => max(0, $totalAmount - $totalDiscount)
        ]);
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
            $customerRepo->updatePoints($order->customer_id, $points);
            
            // Lấy số dư điểm SAU KHI đã cộng
            $customer = $customerRepo->findById($order->customer_id);
            $balanceAfter = 0;
            if ($customer) {
                // findById trả về array
                $balanceAfter = is_array($customer) ? (int)$customer['points'] : (int)$customer->points;
            }
            
            // Log transaction
            $pointRepo->create([
                'customer_id' => $order->customer_id,
                'points' => $points,
                'type' => 'EARN',
                'description' => "Tích điểm từ đơn hàng #$order->id",
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'balance_after' => $balanceAfter
            ]);
        }
    }
    
    /**
     * Validate create order
     */
    private function validateCreateOrder($data) {
        $errors = [];
        
        if (isset($data['table_id']) && empty($data['table_id'])) {
            $errors['table_id'] = 'Bàn không hợp lệ';
        }
        
        return $errors;
    }
}
