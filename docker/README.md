# NukeViet 5.x Docker Environment

Môi trường Docker đầy đủ để phát triển và chạy NukeViet 5.x

## 📋 Các Container

| Container | Mô tả | Port |
|-----------|-------|------|
| `nukeviet5_app` | PHP 8.4-FPM với đầy đủ extensions | 9000 (internal) |
| `nukeviet5_webserver` | Nginx Alpine | 80, 443 |
| `nukeviet5_db` | MariaDB 11 | 3306 |
| `nukeviet5_redis` | Redis 7 Alpine | 6379 |
| `nukeviet5_phpmyadmin` | phpMyAdmin | 8001 |
| `nukeviet5_redis_commander` | Redis Commander UI | 8002 |

## 🚀 Khởi động

### 1. Chuẩn bị môi trường

```bash
cd docker
cp .env.example .env
# Chỉnh sửa .env nếu cần
```

### 2. Build và khởi động containers

```bash
# Build lần đầu
docker compose build

# Khởi động
docker compose up -d

# Xem logs
docker compose logs -f
```

### 3. Truy cập

- **Website**: http://localhost
- **phpMyAdmin**: http://localhost:8001
- **Redis Commander**: http://localhost:8002

## 🗄️ Database

**Thông tin kết nối:**
- Host: `db` (trong container) hoặc `localhost` (từ máy host)
- Port: `3306`
- Database: `nukeviet5`
- Username: `nukeviet` / `root`
- Password: `nukeviet_password` / `password`

## 📦 Redis (Worker/Queue)

Redis được cấu hình sẵn để sử dụng cho các background workers.

**Thông tin kết nối:**
- Host: `redis`
- Port: `6379`

**Trong PHP:**
```php
$redis = new Redis();
$redis->connect('redis', 6379);
```

## 🔧 Các lệnh thường dùng

```bash
# Khởi động
docker compose up -d

# Dừng
docker compose down

# Rebuild (khi thay đổi Dockerfile)
docker compose build --no-cache

# Vào container PHP
docker compose exec app bash

# Chạy composer
docker compose exec app composer install

# Xem logs PHP
docker compose logs -f app

# Xem logs tất cả
docker compose logs -f

# Restart một container
docker compose restart app
```

## 📁 Cấu trúc thư mục

```
docker/
├── docker-compose.yml    # Cấu hình chính
├── .env.example          # File môi trường mẫu
├── php/
│   ├── Dockerfile        # Image PHP với extensions
│   └── local.ini         # Cấu hình PHP
├── nginx/
│   └── default.conf      # Cấu hình Nginx cho NukeViet
└── mariadb/
    └── my.cnf            # Cấu hình MariaDB
```

## 🔌 PHP Extensions đã cài đặt

- pdo_mysql, mysqli
- mbstring, zip, exif
- gd (với freetype, jpeg, webp)
- intl, soap, bcmath
- opcache, pcntl
- **redis** (cho workers)

## ⚠️ Lưu ý

1. **Volume paths**: Source code được mount từ `../` (thư mục cha của docker)
2. **Permissions**: Nếu gặp lỗi permission, chạy:
   ```bash
   docker compose exec app chown -R www:www /var/www
   ```
3. **First run**: Sau khi khởi động lần đầu, truy cập http://localhost để cài đặt NukeViet

## 🔄 Worker với Redis

Để sử dụng Redis cho queue/worker trong NukeViet:

1. Kết nối Redis trong config:
```php
// Trong file cấu hình của module
$redis_host = getenv('REDIS_HOST') ?: 'redis';
$redis_port = getenv('REDIS_PORT') ?: 6379;
```

2. Sử dụng trong code:
```php
$redis = new Redis();
$redis->connect($redis_host, $redis_port);

// Push job vào queue
$redis->lPush('queue:default', json_encode($job));

// Pop job từ queue (trong worker)
$job = $redis->rPop('queue:default');
```

## 📊 Monitoring

- **Redis Commander** tại http://localhost:8002 để xem và quản lý Redis keys
- **phpMyAdmin** tại http://localhost:8001 để quản lý database
