# Email Setup Guide

## Vấn đề hiện tại

Railway (và nhiều cloud platform khác) **block SMTP ports 25, 465, 587** để tránh spam. Do đó, gửi email qua Gmail SMTP sẽ bị timeout.

## Giải pháp được implement

Code đã hỗ trợ 2 phương thức gửi email:

1. **Resend API** (ưu tiên) - Không bị block port
2. **SMTP** (fallback) - SendGrid, Mailgun, AWS SES

### Cách hoạt động:

```
EmailService kiểm tra:
├─ Có RESEND_API_KEY? 
│  ├─ YES → Gửi qua Resend API ✅
│  └─ NO → Gửi qua SMTP
│     ├─ SMTP configured? 
│     │  ├─ YES → Gửi qua SMTP ✅
│     │  └─ NO → Log error ❌
```

---

## Option 1: Resend API (KHUYẾN NGHỊ)

### Ưu điểm:
- ✅ Không bị Railway block port (dùng HTTPS API)
- ✅ Setup nhanh (5 phút)
- ✅ Free tier: 3000 emails/month
- ✅ Modern, developer-friendly
- ✅ Delivery rate cao

### Setup Resend:

**Bước 1:** Đăng ký tại https://resend.com/
- Click **Sign Up** (có thể dùng GitHub)
- Verify email

**Bước 2:** Tạo API Key
- Vào **API Keys** → **Create API Key**
- Name: `Railway Production`
- Permission: **Sending access**
- Copy API Key (bắt đầu bằng `re_...`)

**Bước 3:** Verify Email/Domain

**Option A: Single Sender (Nhanh - cho test)**
```
Domains → Verify a Single Sender
→ Nhập email: datndhe172134@fpt.edu.vn
→ Check email và verify
```

**Option B: Domain (Production)**
```
Domains → Add Domain
→ Nhập domain: spicynoodle.com
→ Add DNS records (SPF, DKIM, DMARC)
```

**Bước 4:** Cập nhật Railway Variables
```env
MAIL_ENABLED=true
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxxx
MAIL_FROM=datndhe172134@fpt.edu.vn
MAIL_FROM_NAME=Spicy Noodle Management
```

**Bước 5:** Deploy và test!

---

## Option 2: SendGrid SMTP (Alternative)

**Bước 1:** Đăng ký tài khoản tại https://sendgrid.com/

**Bước 2:** Tạo API Key
- Dashboard → Settings → API Keys → Create API Key
- Chọn "Full Access"
- Copy API Key

**Bước 3:** Cập nhật `.env` trên Railway
```env
MAIL_ENABLED=true
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USER=apikey
SMTP_PASS=<your-sendgrid-api-key>
MAIL_FROM=noreply@yourdomain.com
```

**Bước 4:** Verify sender email
- SendGrid → Settings → Sender Authentication
- Verify email address hoặc domain

#### 2. Mailgun (Free 5000 emails/month)

**Bước 1:** Đăng ký tại https://www.mailgun.com/

**Bước 2:** Lấy SMTP credentials
- Dashboard → Sending → Domain settings → SMTP credentials

**Bước 3:** Cập nhật `.env`
```env
MAIL_ENABLED=true
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_USER=<your-mailgun-smtp-user>
SMTP_PASS=<your-mailgun-smtp-password>
MAIL_FROM=noreply@yourdomain.com
```

#### 3. Resend (Modern, Developer-friendly)

**Bước 1:** Đăng ký tại https://resend.com/

**Bước 2:** Tạo API Key

**Bước 3:** Cập nhật code để dùng Resend API (không dùng SMTP)
```php
// Gọi Resend API thay vì PHPMailer
$ch = curl_init('https://api.resend.com/emails');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . getenv('RESEND_API_KEY'),
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'from' => 'noreply@yourdomain.com',
    'to' => [$to],
    'subject' => $subject,
    'html' => $message
]));
```

### Option 2: Tắt Email (Development/Testing)

Nếu chỉ test, có thể tắt email và lấy password từ response:

```env
MAIL_ENABLED=false
```

API sẽ trả về:
```json
{
  "success": true,
  "message": "Đã tạo mật khẩu mới...",
  "email": "d****v@gmail.com",
  "email_sent": false,
  "new_password": "Abc123!@#xyz"
}
```

### Option 3: Dùng Railway SMTP Relay (Nếu có)

Một số platform cung cấp SMTP relay riêng. Check Railway docs xem có không.

## Cấu hình Railway Environment Variables

Vào Railway Dashboard → Your Project → Variables:

```
MAIL_ENABLED=true
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USER=apikey
SMTP_PASS=SG.xxxxxxxxxxxxxxxxxxxxx
MAIL_FROM=noreply@spicynoodle.com
MAIL_FROM_NAME=Spicy Noodle Management
```

## Test Email

Sau khi setup, test API:

```bash
curl -X POST https://your-railway-app.up.railway.app/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"phone":"0987654321"}'
```

Check response:
- `email_sent: true` → Email đã gửi thành công
- `email_sent: false` → Email fail, check logs

## Troubleshooting

### Email không gửi được

1. Check Railway logs: `railway logs`
2. Xem error message cụ thể
3. Verify SMTP credentials
4. Check sender email đã verify chưa

### Timeout khi gửi email

- Railway block SMTP ports → Dùng email service
- Hoặc tăng timeout trong `EmailService.php`:
```php
$mail->Timeout = 30; // 30 giây
```

### Email vào Spam

- Verify domain với SPF, DKIM, DMARC
- Dùng email service có reputation tốt (SendGrid, Mailgun)
- Không dùng Gmail SMTP cho production

## Production Checklist

- [ ] Dùng email service chuyên dụng (SendGrid/Mailgun)
- [ ] Verify sender domain
- [ ] Setup SPF, DKIM records
- [ ] Xóa field `new_password` trong response (bảo mật)
- [ ] Monitor email delivery rate
- [ ] Setup email templates đẹp hơn
