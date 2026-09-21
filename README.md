# Manage Server

An internal IT operations dashboard for monitoring and administering a VMware vSphere environment — hosts, VMs, datastores, alarms, performance, security, and general network infrastructure — from a single web app.

Built with [Laravel](https://laravel.com) + [Inertia.js](https://inertiajs.com) + [React](https://react.dev), using the Laravel React starter kit.

## Features

- **Dashboard** — cluster/host/VM overview, top CPU consumers, datastore usage, server room temperature/humidity (via a pushed sensor reading), live ping checks.
- **Manage Hosts / Manage VMs** — local records synced against vCenter, with certificate-expiry tracking and bulk certificate import for VMs.
- **Certificate Expiration** — dedicated view of upcoming VM certificate expiries, with daily Telegram warnings on a configurable threshold.
- **Appliance Health** — vCenter Server Appliance status.
- **Daily Report** — pulls a daily snapshot from vCenter, tracks incidents, and exports to PDF/Telegram.
- **Calendar Notice** — a shared events/reminders calendar with Telegram reminders fired at each notice's set time.
- **IT Repair** — a helpdesk ticket board for repair/service requests, with a public no-login request form and a public tracker (by email) for requesters to follow status and rate a resolved request; email notifications on updates.
- **Service Evaluation** — satisfaction scoring (configurable criteria) on completed IT Repair requests, with monthly/period reporting and PDF export.
- **Alarm Notification** — live vCenter alarms plus down/powered-off VM detection, with AI-assisted hints (via the Anthropic API) and Telegram alerts.
- **Datastore** — capacity trends and a fill-up projection (date datastores are expected to run out of space).
- **Network Infrastructure** — self-service uptime monitoring (WAN, Gateway, Services, DNS, Switch, Server categories) with ping/HTTP/TCP/DNS checks, a live status board, and a 1-hour heartbeat/response-time history, in the spirit of Uptime Kuma.
- **Map Network** — a live map (OpenStreetMap/Leaflet, no API key required) plotting network switches by location — either a pasted Google Maps link or manually entered latitude/longitude — pinged every 20 seconds and color-coded Up/Down.
- **Performance** — vCenter performance metrics browser (CPU, memory, etc.) per entity.
- **Smart Detection** — SSHes into VMs to check for brute-force attempts, suspicious processes, malware indicators, unexpected open ports/services, and failed services, with Telegram alerts on new findings.
- **Mod Security** — ModSecurity log viewer.
- **Services** — SSH-based systemd service monitoring per host, with a live status board and Telegram/email alerts (scoped per service) on services found down.
- **IT Assets** (ครุภัณฑ์ไอที) — an IT asset registry with QR-coded stickers: printable labels, a camera-based scanner, and a public login-free lookup/self-report page (found/normal · damaged · moved · missing, with photo/note/GPS) per asset. Includes category management, an inspection/movement history timeline per asset, scheduled asset-counting rounds, and Excel/PDF export of the registry.
- **Manage Users / Activity Log / Settings** — admin-only user management with per-page permission grants, an audit log of every user action (with IP), and system-wide settings (maintenance mode, alert thresholds, session timeout, per-page enable/disable, branding).

## Tech Stack

- **Backend:** PHP 8.3+, Laravel 13, Inertia.js (server adapter), Laravel Fortify (auth, 2FA, passkeys)
- **Frontend:** React 19, TypeScript, Tailwind CSS, shadcn/ui (Radix primitives), Recharts, Leaflet
- **Database:** SQLite by default (see `.env.example`); any Laravel-supported database works
- **Other integrations:** VMware vSphere API, Telegram Bot API, Anthropic API (Claude, for AI-assisted alarm hints), SSH (for Smart Detection and Services), PhpSpreadsheet + dompdf (Excel/PDF exports, with Thai-font PDF support), `qrcode`/`qr-scanner` (IT Assets QR stickers and camera scanning), mail (IT Repair notifications)

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ and npm
- A reachable vCenter instance (for most pages to show live data)

## Getting Started

```bash
composer setup
```

This installs PHP/Node dependencies, copies `.env.example` to `.env`, generates an app key, runs migrations, and builds frontend assets in one go. Equivalent manual steps:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Then expose the public storage disk (needed for favicon uploads in Settings):

```bash
php artisan storage:link
```

Create your first user directly in the database or via `php artisan tinker` and mark them `is_admin` — there is no public self-registration; new users are created from **Manage Users** by an admin.

### Development

```bash
composer dev
```

Runs the Laravel server, queue listener, log viewer (Pail), and Vite dev server together (via `php artisan dev`). Alternatively, run `php artisan serve` and `npm run dev` in separate terminals.

### Testing & Linting

```bash
composer test       # config:clear, Pint (PHP style), PHPStan, PHPUnit
composer ci:check    # the above plus ESLint, Prettier, and tsc --noEmit
```

## Configuration

Set these in `.env` as needed — a feature degrades gracefully (hidden data, disabled action) rather than erroring when its config is left blank:

| Variable | Used for |
|---|---|
| `VSPHERE_URL`, `VSPHERE_USERNAME`, `VSPHERE_PASSWORD` | vCenter API access — powers most of the app |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` | Alarm/Smart Detection notifications |
| `TELEGRAM_DAILY_REPORT_BOT_TOKEN`, `TELEGRAM_DAILY_REPORT_CHAT_ID` | Daily Report delivery |
| `ANTHROPIC_API_KEY` | AI-assisted hints on the Alarm Notification page |
| `GUEST_SSH_USERNAME`, `GUEST_SSH_PASSWORD`, `GUEST_SSH_PORT` | Smart Detection's and Services' SSH-based checks |
| `FLEET_SSH_SCANS_ENABLED` | Master off-switch for both SSH-based scheduled checks (Smart Detection, Services) — default `true`; set `false` to avoid SSHing into the fleet from a dev box |
| `MAIL_*` (standard Laravel mail config) | IT Repair status-update emails to requesters |
| `ENVIRONMENT_SENSOR_TOKEN` | Shared secret for the server room temperature/humidity sensor's push endpoint |

## Scheduled Tasks

Several features depend on Laravel's scheduler actually running — a `* * * * * php artisan schedule:run` cron entry (or Herd's per-site "Scheduler" toggle):

| Command | Frequency | Purpose |
|---|---|---|
| `datastores:snapshot` | Daily at 00:05 | Datastore Page's fill-up projection |
| `network-monitors:check` | Every minute (per-monitor interval) | Network Infrastructure uptime checks |
| `network-monitors:prune` | Daily at 00:10 | Trims Network Infrastructure history older than 24 hours |
| `calendar-notices:notify` | Every minute (per-notice `remind_at`) | Calendar Notice Telegram reminders |
| `alarms:notify-telegram` | Configurable (Settings → Telegram Notifications), default every 1 minute | vCenter alarm / down-VM Telegram alerts |
| `smart-detection:scan` | Configurable, default every 15 minutes | Smart Detection's SSH-based checks |
| `certificates:notify-telegram` | Configurable, default daily at 08:00 | VM certificate-expiry Telegram warnings |
| `services:check` | Configurable, default every 20 minutes | Services page's SSH-based systemd checks + Telegram/email alerts |

The last four read their enabled/interval settings from **Settings → Telegram Notifications**, falling back to the defaults above; `smart-detection:scan` and `services:check` are also gated by `FLEET_SSH_SCANS_ENABLED`.

Map Network's switch pings run client-side (every 20 seconds while the page is open) rather than on the scheduler, since 20 seconds is finer-grained than a cron job can go.

## Permissions

Every page except user management, the activity log, and settings can be individually granted to a user (`App\Support\Permissions::PAGES`). Admins bypass this entirely; a page can also be disabled app-wide from **Settings → Menus**, which takes priority over any individual grant.

## License

MIT
