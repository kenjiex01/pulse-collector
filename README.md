# Pulse

Laravel + NativePHP desktop app (`biometric-collector`) na kumokolekta ng attendance logs mula sa **ZkTeco** devices (hal. **K30**) per campus. Gumagamit ng **SQLite** locally. Tuwing may **bagong** logs na na-collect sa isang run, gumagawa ng **gzipped JSON** (`.json.gzip`) at ina-upload sa **S3**. Default schedule: **every 5 minutes**.

## Features

- Maraming **campus**, bawat campus may isa o higit pang device
- TCP connection sa device (default port **4370**)
- Deduplication ng punches sa SQLite
- Gzipped JSON export para sa downstream ingestion
- S3 upload **lang kapag may bagong logs** sa cycle na yun
- Dashboard (web o NativePHP window) para sa status at manual collect

## Requirements

- PHP 8.3+
- Composer
- Node.js (para sa NativePHP / Vite kung kailangan)
- Network access sa LAN ng ZkTeco devices
- AWS S3 credentials (same `DB_BACKUP_S3_*` as People360)

## Setup

```bash
cd biometric-collector
composer install
bash scripts/fetch-zkteco.sh   # vendored msaied/zkteco under lib/zkteco-php (required for desktop builds)
composer dump-autoload -o
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

I-update ang `.env`:

- `BIOMETRIC_COLLECT_INTERVAL_MINUTES=5`
- `DB_BACKUP_S3_BUCKET=skolaris-payroll-backups-prod`
- `DB_BACKUP_S3_REGION=ap-southeast-2`
- `DB_BACKUP_S3_KEY` / `DB_BACKUP_S3_SECRET` (same as People360)
- `BIOMETRIC_S3_ENABLED=true`
- `BIOMETRIC_S3_PREFIX=biometric_logs`

I-edit ang sample devices sa `database/seeders/BiometricCollectorSeeder.php` (IP, campus, comm key) o mag-insert sa tables `campuses` at `biometric_devices`.

## Running the collector

### Scheduler (every 2 minutes)

Sa isang terminal:

```bash
php artisan schedule:work
```

O sa production/cron:

```bash
* * * * * cd /path/to/biometric-collector && php artisan schedule:run >> /dev/null 2>&1
```

Manual run:

```bash
php artisan biometric:collect
```

### Web UI

```bash
php artisan serve
```

Buksan ang `/` para sa dashboard.

## Desktop (NativePHP)

Kapag nag-dev mula sa **Cursor** (Electron IDE), i-unset muna ang `ELECTRON_RUN_AS_NODE` — kung hindi, hindi magbubukas ang window:

```bash
unset ELECTRON_RUN_AS_NODE
./scripts/run-native-dev.sh
```

O sa Terminal.app (hindi Cursor integrated terminal):

```bash
composer run native:dev
```

### Desktop build + S3 installer publish

```bash
# Bump NATIVEPHP_APP_VERSION in .env first
./scripts/build-desktop.sh   # macOS + Windows; uploads to S3 when DB_BACKUP_S3_* is set
```

Installers are published to:

`s3://skolaris-payroll-backups-prod/biometric_installer/`

Stable overwrite names (latest set only — old objects under the prefix are deleted):

- `Pulse-setup.exe`
- `Pulse-arm64.dmg`
- `latest.json` (semver + artifact keys)

Set in `.env` (same credentials as People360 cloud backup):

- `DB_BACKUP_S3_BUCKET=skolaris-payroll-backups-prod`
- `DB_BACKUP_S3_REGION=ap-southeast-2`
- `DB_BACKUP_S3_KEY` / `DB_BACKUP_S3_SECRET`
- `DESKTOP_INSTALLER_UPDATE_ENABLED=true`
- `DESKTOP_INSTALLER_S3_PREFIX=biometric_installer`

Kapag may mas bagong version sa S3 `latest.json`, ang desktop app ay magpapakita ng **blocking Update required** modal. Ang current installed version ay nasa header (`vX.Y.Z`).

## ZkTeco library

Gumagamit ng [`msaied/zkteco`](https://packagist.org/packages/msaied/zkteco) (TCP protocol, K30-compatible). Kailangan nasa **same LAN** ang collector machine at ang device.

## Data model

| Table | Purpose |
| --- | --- |
| `campuses` | Campus code at name |
| `biometric_devices` | IP, port, model (K30), comm key, per campus |
| `biometric_attendance_logs` | Na-collect na punches |
| `log_export_batches` | SQL file / S3 metadata per export |

## S3 object layout (logs)

Bucket: `skolaris-payroll-backups-prod` — prefix [`biometric_logs/`](https://ap-southeast-2.console.aws.amazon.com/s3/buckets/skolaris-payroll-backups-prod?region=ap-southeast-2&prefix=biometric_logs/&showversions=false)

```
biometric_logs/{YYYY}/{MM}/{biometric_name}/{biometric_name}_YYYYMMDDHHMMSS.json.gzip
```

Example:

```
biometric_logs/2026/08/Cainta-Front-Desk/Cainta-Front-Desk_20260812114530.json.gzip
```

Itakda ang collector / biometric name sa dashboard (naka-save sa SQLite). Kung walang name, gagamitin ang hostname ng machine bilang slug.

## Notes

- Kung `BIOMETRIC_S3_ENABLED=false` o walang `DB_BACKUP_S3_*`, nai-save pa rin ang `.json.gzip` sa `storage/app/biometric-exports/`.
- Ang file ay gzipped JSON (`Content-Type: application/gzip`); i-gunzip bago i-parse.
