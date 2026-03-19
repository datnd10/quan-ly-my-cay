# Spicy Noodle Management System

Hệ thống quản lý nhà hàng mỳ cay - REST API Backend

## Cấu trúc thư mục

```
.
├── docker-compose.yaml          # Docker configuration
├── Dockerfile                   # PHP Apache image
├── script.sql                   # Database schema
├── storage/                     # Storage folder
│   ├── logs/                   # Application logs
│   └── uploads/                # Uploaded files
└── src/                        # Source code
    ├── index.php               # Entry point
    ├── bootstrap.php           # App initialization
    ├── .htaccess              # Apache rewrite rules
    ├── config/                # Configuration files
    │   ├── app.php           # App config
    │   ├── database.php      # Database config
    │   └── constants.php     # Constants
    ├── app/                   # Application code
    │   ├── core/             # Core classes
    │   │   ├── Controller.php
    │   │   ├── Model.php
    │   │   ├── Router.php
    │   │   └── Database.php
    │   ├── controllers/      # Controllers
    │   ├── models/          # Models
    │   ├── middlewares/     # Middlewares
    │   ├── services/        # Business logic
    │   └── validators/      # Validation classes
    ├── routes/              # Route definitions
    │   └── api.php
    ├── docs/                # API documentation
    │   └── openapi.json
    ├── public/              # Public assets
    │   └── swagger.html
    └── utils/               # Helper utilities
```

## Yêu cầu hệ thống

- Docker & Docker Compose
- Git

## Cài đặt và chạy

### 1. Clone project (nếu có git repo)
```bash
git clone <repo-url>
cd spicy-noodle
```

### 2. Tạo thư mục storage (nếu chưa có)
```bash
mkdir -p storage/logs storage/uploads
```

### 3. Build và khởi động Docker containers
```bash
docker-compose up -d --build
```

Lệnh này sẽ:
- Build PHP image với PDO MySQL extension
- Khởi động 3 services:
  - `mysql` - MySQL 8 database (port 3307 → 3306 internal)
  - `php` - PHP 8.2 + Apache (port 8000)
  - `adminer` - Database management tool (port 8081)

### 4. Đợi MySQL khởi động (khoảng 10-15 giây)
```bash
docker-compose logs -f mysql
# Đợi thấy dòng: "ready for connections"
# Nhấn Ctrl+C để thoát
```

### 5. Import database schema

**PowerShell:**
```powershell
Get-Content script.sql | docker exec -i spicy_mysql mysql -uroot -p123456 spicy_noodle_db
```

**CMD:**
```cmd
type script.sql | docker exec -i spicy_mysql mysql -uroot -p123456 spicy_noodle_db
```

**Bash/Git Bash:**
```bash
docker exec -i spicy_mysql mysql -uroot -p123456 spicy_noodle_db < script.sql
```

### 6. Kiểm tra API
```bash
# Health check
curl http://localhost:8000/api/health

# Test database connection
curl http://localhost:8000/api/test-db

# Mở Swagger UI trong browser
# http://localhost:8000/api/docs
```

## Truy cập

- **API Documentation (Swagger)**: http://localhost:8000/api/docs
- **API Base URL**: http://localhost:8000/api
- **Adminer (DB Manager)**: http://localhost:8081
  - Server: `mysql`
  - Username: `root`
  - Password: `123456`
  - Database: `spicy_noodle_db`

## Các lệnh Docker hữu ích

```bash
# Xem logs
docker-compose logs -f php

# Dừng containers
docker-compose down

# Khởi động lại
docker-compose restart

# Xóa containers và volumes
docker-compose down -v

# Vào container PHP
docker exec -it spicy_php bash

# Vào MySQL CLI
docker exec -it spicy_mysql mysql -uroot -p123456 spicy_noodle_db
```

## Development

### Thêm route mới
Chỉnh sửa file `src/routes/api.php`:

```php
$router->get('products', 'ProductController@index');
$router->post('products', 'ProductController@store');
```

### Tạo Controller mới
Tạo file trong `src/app/controllers/`:

```php
<?php

class ProductController extends Controller {
    public function index() {
        $this->success(['products' => []], 'Danh sách sản phẩm');
    }
}
```

### Tạo Model mới
Tạo file trong `src/app/models/`:

```php
<?php

class Product extends Model {
    protected $table = 'products';
    protected $fillable = ['name', 'price', 'category_id'];
}
```

## Deployment

Xem chi tiết trong file [DEPLOYMENT.md](DEPLOYMENT.md)

**Quick deploy:**
```bash
# Production
docker-compose -f docker-compose.prod.yaml up -d --build

# Auto deploy script
chmod +x deploy.sh
./deploy.sh
```

**Deploy options:**
- VPS (DigitalOcean, AWS, Vultr)
- Railway.app (free, easiest)
- Render.com (free)
- Ngrok (quick demo)

## Tech Stack

- PHP 8.2
- MySQL 8
- Apache
- Docker

## License

Private project
