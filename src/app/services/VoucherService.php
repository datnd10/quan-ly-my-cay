<?php

/**
 * Voucher Service
 * 
 * Business logic cho vouchers
 */

class VoucherService {
    private $voucherRepo;
    
    // Các discount type hợp lệ
    const VALID_DISCOUNT_TYPES = ['PERCENTAGE', 'FIXED'];
    
    public function __construct() {
        $this->voucherRepo = new VoucherRepository();
    }
    
    /**
     * Lấy danh sách vouchers
     */
    public function getVouchers($page, $perPage, $filters) {
        return $this->voucherRepo->getVouchers($page, $perPage, $filters);
    }
    
    /**
     * Lấy danh sách vouchers available cho order amount
     */
    public function getAvailableVouchers($orderAmount, $filters = []) {
        // Lấy tất cả vouchers active
        $result = $this->voucherRepo->getVouchers(1, 1000, $filters);
        $vouchers = $result['vouchers'];
        
        $availableVouchers = [];
        
        foreach ($vouchers as $voucher) {
            // Kiểm tra đơn hàng đủ điều kiện không
            if ($orderAmount >= $voucher->min_order_amount) {
                // Tính discount cho voucher này
                $discount = $voucher->calculateDiscount($orderAmount);
                
                $voucherData = $voucher->toArray();
                $voucherData['discount_amount'] = $discount;
                $voucherData['final_amount'] = max(0, $orderAmount - $discount);
                
                $availableVouchers[] = $voucherData;
            }
        }
        
        // Sắp xếp theo discount giảm dần (voucher giảm nhiều nhất lên đầu)
        usort($availableVouchers, function($a, $b) {
            return $b['discount_amount'] <=> $a['discount_amount'];
        });
        
        return $availableVouchers;
    }
    
    /**
     * Lấy voucher theo ID
     */
    public function getVoucherById($id) {
        $voucher = $this->voucherRepo->findById($id);
        
        if (!$voucher) {
            throw new Exception('Không tìm thấy voucher');
        }
        
        return $voucher;
    }
    
    /**
     * Tạo voucher mới
     */
    public function createVoucher($data) {
        // Validate
        $errors = $this->validateVoucher($data);
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
        
        // Kiểm tra code đã tồn tại chưa
        if ($this->voucherRepo->existsByCode($data['code'])) {
            throw new Exception('Mã voucher đã tồn tại');
        }
        
        return $this->voucherRepo->create($data);
    }
    
    /**
     * Cập nhật voucher
     */
    public function updateVoucher($id, $data) {
        // Kiểm tra voucher tồn tại
        $voucher = $this->voucherRepo->findById($id);
        if (!$voucher) {
            throw new Exception('Không tìm thấy voucher');
        }
        
        // Validate
        $errors = $this->validateVoucher($data, true);
        if (!empty($errors)) {
            throw new ValidationException('Dữ liệu không hợp lệ', $errors);
        }
        
        // Kiểm tra code đã tồn tại chưa (nếu có update)
        if (isset($data['code']) && $this->voucherRepo->existsByCode($data['code'], $id)) {
            throw new Exception('Mã voucher đã tồn tại');
        }
        
        return $this->voucherRepo->update($id, $data);
    }
    
    /**
     * Xóa voucher
     */
    public function deleteVoucher($id) {
        // Kiểm tra voucher tồn tại
        $voucher = $this->voucherRepo->findById($id);
        if (!$voucher) {
            throw new Exception('Không tìm thấy voucher');
        }
        
        // TODO: Kiểm tra voucher có đang được sử dụng trong order nào không
        
        return $this->voucherRepo->delete($id);
    }
    
