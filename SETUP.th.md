# คู่มือติดตั้งโปรเจกต์ Manage Server บนเครื่องใหม่ (ย้าย/โคลนเครื่อง)

เอกสารนี้อธิบายทุกอย่างที่ต้อง "ติดตั้ง" และ "ตั้งค่า" เพื่อนำโปรเจกต์นี้
ไปรันบนคอมพิวเตอร์เครื่องอื่น ตั้งแต่ซอฟต์แวร์พื้นฐาน ไปจนถึงการย้ายฐานข้อมูล
และรายการที่ต้องทำหลังโคลนเสร็จ

> โปรเจกต์นี้คือ Laravel 13 + Inertia.js + React 19 (TypeScript, Tailwind)
> ฐานข้อมูล MySQL, ใช้ `database` เป็นตัวเก็บ session / cache / queue,
> และมีงานตามเวลา (scheduler) กับ queue worker ที่ต้องรันแยก

---

## สารบัญ

1. [ภาพรวมสิ่งที่ต้องมีบนเครื่องใหม่](#1-ภาพรวมสิ่งที่ต้องมีบนเครื่องใหม่)
2. [ซอฟต์แวร์ที่ต้องติดตั้ง (รายละเอียด)](#2-ซอฟต์แวร์ที่ต้องติดตั้ง-รายละเอียด)
3. [ขั้นตอนติดตั้งโปรเจกต์ (ทีละขั้น)](#3-ขั้นตอนติดตั้งโปรเจกต์-ทีละขั้น)
4. [การตั้งค่าไฟล์ `.env`](#4-การตั้งค่าไฟล์-env)
5. [ฐานข้อมูลและการย้ายข้อมูลเดิม](#5-ฐานข้อมูลและการย้ายข้อมูลเดิม)
6. [Queue Worker และ Scheduler (สำคัญ)](#6-queue-worker-และ-scheduler-สำคัญ)
7. [การ Build ฝั่งหน้าเว็บ (Vite)](#7-การ-build-ฝั่งหน้าเว็บ-vite)
8. [สร้างผู้ใช้ผู้ดูแลระบบคนแรก](#8-สร้างผู้ใช้ผู้ดูแลระบบคนแรก)
9. [ไฟล์/โฟลเดอร์ที่ไม่ได้อยู่ใน Git (ต้องสร้างเอง)](#9-ไฟล์โฟลเดอร์ที่ไม่ได้อยู่ใน-git-ต้องสร้างเอง)
10. [ตรวจสอบหลังติดตั้ง](#10-ตรวจสอบหลังติดตั้ง)
11. [ถ้าจะขึ้น Production](#11-ถ้าจะขึ้น-production)
12. [แก้ปัญหาที่พบบ่อย](#12-แก้ปัญหาที่พบบ่อย)

---

## 1. ภาพรวมสิ่งที่ต้องมีบนเครื่องใหม่

| รายการ | เวอร์ชันขั้นต่ำ | เครื่องต้นฉบับใช้ | บังคับ? |
|---|---|---|---|
| PHP | 8.3 | 8.4.16 | ✅ บังคับ |
| Composer | 2.x | 2.9.3 | ✅ บังคับ |
| Node.js | 20 LTS | 22 LTS ขึ้นไป | ✅ บังคับ |
| npm | 10 | 11.x | ✅ บังคับ (มากับ Node) |
| MySQL / MariaDB | MySQL 8.0 / MariaDB 10.6 | MySQL 8 | ✅ บังคับ |
| Git | ล่าสุด | — | ✅ บังคับ |
| Laravel Herd | 1.x | 1.25.0 | ⭐ แนะนำ (Windows/macOS) |
| เว็บเซิร์ฟเวอร์ (nginx/Apache) | — | ผ่าน Herd | ✅ ถ้าไม่ใช้ Herd |
| Cron / Task Scheduler | — | ผ่าน Herd | ✅ (สำหรับ scheduler) |
| Supervisor / NSSM | — | — | ⭐ แนะนำ (คุม queue worker) |

> **ไม่ต้องมี Redis** ถึงแม้ `.env` จะมีคีย์ `REDIS_*` — โปรเจกต์นี้ตั้ง
> session / cache / queue เป็น `database` ทั้งหมด จึงไม่ต้องติดตั้ง Redis
> และไม่ต้องมี PHP extension `redis`

---

## 2. ซอฟต์แวร์ที่ต้องติดตั้ง (รายละเอียด)

### 2.1 PHP 8.3 ขึ้นไป (แนะนำ 8.4)

ต้องเปิด PHP extension ต่อไปนี้ (ทั้งหมดนี้มีอยู่บนเครื่องต้นฉบับแล้ว):

```
bcmath  ctype  curl  dom  exif  fileinfo  gd  iconv  intl  libxml
mbstring  openssl  pcre  pdo  pdo_mysql  session  simplexml  sodium
tokenizer  xml  xmlreader  xmlwriter  zip
```

เหตุผลของตัวที่มักไม่ได้เปิดโดยดีฟอลต์:

| Extension | ใช้ทำอะไร |
|---|---|
| `pdo_mysql` | เชื่อมต่อฐานข้อมูล MySQL |
| `gd` | สร้าง PDF (Daily Report / รายงานการประเมิน) ผ่าน dompdf และ resize รูป |
| `zip`, `xml`, `simplexml` | อ่าน/เขียนไฟล์ Excel (phpoffice/phpspreadsheet) และ dompdf |
| `intl` | จัดรูปแบบวันที่/ตัวเลขตามภาษา (ตั้ง locale เป็น `th`) |
| `mbstring`, `iconv` | จัดการสตริงภาษาไทย |
| `openssl`, `sodium` | เข้ารหัส session/token, SSH (phpseclib), Fortify passkeys |
| `curl` | เรียก API ภายนอก (vSphere, Telegram, Anthropic) |
| `exif` | อ่าน metadata รูปที่อัปโหลด |

ตรวจว่าเปิดครบ:

```bash
php -m
```

> **Windows:** แนะนำให้ใช้ PHP ที่มากับ **Laravel Herd** (ข้อ 2.6) จะได้ extension ครบอยู่แล้ว
> **Ubuntu/Debian:**
> ```bash
> sudo apt install php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
>   php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
> ```

### 2.2 Composer 2.x

ตัวจัดการแพ็กเกจ PHP — ติดตั้งจาก <https://getcomposer.org/download/>
ตรวจ: `composer -V`

### 2.3 Node.js 20 LTS ขึ้นไป + npm

ใช้ build หน้าเว็บ (Vite 8, React 19)
ติดตั้งจาก <https://nodejs.org> หรือใช้ `nvm`

```bash
node -v   # ต้อง >= 20
npm -v    # ต้อง >= 10
```

### 2.4 MySQL 8.0 / MariaDB 10.6 ขึ้นไป

ต้องรองรับ `utf8mb4` เต็มรูปแบบ
บนเครื่องต้นฉบับใช้ MySQL ที่ `127.0.0.1:3306` ชื่อฐานข้อมูล **`manage-server`**
(มีเครื่องหมายขีดกลาง — เวลาสั่ง SQL ต้องครอบด้วย backtick `` `manage-server` ``)

> Herd Pro มี MySQL ในตัว ถ้าใช้ Herd เวอร์ชันธรรมดา ให้ติดตั้ง MySQL แยก
> เช่น MySQL Community Server, MariaDB, XAMPP, Laragon หรือรันผ่าน Docker

### 2.5 Git

ใช้ `git clone` ตัวโปรเจกต์

### 2.6 Laravel Herd (แนะนำอย่างยิ่งบน Windows / macOS)

<https://herd.laravel.com> — รวม PHP หลายเวอร์ชัน + nginx + dnsmasq (โดเมน `*.test`)
ไว้ในตัวเดียว และมีปุ่มเปิด **Scheduler** และ **Queue** ต่อเว็บไซต์

โปรเจกต์นี้ถูกวางไว้ในโฟลเดอร์ `C:\Users\<user>\Herd\manage-server` อยู่แล้ว
ดังนั้นถ้าติดตั้ง Herd แล้ววางโปรเจกต์ในโฟลเดอร์ `Herd/` จะเข้าถึงได้ทันทีที่
`https://manage-server.test`

**ถ้าไม่ใช้ Herd** ต้องจัดการเอง 3 อย่าง:
1. เว็บเซิร์ฟเวอร์ชี้ document root ไปที่โฟลเดอร์ `public/` (nginx/Apache) หรือใช้ `php artisan serve` ตอน dev
2. ตั้ง cron/Task Scheduler รัน `php artisan schedule:run` ทุก 1 นาที (ข้อ 6)
3. ตั้งบริการรัน `php artisan queue:work` ค้างไว้ (ข้อ 6)

---

## 3. ขั้นตอนติดตั้งโปรเจกต์ (ทีละขั้น)

### 3.1 โคลนโค้ด

```bash
git clone <URL ของ repo> manage-server
cd manage-server
```

> ถ้าใช้ Herd ให้โคลนลงในโฟลเดอร์ `~/Herd/` (หรือโฟลเดอร์ที่ Herd `park` ไว้)

### 3.2 ติดตั้ง dependencies ของ PHP

```bash
composer install
```

### 3.3 ติดตั้ง dependencies ของ Node

```bash
npm install
```

### 3.4 สร้างไฟล์ `.env`

```bash
# macOS/Linux
cp .env.example .env
# Windows PowerShell
Copy-Item .env.example .env
```

จากนั้น **แก้ค่าในไฟล์ `.env`** ตามข้อ 4 (สำคัญที่สุดคือ `DB_*` เพราะ
`.env.example` ตั้งมาเป็น `sqlite` แต่โปรเจกต์นี้ใช้ `mysql`)

### 3.5 สร้าง APP_KEY

```bash
php artisan key:generate
```

### 3.6 สร้างฐานข้อมูลเปล่า

เข้า MySQL แล้วสั่ง:

```sql
CREATE DATABASE `manage-server` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

(ชื่อฐานข้อมูลต้องตรงกับ `DB_DATABASE` ใน `.env`)

### 3.7 รัน migration

```bash
php artisan migrate
```

จะสร้างตารางทั้งหมด รวมถึงตาราง `sessions`, `cache`, `jobs`
(เพราะ session/cache/queue เป็นแบบ `database`)

> ถ้าต้องการข้อมูลตัวอย่างสำหรับทดสอบ: `php artisan db:seed`
> **อย่ารันบนฐานข้อมูลที่ย้ายของจริงมา** — ดูข้อ 5

### 3.8 สร้าง symlink ของ storage

จำเป็นสำหรับรูปที่อัปโหลดในหน้า Settings (favicon, โลโก้อีเมลแจ้งซ่อม)

```bash
php artisan storage:link
```

### 3.9 Build ไฟล์หน้าเว็บ

```bash
npm run build
```

> ขั้นตอนนี้ **ต้องต่ออินเทอร์เน็ต** เพราะ Vite จะดาวน์โหลดฟอนต์ (Bunny Fonts / Instrument Sans)
> และสร้างไฟล์ route helper อัตโนมัติ ผลลัพธ์จะอยู่ที่ `public/build/`

### 3.10 ทางลัด (ทำข้อ 3.2–3.9 บางส่วนในคำสั่งเดียว)

```bash
composer setup
```

สคริปต์นี้จะรัน `composer install`, คัดลอก `.env`, `key:generate`,
`migrate --force`, `npm install`, `npm run build` ให้
**แต่** ต้องแก้ `.env` (ค่า DB) และสร้างฐานข้อมูลเปล่าไว้ก่อน ไม่งั้น `migrate` จะล้มเหลว

### 3.11 เปิดใช้งาน

- **โหมดพัฒนา:** `composer dev`
  (รัน `php artisan serve` + `queue:listen` + `pail` + `vite` พร้อมกัน)
  หรือแยกเป็น 2 เทอร์มินัล: `php artisan serve` และ `npm run dev`
- **ผ่าน Herd:** เปิด `https://manage-server.test` ได้เลย (หลัง `npm run build`)

---

## 4. การตั้งค่าไฟล์ `.env`

### 4.1 ค่าหลักของแอป (ต้องตั้ง)

| ตัวแปร | ค่าที่ควรใช้ | หมายเหตุ |
|---|---|---|
| `APP_NAME` | `Manage-Server` | แสดงในหัวเว็บ/อีเมล |
| `APP_ENV` | `local` (dev) / `production` (จริง) | |
| `APP_KEY` | (ได้จาก `key:generate`) | ห้ามว่าง |
| `APP_DEBUG` | `true` (dev) / `false` (จริง) | |
| `APP_URL` | `https://manage-server.test` หรือ URL จริง | ใช้สร้างลิงก์ในอีเมล |
| `APP_LOCALE` | `th` | |

### 4.2 ฐานข้อมูล (ต้องตั้ง — ค่าใน `.env.example` เป็น sqlite ต้องเปลี่ยน)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=manage-server
DB_USERNAME=root
DB_PASSWORD=<รหัสผ่าน MySQL>
```

### 4.3 Session / Cache / Queue (ปล่อยตามเดิม)

```env
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

ทั้งสามใช้ตารางในฐานข้อมูล — `php artisan migrate` สร้างให้แล้ว ไม่ต้องติดตั้งอะไรเพิ่ม

### 4.4 อีเมล (SMTP — ใช้ Gmail)

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

> `MAIL_PASSWORD` ต้องเป็น **App Password** ของ Google (เปิด 2-Step Verification
> ในบัญชี Google ก่อน แล้วสร้างที่ <https://myaccount.google.com/apppasswords>)
> ใช้รหัสผ่านปกติของ Gmail ไม่ได้
> ถ้ายังไม่ตั้ง SMTP ให้ใช้ `MAIL_MAILER=log` ไปก่อน อีเมลจะถูกเขียนลง `storage/logs/laravel.log`

### 4.5 การเชื่อมต่อระบบภายนอก (ตั้งเท่าที่ใช้ — ปล่อยว่างได้ ฟีเจอร์นั้นจะถูกซ่อน/ปิดเอง)

| ตัวแปร | ใช้กับ |
|---|---|
| `VSPHERE_URL`, `VSPHERE_USERNAME`, `VSPHERE_PASSWORD` | vCenter API — เป็นแหล่งข้อมูลหลักของเกือบทุกหน้า |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | แจ้งเตือน Alarm / Smart Detection / Service / Certificate |
| `TELEGRAM_DAILY_REPORT_BOT_TOKEN`, `TELEGRAM_DAILY_REPORT_CHAT_ID` | ส่ง Daily Report เข้ากลุ่ม Telegram |
| `ANTHROPIC_API_KEY` | คำแนะนำ AI ในหน้า Alarm Notification |
| `GUEST_SSH_USERNAME`, `GUEST_SSH_PASSWORD`, `GUEST_SSH_PORT` | SSH เข้า VM สำหรับ Smart Detection / ตรวจ service |
| `SSH_FALLBACK_USERNAME`, `SSH_FALLBACK_PASSWORD`, `SSH_FALLBACK_SU_PASSWORD` | บัญชีสำรองสำหรับ VM ที่ห้าม root ล็อกอินตรง (ล็อกอินบัญชีธรรมดาแล้ว `su -`) |
| `ENVIRONMENT_SENSOR_TOKEN` | รหัสลับสำหรับ endpoint รับค่าอุณหภูมิ/ความชื้นห้องเซิร์ฟเวอร์ |
| `FLEET_SSH_SCANS_ENABLED` | `true`/ไม่ตั้ง = ให้ scheduler SSH เข้า VM ทุกเครื่องตามรอบ; ตั้ง `false` บนเครื่อง dev เพื่อไม่ให้ยิง SSH ทั้งฟลีต |

### 4.6 ค่าที่เป็นความลับ — ต้องกรอกใหม่บนเครื่องใหม่เสมอ

ไฟล์ `.env` **ไม่ได้อยู่ใน Git** ฉะนั้นต้องเตรียมค่าพวกนี้จากเครื่องเดิม/ผู้ดูแล:

- `APP_KEY`
- `DB_PASSWORD`
- `MAIL_USERNAME`, `MAIL_PASSWORD`
- `VSPHERE_*`
- `TELEGRAM_*` (ทั้ง 4 ค่า)
- `ANTHROPIC_API_KEY`
- `GUEST_SSH_*`, `SSH_FALLBACK_*`
- `ENVIRONMENT_SENSOR_TOKEN`

> วิธีที่ปลอดภัยที่สุดคือ **คัดลอกไฟล์ `.env` เดิมมาทั้งไฟล์** แล้วแก้เฉพาะ
> `APP_URL` / `DB_HOST` ให้ตรงกับเครื่องใหม่

---

## 5. ฐานข้อมูลและการย้ายข้อมูลเดิม

### 5.1 ถ้าเริ่มใหม่ (ไม่เอาข้อมูลเก่า)

```bash
php artisan migrate
```

### 5.2 ถ้าต้องการย้ายข้อมูลจริงจากเครื่องเดิม

บนเครื่อง **เดิม**:

```bash
mysqldump -u root -p --single-transaction --default-character-set=utf8mb4 \
  "manage-server" > manage-server-dump.sql
```

บนเครื่อง **ใหม่** (สร้างฐานข้อมูลเปล่าตามข้อ 3.6 ก่อน):

```bash
mysql -u root -p "manage-server" < manage-server-dump.sql
php artisan migrate           # รัน migration ที่ยังไม่เคยรัน (ถ้ามี)
php artisan migrate:status     # ตรวจว่าครบทุกตัว
```

### 5.3 ย้ายไฟล์ที่ผู้ใช้อัปโหลด

ไฟล์ในหน้า Settings (favicon, โลโก้อีเมลแจ้งซ่อม) เก็บอยู่ใน
`storage/app/public/` — **ไม่ได้อยู่ใน Git** ให้คัดลอกทั้งโฟลเดอร์นี้จากเครื่องเดิมมาวางที่เดิม
แล้วรัน `php artisan storage:link` (ถ้ายังไม่ได้ทำ)

```
storage/app/public/favicon/...
storage/app/public/it-repair-email/...
```

---

## 6. Queue Worker และ Scheduler (สำคัญ)

### 6.1 Queue Worker

`QUEUE_CONNECTION=database` — งานเบื้องหลังบางส่วนถูกส่งเข้าคิว ต้องมีตัวรันคิวค้างไว้:

```bash
php artisan queue:work --queue=default --sleep=3 --tries=3
```

- **Herd:** เปิดสวิตช์ **Queue** ของเว็บไซต์นี้ในแอป Herd
- **Linux (production):** ใช้ **Supervisor** คุมให้รันตลอด
- **Windows (ไม่มี Herd):** ใช้ **NSSM** ทำเป็น Windows Service
- ทุกครั้งที่ deploy โค้ดใหม่ อย่าลืม `php artisan queue:restart`

> ตอน dev ใช้ `composer dev` ได้เลย มันรัน `queue:listen` ให้อัตโนมัติ

### 6.2 Scheduler (งานตามเวลา)

ต้องมี cron รัน `schedule:run` ทุก 1 นาที มิฉะนั้นหลายฟีเจอร์จะไม่อัปเดต:

- **Herd:** เปิดสวิตช์ **Scheduler** ของเว็บไซต์นี้
- **Linux:** `crontab -e` แล้วเพิ่ม
  ```
  * * * * * cd /path/to/manage-server && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Windows (ไม่มี Herd):** สร้าง Task Scheduler ให้รัน `php artisan schedule:run` ทุก 1 นาที

งานที่ตั้งไว้ (ดูของจริงได้ที่ `routes/console.php` หรือ `php artisan schedule:list`):

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

> คำสั่งกลุ่มที่ SSH เข้าเครื่องทั้งฟลีต (`smart-detection:scan`, `services:check`)
> จะถูกปิดถ้าตั้ง `FLEET_SSH_SCANS_ENABLED=false` — เหมาะกับเครื่อง dev

---

## 7. การ Build ฝั่งหน้าเว็บ (Vite)

| คำสั่ง | ใช้เมื่อ |
|---|---|
| `npm run dev` | ตอนพัฒนา (hot reload) — ต้องเปิดค้างไว้ |
| `npm run build` | ก่อนใช้งานจริง / หลังดึงโค้ดใหม่ — สร้าง `public/build/` |

สิ่งที่ Vite สร้างให้อัตโนมัติตอน `dev`/`build` และ **ไม่ได้อยู่ใน Git**:

- `resources/js/routes/`, `resources/js/actions/`, `resources/js/wayfinder/`
  (ตัวช่วยเรียก route จากฝั่ง React — สร้างโดยปลั๊กอิน Wayfinder)
- `public/build/` (ไฟล์ JS/CSS ที่ compile แล้ว)
- `public/hot` (มีเฉพาะตอน `npm run dev` ทำงาน)

ถ้าเจอ error ว่าหา `@/routes/...` หรือ `@/actions/...` ไม่เจอ ให้รัน:

```bash
php artisan wayfinder:generate
# หรือแค่รัน npm run dev / npm run build ใหม่
```

> ต้องต่ออินเทอร์เน็ตตอน build (โหลดฟอนต์จาก Bunny Fonts)

---

## 8. สร้างผู้ใช้ผู้ดูแลระบบคนแรก

ระบบ **ไม่มีหน้าสมัครสมาชิก** — ผู้ใช้ใหม่ต้องถูกสร้างจากหน้า Manage Users
โดยแอดมิน ดังนั้นแอดมินคนแรกต้องสร้างเองผ่าน tinker:

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

> ถ้าย้ายฐานข้อมูลเดิมมา (ข้อ 5.2) ผู้ใช้เดิมมาครบอยู่แล้ว ข้ามขั้นตอนนี้ได้

---

## 9. ไฟล์/โฟลเดอร์ที่ไม่ได้อยู่ใน Git (ต้องสร้างเอง)

| รายการ | วิธีได้มา |
|---|---|
| `vendor/` | `composer install` |
| `node_modules/` | `npm install` |
| `.env` | คัดลอกจาก `.env.example` แล้วแก้ / หรือคัดลอกไฟล์เดิมมา |
| `public/build/` | `npm run build` |
| `public/storage` (symlink) | `php artisan storage:link` |
| `resources/js/{routes,actions,wayfinder}/` | สร้างอัตโนมัติตอน `npm run dev`/`build` |
| `bootstrap/ssr/` | สร้างอัตโนมัติ (ถ้าใช้ SSR) |
| ข้อมูลในฐานข้อมูล | migrate ใหม่ หรือ import dump (ข้อ 5) |
| ไฟล์อัปโหลดใน `storage/app/public/` | คัดลอกจากเครื่องเดิม (ข้อ 5.3) |
| `APP_KEY` และ secret ต่าง ๆ | จากเครื่องเดิม / ผู้ดูแล |

---

## 10. ตรวจสอบหลังติดตั้ง

```bash
php artisan about            # ดูภาพรวม: PHP, DB, cache/queue driver
php artisan migrate:status   # migration ครบทุกตัว
php artisan schedule:list    # เห็นรายการงานตามเวลา
php artisan storage:link     # (ถ้ายังไม่ทำ)
npm run build                # build ผ่านไม่มี error
```

เปิดเว็บ → ล็อกอินด้วยแอดมินที่สร้างไว้ → ตรวจว่า:
- หน้า Dashboard โหลดได้ (ถ้าไม่ได้ตั้ง `VSPHERE_*` ข้อมูล vCenter จะว่าง แต่หน้าไม่ error)
- หน้า Settings อัปโหลด favicon ได้ (ทดสอบ `storage:link`)
- ลองกด "ส่งอีเมล" ในหน้า IT Repair เพื่อทดสอบ SMTP

ชุดตรวจคุณภาพโค้ด (ไม่บังคับ มีเฉพาะ dev dependencies):

```bash
composer test      # Pint + PHPStan + PHPUnit
composer ci:check   # เพิ่ม ESLint + Prettier + tsc
```

---

## 11. ถ้าจะขึ้น Production

1. ตั้ง `APP_ENV=production` และ `APP_DEBUG=false`
2. ตั้ง `APP_URL` เป็น URL จริง (https)
3. cache ค่าต่าง ๆ เพื่อความเร็ว:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan optimize
   ```
   > ทุกครั้งที่แก้ `.env` หรือ deploy ใหม่ ต้องรัน `php artisan optimize:clear` แล้ว cache ใหม่
4. document root ของเว็บเซิร์ฟเวอร์ต้องชี้ที่ `public/` เท่านั้น
5. สิทธิ์ไฟล์ (Linux): ให้ user ของเว็บเซิร์ฟเวอร์ (เช่น `www-data`) เขียน
   `storage/` และ `bootstrap/cache/` ได้
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R ug+rw storage bootstrap/cache
   ```
6. ตั้ง Supervisor คุม `queue:work` และ cron คุม `schedule:run` (ข้อ 6)
7. ตั้ง HTTPS (Let's Encrypt / Herd จัดการให้อัตโนมัติสำหรับ `.test`)
8. `npm run build` แล้ว deploy โฟลเดอร์ `public/build/` ไปด้วย (อย่ารัน `npm run dev` บน production)

---

## 12. แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| เปิดเว็บขึ้น **500** ทันที | `APP_KEY` ว่าง → `php artisan key:generate`; หรือสิทธิ์เขียน `storage/` ไม่พอ |
| `SQLSTATE... Unknown database 'manage-server'` | ยังไม่สร้างฐานข้อมูล → ดูข้อ 3.6 (อย่าลืม backtick ครอบชื่อที่มีขีดกลาง) |
| `could not find driver` | ยังไม่เปิด extension `pdo_mysql` |
| หน้าเว็บโหลดแต่ไม่มี CSS/JS / `Vite manifest not found` | ยังไม่ `npm run build` (หรือใช้ dev แต่ไม่ได้เปิด `npm run dev`) |
| build fail: หา `@/routes/register` / `@/actions/...` ไม่เจอ | Wayfinder ยังไม่ generate → `php artisan wayfinder:generate` แล้ว build ใหม่ |
| อัปโหลด favicon แล้วรูปไม่ขึ้น (404) | ยังไม่ `php artisan storage:link` |
| อีเมลไม่ออก / `535 Username and Password not accepted` | `MAIL_PASSWORD` ต้องเป็น **Google App Password** ไม่ใช่รหัสผ่านปกติ; ต้องเปิด 2FA ของบัญชี Google ก่อน |
| งานตามเวลาไม่ทำงาน (datastore/uptime ไม่อัปเดต) | ยังไม่มี cron `schedule:run` หรือยังไม่เปิดสวิตช์ Scheduler ใน Herd |
| อีเมล/แจ้งเตือนบางอย่างค้าง ไม่ส่ง | ยังไม่ได้รัน `php artisan queue:work` |
| แก้ `.env` แล้วไม่มีผล | มี config cache อยู่ → `php artisan config:clear` (หรือ `optimize:clear`) |
| ตั้ง locale เป็น `th` แล้ววันที่ยัง error | ยังไม่เปิด extension `intl` |
| SSH เข้า VM ไม่ได้ / ช้า | ตรวจ `GUEST_SSH_*` / `SSH_FALLBACK_*`; บนเครื่อง dev ตั้ง `FLEET_SSH_SCANS_ENABLED=false` เพื่อปิดการยิง SSH ทั้งฟลีตจาก scheduler |

---

## สรุปคำสั่งแบบย่อ (เครื่องใหม่ที่มี PHP/Composer/Node/MySQL/Herd แล้ว)

```bash
git clone <repo-url> manage-server
cd manage-server

composer install
npm install

cp .env.example .env
#  แก้ .env: APP_URL, DB_DATABASE=manage-server, DB_USERNAME, DB_PASSWORD,
#           MAIL_*, VSPHERE_*, TELEGRAM_*, ANTHROPIC_API_KEY, GUEST_SSH_* ฯลฯ
#  (ทางที่ชัวร์ที่สุด: คัดลอกไฟล์ .env เดิมมาทั้งไฟล์)

php artisan key:generate

mysql -u root -p -e 'CREATE DATABASE `manage-server` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
php artisan migrate                # หรือ import dump ของเดิม
php artisan storage:link
npm run build

#  เปิดสวิตช์ Scheduler + Queue ในแอป Herd  (หรือใช้ cron + supervisor)
#  สร้างแอดมินคนแรกด้วย php artisan tinker (ถ้าไม่ได้ย้าย DB เดิมมา)
```

เปิด `https://manage-server.test` แล้วล็อกอินได้เลย
