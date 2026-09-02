# คู่มือติดตั้งโปรเจกต์ Manage Server บนเครื่องใหม่ (Windows และ Linux AlmaLinux)

เอกสารนี้อธิบายทุกอย่างที่ต้อง "ติดตั้ง" และ "ตั้งค่า" เพื่อนำโปรเจกต์นี้ไปรัน
บนคอมพิวเตอร์เครื่องอื่น ครอบคลุมทั้ง **Windows** และ **AlmaLinux 9** (ตระกูล RHEL)
ตั้งแต่ซอฟต์แวร์พื้นฐาน เว็บเซิร์ฟเวอร์ ฐานข้อมูล ไปจนถึงการย้ายข้อมูลและ
รายการที่ต้องทำหลังโคลนเสร็จ

> **โปรเจกต์นี้คือ** Laravel 13 + Inertia.js + React 19 (TypeScript, Tailwind CSS)
> **ฐานข้อมูล** MySQL / MariaDB
> ใช้ `database` เป็นตัวเก็บ **session / cache / queue** (ไม่ต้องมี Redis)
> มี **งานตามเวลา (scheduler)** และ **queue worker** ที่ต้องรันแยก

---

## สารบัญ

- [0. ความต้องการเวอร์ชัน (ตารางรวม)](#0-ความต้องการเวอร์ชัน-ตารางรวม)
- [ส่วน A — ติดตั้งบน Windows](#ส่วน-a--ติดตั้งบน-windows)
  - [A1. แบบใช้ Laravel Herd (แนะนำ)](#a1-แบบใช้-laravel-herd-แนะนำ)
  - [A2. แบบติดตั้งเอง (ไม่ใช้ Herd)](#a2-แบบติดตั้งเอง-ไม่ใช้-herd)
- [ส่วน B — ติดตั้งบน AlmaLinux 9](#ส่วน-b--ติดตั้งบน-almalinux-9)
  - [B1. เตรียม repository (EPEL + Remi)](#b1-เตรียม-repository-epel--remi)
  - [B2. PHP 8.3 + extensions + PHP-FPM](#b2-php-83--extensions--php-fpm)
  - [B3. Composer](#b3-composer)
  - [B4. Node.js 20+](#b4-nodejs-20)
  - [B5. MySQL / MariaDB](#b5-mysql--mariadb)
  - [B6. nginx](#b6-nginx)
  - [B7. SELinux และ firewalld](#b7-selinux-และ-firewalld)
  - [B8. สิทธิ์ไฟล์](#b8-สิทธิ์ไฟล์)
  - [B9. systemd (queue) + cron (scheduler)](#b9-systemd-queue--cron-scheduler)
- [ส่วน C — ขั้นตอนที่เหมือนกันทั้งสอง OS (โคลน + ตั้งค่า + build)](#ส่วน-c--ขั้นตอนที่เหมือนกันทั้งสอง-os-โคลน--ตั้งค่า--build)
- [ส่วน D — การตั้งค่าไฟล์ `.env`](#ส่วน-d--การตั้งค่าไฟล์-env)
- [ส่วน E — ย้ายฐานข้อมูลและไฟล์ที่ผู้ใช้อัปโหลด](#ส่วน-e--ย้ายฐานข้อมูลและไฟล์ที่ผู้ใช้อัปโหลด)
- [ส่วน F — Queue Worker และ Scheduler](#ส่วน-f--queue-worker-และ-scheduler)
- [ส่วน G — สร้างผู้ใช้ผู้ดูแลระบบคนแรก](#ส่วน-g--สร้างผู้ใช้ผู้ดูแลระบบคนแรก)
- [ส่วน H — ไฟล์/โฟลเดอร์ที่ไม่ได้อยู่ใน Git](#ส่วน-h--ไฟล์โฟลเดอร์ที่ไม่ได้อยู่ใน-git)
- [ส่วน I — ตรวจสอบหลังติดตั้ง](#ส่วน-i--ตรวจสอบหลังติดตั้ง)
- [ส่วน J — ขึ้น Production](#ส่วน-j--ขึ้น-production)
- [ส่วน K — แก้ปัญหาที่พบบ่อย](#ส่วน-k--แก้ปัญหาที่พบบ่อย)
- [ส่วน L — สรุปคำสั่งแบบย่อ](#ส่วน-l--สรุปคำสั่งแบบย่อ)

---

## 0. ความต้องการเวอร์ชัน (ตารางรวม)

| รายการ | เวอร์ชันขั้นต่ำ | เครื่องต้นฉบับ | บังคับ? |
|---|---|---|---|
| PHP | 8.3 | 8.4 | ✅ |
| Composer | 2.x | 2.9 | ✅ |
| Node.js | 20 LTS | 22 LTS+ | ✅ |
| npm | 10 | 11.x | ✅ (มากับ Node) |
| MySQL / MariaDB | MySQL 8.0 / MariaDB 10.6 | MySQL 8 | ✅ |
| Git | ล่าสุด | — | ✅ |
| เว็บเซิร์ฟเวอร์ | nginx หรือ Apache (หรือ `php artisan serve` ตอน dev) | Herd (nginx) | ✅ |
| Cron / Task Scheduler | — | Herd toggle | ✅ (scheduler) |
| ตัวคุมโปรเซส (systemd / NSSM / Supervisor) | — | Herd toggle | ⭐ แนะนำ (queue) |

**ไม่ต้องมี:** Redis, Memcached — session/cache/queue เป็น `database` ทั้งหมด

**PHP extensions ที่ต้องเปิด (ทั้งสอง OS):**

```
bcmath  ctype  curl  dom  exif  fileinfo  gd  iconv  intl  libxml
mbstring  openssl  pcntl/posix  pcre  pdo  pdo_mysql  session
simplexml  sodium  tokenizer  xml  xmlreader  xmlwriter  zip
```

| Extension | ใช้ทำอะไรในโปรเจกต์นี้ |
|---|---|
| `pdo_mysql` | เชื่อมต่อ MySQL |
| `gd` | สร้าง PDF (Daily Report / รายงานประเมิน) ผ่าน dompdf และย่อรูป |
| `zip`, `xml`, `dom`, `simplexml` | อ่าน/เขียน Excel (phpspreadsheet) และ dompdf |
| `intl` | จัดรูปแบบวันที่/ตัวเลขภาษาไทย (locale = `th`) |
| `mbstring`, `iconv` | จัดการสตริงภาษาไทย |
| `openssl`, `sodium` | เข้ารหัส session/token, SSH (phpseclib), Fortify passkeys |
| `curl` | เรียก API ภายนอก (vSphere, Telegram, Anthropic) |
| `pcntl`, `posix` | `php artisan queue:work` / `pail` (โหมด dev) |
| `exif` | อ่าน metadata ของรูปที่อัปโหลด |

---

# ส่วน A — ติดตั้งบน Windows

## A1. แบบใช้ Laravel Herd (แนะนำ)

[Laravel Herd](https://herd.laravel.com) รวม PHP หลายเวอร์ชัน + nginx + dnsmasq
(โดเมน `*.test`) ไว้ในตัวเดียว มีปุ่มเปิด **Scheduler** และ **Queue** ต่อเว็บไซต์
เหมาะที่สุดสำหรับเครื่องพัฒนา/เดโมบน Windows

1. **ติดตั้ง Herd** จากเว็บ แล้วเปิดโปรแกรม
2. Herd จะติดตั้ง PHP ให้อัตโนมัติ — เข้า **Settings → PHP** เลือกเวอร์ชัน **8.3 หรือ 8.4**
   และเปิด extension: `intl`, `gd`, `zip`, `exif`, `sodium`, `pdo_mysql` (ปกติเปิดครบอยู่แล้ว)
3. **ฐานข้อมูล:**
   - Herd Pro มี MySQL ในตัว (แท็บ Services) — เปิดใช้ได้เลย
   - Herd ฟรี: ติดตั้ง MySQL แยก เช่น
     [MySQL Community Installer](https://dev.mysql.com/downloads/installer/),
     [MariaDB](https://mariadb.org/download/), Laragon หรือ Docker
4. **Composer** มากับ Herd แล้ว (ตรวจ `composer -V` ใน Herd terminal)
5. **Node.js + npm** — Herd ไม่รวมมาให้ ต้องติดตั้งเอง:
   [nodejs.org](https://nodejs.org) เลือก LTS (20 ขึ้นไป) หรือใช้
   [nvm-windows](https://github.com/coreybutler/nvm-windows)
6. **Git** — [git-scm.com](https://git-scm.com/download/win)
7. **วางโปรเจกต์** ในโฟลเดอร์ที่ Herd `park` ไว้ (ค่าเริ่มต้น `C:\Users\<user>\Herd`)
   → โคลนลงเป็น `C:\Users\<user>\Herd\manage-server`
8. ทำต่อที่ [ส่วน C](#ส่วน-c--ขั้นตอนที่เหมือนกันทั้งสอง-os-โคลน--ตั้งค่า--build)
9. หลัง `npm run build` เปิด `https://manage-server.test` ได้เลย
   (Herd ออกใบรับรอง HTTPS ให้อัตโนมัติ)
10. ในแอป Herd เปิดสวิตช์ **Scheduler** และ **Queue** ของเว็บไซต์นี้

---

## A2. แบบติดตั้งเอง (ไม่ใช้ Herd)

### A2.1 PHP 8.3/8.4

1. ดาวน์โหลด **PHP for Windows (Thread Safe, x64)** จาก
   <https://windows.php.net/download/> แตกไฟล์ไปที่ `C:\php`
2. เพิ่ม `C:\php` เข้า **PATH** (System Environment Variables)
3. คัดลอก `php.ini-development` เป็น `php.ini` แล้วแก้:
   ```ini
   extension_dir = "ext"
   extension=bcmath
   extension=curl
   extension=exif
   extension=fileinfo
   extension=gd
   extension=intl
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   extension=sodium
   extension=zip
   ```
4. ตรวจ: `php -v` และ `php -m`

### A2.2 Composer

ติดตั้งด้วย [Composer-Setup.exe](https://getcomposer.org/download/) (ชี้ไปที่ `C:\php\php.exe`)
ตรวจ: `composer -V`

### A2.3 Node.js + npm

ติดตั้ง LTS จาก [nodejs.org](https://nodejs.org) (20+) — ตรวจ `node -v`, `npm -v`

### A2.4 MySQL / MariaDB

ติดตั้ง [MySQL Community Server 8](https://dev.mysql.com/downloads/mysql/) หรือ MariaDB
ตั้งรหัสผ่าน root จำไว้ใช้ใน `.env`

### A2.5 เว็บเซิร์ฟเวอร์

- **ง่ายสุด (dev):** ใช้ `php artisan serve` (พอร์ต 8000)
- **แบบจริงจัง:** ติดตั้ง nginx for Windows หรือ IIS + ตั้ง document root ที่โฟลเดอร์ `public\`
  และ FastCGI ไป `php-cgi.exe`

### A2.6 Git

[git-scm.com](https://git-scm.com/download/win)

### A2.7 Scheduler + Queue บน Windows (ไม่มี Herd)

- **Scheduler:** สร้าง **Task Scheduler** ให้รันทุก 1 นาที
  - Program: `C:\php\php.exe`
  - Arguments: `artisan schedule:run`
  - Start in: `C:\path\to\manage-server`
- **Queue:** ใช้ [NSSM](https://nssm.cc/) ทำ `php artisan queue:work` เป็น Windows Service
  ```powershell
  nssm install ManageServerQueue "C:\php\php.exe" "artisan queue:work --sleep=3 --tries=3 --max-time=3600"
  nssm set ManageServerQueue AppDirectory "C:\path\to\manage-server"
  nssm start ManageServerQueue
  ```

จากนั้นทำต่อที่ [ส่วน C](#ส่วน-c--ขั้นตอนที่เหมือนกันทั้งสอง-os-โคลน--ตั้งค่า--build)

---

# ส่วน B — ติดตั้งบน AlmaLinux 9

> คำสั่งทั้งหมดใช้สิทธิ์ `sudo` สมมติ deploy โปรเจกต์ไว้ที่ `/var/www/manage-server`
> และเว็บเซิร์ฟเวอร์คือ **nginx + PHP-FPM**

## B1. เตรียม repository (EPEL + Remi)

AppStream ของ AlmaLinux 9 ให้ PHP 8.1/8.2 — เพื่อได้ **PHP 8.3/8.4** ต้องใช้ Remi:

```bash
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf update -y
```

## B2. PHP 8.3 + extensions + PHP-FPM

```bash
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.3 -y      # หรือ php:remi-8.4
sudo dnf install -y \
  php php-cli php-fpm php-common php-opcache \
  php-mysqlnd php-mbstring php-xml php-gd php-intl php-bcmath \
  php-zip php-curl php-sodium php-process php-pecl-zip

php -v
php -m        # ตรวจให้ครบตามตารางข้อ 0
```

- `php-mysqlnd` = `pdo_mysql`
- `php-process` = `pcntl` + `posix` (จำเป็นสำหรับ `queue:work`)
- `php-gd`, `php-zip`, `php-xml` = dompdf + phpspreadsheet
- `php-intl` = locale ภาษาไทย
- `php-sodium` = Fortify passkeys / เข้ารหัส

**ให้ PHP-FPM รันเป็น user เดียวกับ nginx** — แก้ `/etc/php-fpm.d/www.conf`:

```ini
user = nginx
group = nginx
listen.owner = nginx
listen.group = nginx
```

เปิดใช้งาน:

```bash
sudo systemctl enable --now php-fpm
```

> ปรับ `upload_max_filesize` / `post_max_size` ใน `/etc/php.ini` ถ้าต้องอัปโหลดไฟล์ใหญ่
> (การนำเข้าใบรับรอง VM / โลโก้อีเมล) — ค่าเริ่มต้น 2M/8M มักพอ

## B3. Composer

```bash
sudo dnf install -y composer     # จาก Remi/EPEL ได้ Composer 2.x
composer -V
# หรือติดตั้งเองจาก getcomposer.org แล้ววางที่ /usr/local/bin/composer
```

## B4. Node.js 20+

เลือกวิธีใดวิธีหนึ่ง:

```bash
# วิธี 1: AppStream module
sudo dnf module reset nodejs -y
sudo dnf module enable nodejs:20 -y
sudo dnf install -y nodejs

# วิธี 2: NodeSource (ได้เวอร์ชันใหม่กว่า เช่น 22 LTS)
curl -fsSL https://rpm.nodesource.com/setup_22.x | sudo bash -
sudo dnf install -y nodejs
```

```bash
node -v   # >= 20
npm -v    # >= 10
```

## B5. MySQL / MariaDB

```bash
# MySQL 8 (แนะนำ ให้ตรงกับเครื่องต้นฉบับ)
sudo dnf install -y mysql-server
sudo systemctl enable --now mysqld
sudo mysql_secure_installation
```

หรือ MariaDB:

```bash
sudo dnf install -y mariadb-server
sudo systemctl enable --now mariadb
sudo mariadb-secure-installation
```

สร้างฐานข้อมูลและผู้ใช้:

```sql
CREATE DATABASE `manage-server` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'manage'@'localhost' IDENTIFIED BY 'รหัสผ่านที่ปลอดภัย';
GRANT ALL PRIVILEGES ON `manage-server`.* TO 'manage'@'localhost';
FLUSH PRIVILEGES;
```

> ชื่อฐานข้อมูล `manage-server` มีขีดกลาง — ต้องครอบด้วย backtick `` ` `` เสมอ

## B6. nginx

```bash
sudo dnf install -y nginx
sudo systemctl enable --now nginx
```

สร้าง `/etc/nginx/conf.d/manage-server.conf`:

```nginx
server {
    listen 80;
    server_name manage-server.example.local;      # เปลี่ยนเป็นโดเมน/IP จริง
    root /var/www/manage-server/public;

    index index.php;
    charset utf-8;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

> ใส่ HTTPS ด้วย `certbot` (`sudo dnf install certbot python3-certbot-nginx`) ถ้ามีโดเมนจริง

## B7. SELinux และ firewalld

AlmaLinux เปิด SELinux แบบ `enforcing` โดยดีฟอลต์ — ต้องอนุญาต:

```bash
sudo dnf install -y policycoreutils-python-utils

# ให้ PHP-FPM เชื่อมต่อออกภายนอกได้ (vSphere API, Telegram, Anthropic, SSH เข้าไป VM)
sudo setsebool -P httpd_can_network_connect 1

# ให้ nginx/php-fpm เขียนโฟลเดอร์ storage และ bootstrap/cache ได้
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/manage-server/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/manage-server/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/manage-server/storage /var/www/manage-server/bootstrap/cache
```

เปิดพอร์ตไฟร์วอลล์:

```bash
sudo firewall-cmd --permanent --add-service=http --add-service=https
sudo firewall-cmd --reload
```

> ถ้า SELinux ยังบล็อกอะไรอยู่ ดูได้จาก `sudo ausearch -m avc -ts recent`
> ระหว่างทดสอบอาจตั้งชั่วคราวเป็น `sudo setenforce 0` เพื่อยืนยันว่าปัญหามาจาก SELinux

## B8. สิทธิ์ไฟล์

```bash
sudo mkdir -p /var/www
sudo git clone <repo-url> /var/www/manage-server
cd /var/www/manage-server

# ให้ nginx เป็นเจ้าของโฟลเดอร์ที่ต้องเขียน
sudo chown -R nginx:nginx storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;

# ให้ user ที่ใช้ deploy อยู่ในกลุ่ม nginx เพื่อรันคำสั่ง artisan ได้สะดวก
sudo usermod -aG nginx $USER      # ต้อง log out/in ใหม่
```

## B9. systemd (queue) + cron (scheduler)

**Queue worker** — สร้าง `/etc/systemd/system/manage-server-queue.service`:

```ini
[Unit]
Description=Manage Server queue worker
After=network.target mysqld.service

[Service]
User=nginx
Group=nginx
WorkingDirectory=/var/www/manage-server
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=append:/var/log/manage-server-queue.log
StandardError=append:/var/log/manage-server-queue.log

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now manage-server-queue
sudo systemctl status manage-server-queue
```

**Scheduler** — สร้าง `/etc/cron.d/manage-server`:

```cron
* * * * * nginx cd /var/www/manage-server && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

> ทุกครั้งที่ deploy โค้ดใหม่: `sudo systemctl restart manage-server-queue`
> (หรือ `php artisan queue:restart`)

จากนั้นทำต่อที่ [ส่วน C](#ส่วน-c--ขั้นตอนที่เหมือนกันทั้งสอง-os-โคลน--ตั้งค่า--build)

---

# ส่วน C — ขั้นตอนที่เหมือนกันทั้งสอง OS (โคลน + ตั้งค่า + build)

รันในโฟลเดอร์โปรเจกต์ (`C:\Users\<user>\Herd\manage-server` หรือ `/var/www/manage-server`)

### C1. ติดตั้ง dependencies

```bash
composer install
npm install
```

### C2. สร้างไฟล์ `.env`

```bash
cp .env.example .env          # Windows PowerShell: Copy-Item .env.example .env
```

**ต้องแก้ `.env`** ตาม [ส่วน D](#ส่วน-d--การตั้งค่าไฟล์-env) — สำคัญสุดคือ `DB_*`
เพราะ `.env.example` ตั้งมาเป็น `sqlite` แต่โปรเจกต์นี้ใช้ `mysql`
วิธีที่ชัวร์สุดคือ **คัดลอกไฟล์ `.env` เดิมจากเครื่องต้นฉบับมาทั้งไฟล์** แล้วแก้แค่
`APP_URL` และ `DB_HOST`/`DB_USERNAME`/`DB_PASSWORD`

### C3. สร้าง APP_KEY

```bash
php artisan key:generate
```

### C4. สร้างฐานข้อมูลเปล่า

- Windows: ผ่าน MySQL Workbench / HeidiSQL / คำสั่ง `mysql`
- AlmaLinux: ทำแล้วในข้อ B5

```sql
CREATE DATABASE `manage-server` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### C5. รัน migration

```bash
php artisan migrate
```

สร้างตารางทั้งหมด รวม `sessions`, `cache`, `jobs` (เพราะ session/cache/queue เป็น `database`)

> ถ้าจะย้ายข้อมูลจริงจากเครื่องเดิม ดู [ส่วน E](#ส่วน-e--ย้ายฐานข้อมูลและไฟล์ที่ผู้ใช้อัปโหลด)
> **อย่ารัน `php artisan db:seed` บนฐานข้อมูลของจริง**

### C6. สร้าง symlink ของ storage

```bash
php artisan storage:link
```

จำเป็นสำหรับรูปที่อัปโหลดในหน้า Settings (favicon, โลโก้อีเมลแจ้งซ่อม)

### C7. Build ไฟล์หน้าเว็บ

```bash
npm run build
```

> **ต้องต่ออินเทอร์เน็ต** — Vite ดาวน์โหลดฟอนต์ (Bunny Fonts / Instrument Sans)
> และสร้างไฟล์ route helper อัตโนมัติ ผลลัพธ์อยู่ที่ `public/build/`

### C8. (ทางลัด) รวมหลายขั้นในคำสั่งเดียว

```bash
composer setup
```

รัน `composer install` → คัดลอก `.env` → `key:generate` → `migrate --force`
→ `npm install` → `npm run build` ให้
**แต่** ต้องแก้ `.env` (ค่า DB) และสร้างฐานข้อมูลเปล่าก่อน

### C9. เปิดใช้งาน

| สถานการณ์ | คำสั่ง |
|---|---|
| Dev (ทุก OS) | `composer dev` — รัน serve + queue:listen + pail + vite พร้อมกัน |
| Dev แบบแยกเทอร์มินัล | `php artisan serve` และอีกหน้าต่าง `npm run dev` |
| Herd (Windows) | เปิด `https://manage-server.test` ได้เลยหลัง build |
| AlmaLinux (nginx) | เปิด URL/IP ที่ตั้งใน server block ได้เลยหลัง build |

---

# ส่วน D — การตั้งค่าไฟล์ `.env`

### D1. ค่าหลักของแอป

| ตัวแปร | ค่าที่ควรใช้ | หมายเหตุ |
|---|---|---|
| `APP_NAME` | `Manage-Server` | แสดงในหัวเว็บ/อีเมล |
| `APP_ENV` | `local` (dev) / `production` (จริง) | |
| `APP_KEY` | (จาก `key:generate`) | ห้ามว่าง |
| `APP_DEBUG` | `true` (dev) / `false` (จริง) | |
| `APP_URL` | `https://manage-server.test` หรือ URL จริง | ใช้สร้างลิงก์ในอีเมล |
| `APP_LOCALE` | `th` | |

### D2. ฐานข้อมูล (ต้องเปลี่ยนจากค่า sqlite ใน `.env.example`)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=manage-server
DB_USERNAME=root            # หรือ manage (ถ้าสร้าง user แยกตามข้อ B5)
DB_PASSWORD=<รหัสผ่าน>
```

### D3. Session / Cache / Queue (ปล่อยตามเดิม)

```env
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

ทั้งสามใช้ตารางในฐานข้อมูล — `php artisan migrate` สร้างให้แล้ว

### D4. อีเมล (SMTP ผ่าน Gmail)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=<อีเมล Gmail>
MAIL_PASSWORD=<App Password 16 หลัก>
MAIL_FROM_ADDRESS=<อีเมล Gmail>
MAIL_FROM_NAME="Manage Server"
```

> `MAIL_PASSWORD` ต้องเป็น **Google App Password** (เปิด 2-Step Verification ในบัญชี
> Google ก่อน แล้วสร้างที่ <https://myaccount.google.com/apppasswords>) — รหัสผ่าน
> Gmail ปกติใช้ไม่ได้
> ยังไม่พร้อมตั้ง SMTP: ใช้ `MAIL_MAILER=log` ไปก่อน อีเมลจะถูกเขียนลง `storage/logs/laravel.log`

### D5. การเชื่อมต่อระบบภายนอก (ตั้งเท่าที่ใช้ — ปล่อยว่างได้ ฟีเจอร์นั้นจะถูกซ่อน/ปิดเอง ไม่ error)

| ตัวแปร | ใช้กับ |
|---|---|
| `VSPHERE_URL`, `VSPHERE_USERNAME`, `VSPHERE_PASSWORD` | vCenter API — แหล่งข้อมูลหลักของเกือบทุกหน้า |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | แจ้งเตือน Alarm / Smart Detection / Service / Certificate |
| `TELEGRAM_DAILY_REPORT_BOT_TOKEN`, `TELEGRAM_DAILY_REPORT_CHAT_ID` | ส่ง Daily Report เข้ากลุ่ม Telegram |
| `ANTHROPIC_API_KEY` | คำแนะนำ AI ในหน้า Alarm Notification |
| `GUEST_SSH_USERNAME`, `GUEST_SSH_PASSWORD`, `GUEST_SSH_PORT` | SSH เข้า VM สำหรับ Smart Detection / ตรวจ service |
| `SSH_FALLBACK_USERNAME`, `SSH_FALLBACK_PASSWORD`, `SSH_FALLBACK_SU_PASSWORD` | บัญชีสำรองสำหรับ VM ที่ห้าม root ล็อกอินตรง (ล็อกอินบัญชีธรรมดาแล้ว `su -`) |
| `ENVIRONMENT_SENSOR_TOKEN` | รหัสลับของ endpoint รับค่าอุณหภูมิ/ความชื้นห้องเซิร์ฟเวอร์ |
| `FLEET_SSH_SCANS_ENABLED` | `true`/ไม่ตั้ง = ให้ scheduler SSH เข้า VM ทั้งฟลีตตามรอบ; `false` บนเครื่อง dev เพื่อไม่ให้ยิง SSH |

### D6. ค่าลับที่ต้องกรอกใหม่บนเครื่องใหม่เสมอ (ไม่อยู่ใน Git)

`APP_KEY` · `DB_PASSWORD` · `MAIL_USERNAME` / `MAIL_PASSWORD` · `VSPHERE_*` ·
`TELEGRAM_*` (4 ค่า) · `ANTHROPIC_API_KEY` · `GUEST_SSH_*` · `SSH_FALLBACK_*` ·
`ENVIRONMENT_SENSOR_TOKEN`

---

# ส่วน E — ย้ายฐานข้อมูลและไฟล์ที่ผู้ใช้อัปโหลด

### E1. เริ่มใหม่ (ไม่เอาข้อมูลเดิม)

```bash
php artisan migrate
```

### E2. ย้ายข้อมูลจริงจากเครื่องเดิม

เครื่อง **เดิม**:

```bash
mysqldump -u root -p --single-transaction --default-character-set=utf8mb4 \
  "manage-server" > manage-server-dump.sql
```

เครื่อง **ใหม่** (สร้างฐานข้อมูลเปล่าก่อน):

```bash
mysql -u root -p "manage-server" < manage-server-dump.sql
php artisan migrate            # รัน migration ที่ยังไม่เคยรัน (ถ้ามี)
php artisan migrate:status      # ตรวจว่าครบทุกตัว
```

### E3. ย้ายไฟล์ที่ผู้ใช้อัปโหลด

โฟลเดอร์ `storage/app/public/` **ไม่อยู่ใน Git** — คัดลอกจากเครื่องเดิมมาวางที่เดิม
แล้วรัน `php artisan storage:link`

```
storage/app/public/favicon/...
storage/app/public/it-repair-email/...
```

> AlmaLinux: หลังคัดลอกไฟล์มา อย่าลืม `sudo chown -R nginx:nginx storage` และ
> `sudo restorecon -Rv storage`

---

# ส่วน F — Queue Worker และ Scheduler

### F1. Queue Worker

`QUEUE_CONNECTION=database` — งานเบื้องหลังบางส่วนถูกส่งเข้าคิว ต้องมีตัวรันค้างไว้:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

| OS / วิธี | ทำอย่างไร |
|---|---|
| Dev (ทุก OS) | `composer dev` รัน `queue:listen` ให้อยู่แล้ว |
| Herd (Windows) | เปิดสวิตช์ **Queue** ของเว็บไซต์ในแอป Herd |
| Windows (ไม่มี Herd) | NSSM เป็น Windows Service (ข้อ A2.7) |
| AlmaLinux | systemd unit `manage-server-queue` (ข้อ B9) |

> deploy โค้ดใหม่แล้วสั่ง `php artisan queue:restart` ทุกครั้ง

### F2. Scheduler (งานตามเวลา)

ต้องมี cron รัน `php artisan schedule:run` **ทุก 1 นาที** ไม่งั้นหลายฟีเจอร์ไม่อัปเดต

| OS / วิธี | ทำอย่างไร |
|---|---|
| Herd (Windows) | เปิดสวิตช์ **Scheduler** ของเว็บไซต์ |
| Windows (ไม่มี Herd) | Task Scheduler รัน `php artisan schedule:run` ทุก 1 นาที (ข้อ A2.7) |
| AlmaLinux | `/etc/cron.d/manage-server` (ข้อ B9) |

งานที่ตั้งไว้ (ดูของจริงที่ `routes/console.php` หรือ `php artisan schedule:list`):

| คำสั่ง | ความถี่ | ทำอะไร |
|---|---|---|
| `datastores:snapshot` | ทุกวัน 00:05 | เก็บสถิติ datastore ไว้พยากรณ์วันพื้นที่เต็ม |
| `network-monitors:check` | ทุก 1 นาที | ตรวจ uptime อุปกรณ์เครือข่าย |
| `network-monitors:prune` | ทุกวัน 00:10 | ลบประวัติ network เกิน 24 ชม. |
| `calendar-notices:notify` | ทุก 1 นาที | เตือน Calendar Notice ที่ถึงกำหนดผ่าน Telegram |
| `alarms:notify-telegram` | ตามตั้งค่า (ดีฟอลต์ 1 นาที) | แจ้งเตือน alarm / VM ดับ จาก vCenter |
| `smart-detection:scan` | ตามตั้งค่า (ดีฟอลต์ 15 นาที) | SSH เข้า VM ตรวจความปลอดภัย |
| `certificates:notify-telegram` | ทุกวันตามเวลาที่ตั้ง | เตือนใบรับรองใกล้หมดอายุ |
| `services:check` | ตามตั้งค่า (ดีฟอลต์ 20 นาที) | ตรวจสถานะ systemd service ของแต่ละ host |

> คำสั่งที่ SSH เข้าเครื่องทั้งฟลีต (`smart-detection:scan`, `services:check`)
> ปิดได้ด้วย `FLEET_SSH_SCANS_ENABLED=false` — เหมาะกับเครื่อง dev

---

# ส่วน G — สร้างผู้ใช้ผู้ดูแลระบบคนแรก

ระบบ **ไม่มีหน้าสมัครสมาชิก** ผู้ใช้ใหม่ถูกสร้างจากหน้า Manage Users โดยแอดมิน
ดังนั้นแอดมินคนแรกต้องสร้างผ่าน tinker:

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'ผู้ดูแลระบบ',
    'email' => 'admin@example.com',
    'password' => bcrypt('รหัสผ่านที่ต้องการ'),
    'is_admin' => true,
]);
```

> ถ้าย้ายฐานข้อมูลเดิมมา (ส่วน E) ผู้ใช้เดิมมาครบแล้ว ข้ามขั้นตอนนี้

---

# ส่วน H — ไฟล์/โฟลเดอร์ที่ไม่ได้อยู่ใน Git

| รายการ | วิธีได้มา |
|---|---|
| `vendor/` | `composer install` |
| `node_modules/` | `npm install` |
| `.env` | คัดลอกจาก `.env.example` แล้วแก้ / หรือคัดลอกไฟล์เดิมมา |
| `public/build/` | `npm run build` |
| `public/storage` (symlink) | `php artisan storage:link` |
| `resources/js/{routes,actions,wayfinder}/` | สร้างอัตโนมัติตอน `npm run dev`/`build` (หรือ `php artisan wayfinder:generate`) |
| `bootstrap/ssr/` | สร้างอัตโนมัติ (ถ้าใช้ SSR — ปกติไม่ต้อง) |
| ข้อมูลในฐานข้อมูล | migrate ใหม่ หรือ import dump (ส่วน E) |
| ไฟล์อัปโหลดใน `storage/app/public/` | คัดลอกจากเครื่องเดิม (ส่วน E3) |
| `APP_KEY` และ secret ต่าง ๆ | จากเครื่องเดิม / ผู้ดูแล |

---

# ส่วน I — ตรวจสอบหลังติดตั้ง

```bash
php artisan about            # ภาพรวม: PHP, DB, cache/queue driver
php artisan migrate:status   # migration ครบทุกตัว
php artisan schedule:list    # เห็นรายการงานตามเวลา
npm run build                # build ผ่านไม่มี error
```

- **AlmaLinux:** `systemctl status php-fpm nginx mysqld manage-server-queue` ต้อง `active`
- **Windows/Herd:** เว็บไซต์แสดง `active` ในแอป Herd, สวิตช์ Scheduler/Queue เปิด

เปิดเว็บ → ล็อกอินด้วยแอดมิน → ตรวจว่า:
- หน้า Dashboard โหลดได้ (ถ้าไม่ตั้ง `VSPHERE_*` ข้อมูล vCenter จะว่าง แต่ไม่ error)
- หน้า Settings อัปโหลด favicon ได้ (ทดสอบ `storage:link` + สิทธิ์เขียน)
- กด "ส่งอีเมล" ในหน้า IT Repair เพื่อทดสอบ SMTP

ชุดตรวจคุณภาพโค้ด (ไม่บังคับ มีเฉพาะ dev):

```bash
composer test      # Pint + PHPStan + PHPUnit
composer ci:check   # เพิ่ม ESLint + Prettier + tsc
```

---

# ส่วน J — ขึ้น Production

1. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` เป็น URL จริง (https)
2. cache เพื่อความเร็ว:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan optimize
   ```
   > แก้ `.env` หรือ deploy ใหม่ ต้อง `php artisan optimize:clear` แล้ว cache ใหม่
3. document root ชี้ที่ `public/` เท่านั้น
4. **AlmaLinux:** ตรวจ SELinux (`httpd_can_network_connect=1`, fcontext ของ storage),
   firewalld เปิด 80/443, ใบรับรอง HTTPS ด้วย certbot
5. **AlmaLinux:** สิทธิ์ไฟล์ `sudo chown -R nginx:nginx storage bootstrap/cache`
6. Queue ผ่าน systemd/NSSM, Scheduler ผ่าน cron/Task Scheduler — ตั้งให้ start on boot
7. `npm run build` แล้ว deploy โฟลเดอร์ `public/build/` ไปด้วย — **อย่ารัน `npm run dev` บน production**
8. สำรอง `.env` และฐานข้อมูลไว้ที่ปลอดภัย

---

# ส่วน K — แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| เปิดเว็บขึ้น **500** ทันที | `APP_KEY` ว่าง → `php artisan key:generate`; หรือสิทธิ์เขียน `storage/` ไม่พอ |
| `SQLSTATE... Unknown database 'manage-server'` | ยังไม่สร้างฐานข้อมูล (ข้อ C4) — อย่าลืม backtick ครอบชื่อที่มีขีดกลาง |
| `could not find driver` | ยังไม่เปิด `pdo_mysql` (Windows: แก้ php.ini / Alma: `dnf install php-mysqlnd`) |
| หน้าเว็บโหลดแต่ไม่มี CSS/JS / `Vite manifest not found` | ยังไม่ `npm run build` (หรือใช้ dev แต่ไม่ได้เปิด `npm run dev`) |
| build fail หา `@/routes/...` / `@/actions/...` ไม่เจอ | Wayfinder ยังไม่ generate → `php artisan wayfinder:generate` แล้ว build ใหม่ |
| อัปโหลด favicon แล้วรูป 404 | ยังไม่ `php artisan storage:link` |
| อีเมลไม่ออก / `535 Username and Password not accepted` | `MAIL_PASSWORD` ต้องเป็น **Google App Password** ไม่ใช่รหัสผ่านปกติ; ต้องเปิด 2FA บัญชี Google ก่อน |
| งานตามเวลาไม่ทำงาน | ไม่มี cron `schedule:run` / ไม่ได้เปิดสวิตช์ Scheduler ใน Herd |
| อีเมล/แจ้งเตือนบางอย่างค้าง | ยังไม่ได้รัน `queue:work` (Alma: `systemctl status manage-server-queue`) |
| แก้ `.env` แล้วไม่มีผล | มี config cache → `php artisan config:clear` (หรือ `optimize:clear`) |
| วันที่ error เมื่อ locale = `th` | ยังไม่เปิด extension `intl` |
| **Alma:** 502 Bad Gateway | php-fpm ไม่ทำงาน หรือ `fastcgi_pass` ผิด socket → ตรวจ `systemctl status php-fpm`, path `/run/php-fpm/www.sock` |
| **Alma:** 403 Forbidden ทุกหน้า | SELinux บล็อก หรือ root ไม่ได้ชี้ที่ `public/` → `restorecon -Rv`, ตรวจ `ausearch -m avc -ts recent` |
| **Alma:** เขียน `storage/logs` ไม่ได้ (Permission denied) | `chown -R nginx:nginx storage bootstrap/cache` + `semanage fcontext ... httpd_sys_rw_content_t` + `restorecon` |
| **Alma:** เรียก vSphere/Telegram/SSH จากแอปไม่ได้ (timeout) | `sudo setsebool -P httpd_can_network_connect 1` |
| **Alma:** `php: command not found` ใน cron | ใช้ path เต็ม `/usr/bin/php` ใน crontab/systemd |
| **Windows:** `php` ไม่รู้จักใน cmd | ยังไม่เพิ่ม `C:\php` เข้า PATH (หรือใช้ Herd terminal) |
| SSH เข้า VM ไม่ได้/ช้า | ตรวจ `GUEST_SSH_*` / `SSH_FALLBACK_*`; เครื่อง dev ตั้ง `FLEET_SSH_SCANS_ENABLED=false` |

---

# ส่วน L — สรุปคำสั่งแบบย่อ

## L1. Windows + Herd (มี PHP/Composer จาก Herd, ติดตั้ง Node/Git เพิ่มแล้ว)

```powershell
git clone <repo-url> "$env:USERPROFILE\Herd\manage-server"
cd "$env:USERPROFILE\Herd\manage-server"

composer install
npm install
Copy-Item .env.example .env
#  แก้ .env: APP_URL, DB_DATABASE=manage-server, DB_USERNAME, DB_PASSWORD, MAIL_*, VSPHERE_* ฯลฯ
#  (ชัวร์สุด: คัดลอกไฟล์ .env เดิมมาทั้งไฟล์)
php artisan key:generate

#  สร้าง DB เปล่าชื่อ manage-server (ผ่าน Workbench/HeidiSQL/คำสั่ง mysql)
php artisan migrate            # หรือ import dump ของเดิม
php artisan storage:link
npm run build

#  ในแอป Herd: เปิดสวิตช์ Scheduler + Queue ของเว็บไซต์นี้
#  สร้างแอดมินคนแรกด้วย php artisan tinker (ถ้าไม่ได้ย้าย DB เดิม)
```

เปิด `https://manage-server.test`

## L2. AlmaLinux 9 (ตั้งแต่เครื่องเปล่า)

```bash
# --- ติดตั้งซอฟต์แวร์ ---
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf update -y
sudo dnf module reset php -y && sudo dnf module enable php:remi-8.3 -y
sudo dnf install -y php php-cli php-fpm php-mysqlnd php-mbstring php-xml \
  php-gd php-intl php-bcmath php-zip php-curl php-sodium php-process php-opcache \
  composer nginx mysql-server git policycoreutils-python-utils
curl -fsSL https://rpm.nodesource.com/setup_22.x | sudo bash - && sudo dnf install -y nodejs

# --- เปิดบริการ ---
sudo systemctl enable --now php-fpm nginx mysqld
sudo mysql_secure_installation

# --- ตั้ง php-fpm ให้รันเป็น nginx (แก้ /etc/php-fpm.d/www.conf: user/group/listen.owner/listen.group = nginx) ---
sudo systemctl restart php-fpm

# --- ฐานข้อมูล ---
sudo mysql -e "CREATE DATABASE \`manage-server\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# --- โค้ด ---
sudo git clone <repo-url> /var/www/manage-server
cd /var/www/manage-server
composer install
npm install
sudo cp .env.example .env
sudo -e .env                    # แก้ APP_URL, DB_*, MAIL_*, VSPHERE_* ฯลฯ (หรือวางไฟล์ .env เดิม)
php artisan key:generate
php artisan migrate              # หรือ import dump ของเดิม
php artisan storage:link
npm run build

# --- สิทธิ์ + SELinux ---
sudo chown -R nginx:nginx storage bootstrap/cache
sudo setsebool -P httpd_can_network_connect 1
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/manage-server/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/manage-server/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/manage-server/storage /var/www/manage-server/bootstrap/cache
sudo firewall-cmd --permanent --add-service={http,https} && sudo firewall-cmd --reload

# --- nginx server block: /etc/nginx/conf.d/manage-server.conf (ดูข้อ B6) ---
sudo nginx -t && sudo systemctl reload nginx

# --- queue (systemd, ดูข้อ B9) + scheduler (/etc/cron.d/manage-server) ---
sudo systemctl enable --now manage-server-queue

# --- แอดมินคนแรก (ถ้าไม่ได้ย้าย DB เดิม) ---
php artisan tinker
```