    /**
     * Validate voucher code và tính discount
     */
    public function validateAndCalculate($code, $orderAmount) {
        // Lấy voucher theo code
        $voucher = $this->voucherRepo->findByCode($code);
        
        if (!$voucher) {
            throw new Exception('Mã voucher không tồn tại');
        }
        
        // Kiểm tra status
        if ($voucher->status != 1) {
            throw new Exception('Voucher đã bị vô hiệu hóa');
        }
        
        // Kiểm tra ngày bắt đầu
        $now = date('Y-m-d H:i:s');
        if ($voucher->start_date && $now < $voucher->start_date) {
            throw new Exception('Voucher chưa đến ngày sử dụng');
        }
        
        // Kiểm tra ngày hết hạn
        if ($voucher->expired_at && $now > $voucher->expired_at) {
            throw new Exception('Voucher đã hết hạn');
        }
        
        // Kiểm tra số lần sử dụng
        if ($voucher->usage_limit > 0 && $voucher->used_count >= $voucher->usage_limit) {
            throw new Exception('Voucher đã hết lượt sử dụng');
        }
        
        // Kiểm tra đơn tối thiểu
        if ($orderAmount < $voucher->min_order_amount) {
            throw new Exception('Đơn hàng chưa đủ điều kiện. Tối thiểu: ' . number_format($voucher->min_order_amount) . ' VND');
        }
        
        // Tính discount
        $discount = $voucher->calculateDiscount($orderAmount);
        
        return [
            'voucher' => $voucher,
            'discount' => $discount,
            'final_amount' => max(0, $orderAmount - $discount)
        ];
    }
    
    /**
     * Apply voucher cho order (tăng used_count)
     */
    public function applyVoucher($voucherId) {
        return $this->voucherRepo->incrementUsedCount($voucherId);
    }
    
    /**
     * Revert voucher khi hủy order (giảm used_count)
     */
    public function revertVoucher($voucherId) {
        return $this->voucherRepo->decrementUsedCount($voucherId);
    }
    
    /**
     * Validate voucher data
     */
    private function validateVoucher($data, $isUpdate = false) {
        $errors = [];
        
        // Code
        if (!$isUpdate || isset($data['code'])) {
            if (empty($data['code'])) {
                $errors['code'] = 'Mã voucher không được để trống';
            } elseif (strlen($data['code']) > 50) {
                $errors['code'] = 'Mã voucher không được vượt quá 50 ký tự';
            } elseif (!preg_match('/^[A-Z0-9_-]+$/', $data['code'])) {
                $errors['code'] = 'Mã voucher chỉ chứa chữ in hoa, số, gạch dưới và gạch ngang';
            }
        }
        
        // Discount type
        if (!$isUpdate || isset($data['discount_type'])) {
            if (empty($data['discount_type'])) {
                $errors['discount_type'] = 'Loại giảm giá không được để trống';
            } elseif (!in_array($data['discount_type'], self::VALID_DISCOUNT_TYPES)) {
                $errors['discount_type'] = 'Loại giảm giá không hợp lệ. Chỉ chấp nhận: ' . implode(', ', self::VALID_DISCOUNT_TYPES);
            }
        }
        
        // Discount value
        if (!$isUpdate || isset($data['discount_value'])) {
            if (!isset($data['discount_value']) || $data['discount_value'] === '') {
                $errors['discount_value'] = 'Giá trị giảm không được để trống';
            } elseif (!is_numeric($data['discount_value']) || $data['discount_value'] <= 0) {
                $errors['discount_value'] = 'Giá trị giảm phải là số dương';
            } elseif (isset($data['discount_type']) && $data['discount_type'] === 'PERCENTAGE' && $data['discount_value'] > 100) {
                $errors['discount_value'] = 'Giá trị giảm theo % không được vượt quá 100';
            }
        }
        
        // Min order amount
        if (isset($data['min_order_amount'])) {
            if (!is_numeric($data['min_order_amount']) || $data['min_order_amount'] < 0) {
                $errors['min_order_amount'] = 'Đơn tối thiểu phải là số không âm';
            }
        }
        
        // Max discount
        if (isset($data['max_discount']) && $data['max_discount'] !== null && $data['max_discount'] !== '') {
            if (!is_numeric($data['max_discount']) || $data['max_discount'] <= 0) {
                $errors['max_discount'] = 'Giảm tối đa phải là số dương';
            }
        }
        
        // Usage limit
        if (isset($data['usage_limit'])) {
            if (!is_numeric($data['usage_limit']) || $data['usage_limit'] < 0) {
                $errors['usage_limit'] = 'Số lần sử dụng phải là số không âm';
            }
        }
        
        // Start date và expired_at
        if (isset($data['start_date']) && isset($data['expired_at'])) {
            if ($data['start_date'] && $data['expired_at'] && $data['start_date'] >= $data['expired_at']) {
                $errors['expired_at'] = 'Ngày hết hạn phải sau ngày bắt đầu';
            }
        }
        
        // Status
        if (isset($data['status'])) {
            if (!in_array($data['status'], [0, 1])) {
                $errors['status'] = 'Trạng thái chỉ có thể là 0 hoặc 1';
            }
        }
        
        return $errors;
    }
}
