# Hướng dẫn Deploy lên Railway.app

## Bước 1: Push code lên GitHub

```bash
# Khởi tạo git (nếu chưa có)
git init
git add .
git commit -m "Initial commit"

# Tạo repo trên GitHub rồi push
git remote add origin https://github.com/your-username/your-repo.git
git branch -M main
git push -u origin main
```

## Bước 2: Đăng ký Railway

1. Truy cập: https://railway.app
2. Click "Login" → Chọn "Login with GitHub"
3. Authorize Railway truy cập GitHub

## Bước 3: Tạo Project mới

1. Click "New Project"
2. Chọn "Deploy from GitHub repo"
3. Chọn repo vừa push
4. Railway sẽ tự động detect Dockerfile và bắt đầu build

## Bước 4: Thêm MySQL Database

1. Trong project, click "New" → "Database" → "Add MySQL"
2. Railway tự động tạo database và set environment variables:
   - `MYSQLHOST`
   - `MYSQLPORT`
   - `MYSQLDATABASE`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`

## Bước 5: Import Database Schema

### Cách 1: Qua Railway CLI (Khuyên dùng)

```bash
# Cài Railway CLI
npm i -g @railway/cli

# Login
railway login

# Link project
railway link

# Import database
railway run mysql -h $MYSQLHOST -P $MYSQLPORT -u $MYSQLUSER -p$MYSQLPASSWORD $MYSQLDATABASE < script.sql
```

### Cách 2: Qua MySQL Client

1. Lấy thông tin database từ Railway:
   - Click vào MySQL service
   - Tab "Variables" → Copy connection info

2. Connect và import:
```bash
mysql -h <MYSQLHOST> -P <MYSQLPORT> -u <MYSQLUSER> -p<MYSQLPASSWORD> <MYSQLDATABASE> < script.sql
```

## Bước 6: Lấy Public URL

1. Click vào service PHP
2. Tab "Settings" → "Networking"
3. Click "Generate Domain"
4. Copy URL: `https://your-app.up.railway.app`

## Bước 7: Test API

```bash
# Health check
curl https://your-app.up.railway.app/api/health

# Swagger UI
# Mở browser: https://your-app.up.railway.app/api/docs
```

## Bước 8: Cung cấp cho FE

**API Base URL:**
```
https://your-app.up.railway.app/api
```

**Endpoints:**
- Health: `GET /api/health`
- Test DB: `GET /api/test-db`
- Docs: `GET /api/docs`

## Update Code

Mỗi khi push code mới lên GitHub, Railway tự động deploy:

```bash
git add .
git commit -m "Update API"
git push origin main
```

Railway sẽ:
1. Detect changes
2. Rebuild Docker image
3. Deploy tự động
4. Zero downtime

## Xem Logs

1. Vào Railway dashboard
2. Click vào service
3. Tab "Deployments" → Click deployment mới nhất
4. Tab "Logs" để xem real-time logs

## Troubleshooting

### Lỗi: Database connection failed

**Giải pháp:** Kiểm tra MySQL service đã start chưa
- Vào MySQL service → Tab "Deployments"
- Đợi status = "Success"

### Lỗi: 502 Bad Gateway

**Giải pháp:** Service chưa start xong
- Đợi 1-2 phút
- Check logs xem có lỗi gì

### Lỗi: Port already in use

**Giải pháp:** Railway tự động map port, không cần config

## Chi phí

- **Free tier:** $5 credit/tháng
- **Hobby plan:** $5/tháng (unlimited projects)
- API nhỏ như này chạy free tier là đủ

## Custom Domain (Tùy chọn)

1. Mua domain (Namecheap, GoDaddy...)
2. Railway → Settings → Domains
3. Add custom domain: `api.yourdomain.com`
4. Thêm CNAME record vào DNS:
   - Name: `api`
   - Value: `your-app.up.railway.app`
5. Đợi DNS propagate (5-30 phút)

## Backup Database

```bash
# Export
railway run mysqldump -h $MYSQLHOST -P $MYSQLPORT -u $MYSQLUSER -p$MYSQLPASSWORD $MYSQLDATABASE > backup.sql

# Import
railway run mysql -h $MYSQLHOST -P $MYSQLPORT -u $MYSQLUSER -p$MYSQLPASSWORD $MYSQLDATABASE < backup.sql
```

## Monitoring

Railway dashboard hiển thị:
- CPU usage
- Memory usage
- Network traffic
- Request logs
- Error logs

## Lưu ý

- Railway tự động sleep service sau 5 phút không dùng (free tier)
- Request đầu tiên sau khi sleep sẽ mất 10-20s để wake up
- Upgrade Hobby plan để tránh sleep
