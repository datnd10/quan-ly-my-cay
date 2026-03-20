# Hướng dẫn setup Cloudinary

## 1. Tạo tài khoản Cloudinary (FREE)

1. Truy cập: https://cloudinary.com/users/register/free
2. Đăng ký tài khoản miễn phí
3. Sau khi đăng ký, vào Dashboard: https://console.cloudinary.com/

## 2. Lấy credentials

Trong Dashboard, bạn sẽ thấy:
- **Cloud Name**: `dxxxxxx`
- **API Key**: `123456789012345`
- **API Secret**: `abcdefghijklmnopqrstuvwxyz`

## 3. Cài đặt trên Railway

### Bước 1: Cài Composer dependencies

Chạy lệnh local hoặc trên Railway:
```bash
composer install
```

### Bước 2: Thêm Environment Variables trên Railway

Vào Railway Dashboard → Your Service → Variables → Add:

```
CLOUDINARY_CLOUD_NAME=dxxxxxx
CLOUDINARY_API_KEY=123456789012345
CLOUDINARY_API_SECRET=abcdefghijklmnopqrstuvwxyz
```

### Bước 3: Deploy

Railway sẽ tự động redeploy sau khi thêm variables.

## 4. Test

Upload ảnh qua API:
```bash
curl -X POST 'https://your-domain.com/api/products' \
  -H 'Authorization: Bearer TOKEN' \
  -F 'name=Test Product' \
  -F 'price=50000' \
  -F 'category_id=1' \
  -F 'images[]=@image1.jpg' \
  -F 'images[]=@image2.jpg'
```

Response sẽ có URLs từ Cloudinary:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Test Product",
    "images": [
      "https://res.cloudinary.com/dxxxxxx/image/upload/v123/products/abc.jpg",
      "https://res.cloudinary.com/dxxxxxx/image/upload/v123/products/def.jpg"
    ]
  }
}
```

## 5. Quota FREE tier

- **Storage**: 25 GB
- **Bandwidth**: 25 GB/month
- **Transformations**: 25,000/month

Đủ cho development và small projects!

## 6. Fallback

Nếu không config Cloudinary, hệ thống tự động fallback về local storage (nhưng sẽ mất khi Railway restart).
