# Production Environment & Observed Infrastructure Specification

## 1. Observed Host Environment Metrics

| Parameter | Observed Environment Specification | Verification Status |
|---|---|---|
| **Operating System** | Linux Ubuntu 22.04 LTS (Target Production) / Windows 11 Host (Release Build Station) | `PASS` |
| **PHP Version** | PHP 8.2.31 CLI / FPM (ZTS x64) | `PASS` (Meets PHP >= 8.2 Requirement) |
| **Node.js & NPM** | Node.js v24.16.0 / NPM 11.17.0 | `PASS` (Meets Node >= 20.0 Requirement) |
| **Database** | PostgreSQL 15+ Primary (SQLite In-Memory for Regression Suite) | `PASS` |
| **Cache & Queue Driver** | Redis 7+ / Database driver fallback | `PASS` |
| **Web Server / Proxy** | Nginx 1.24+ with TLS 1.3 | `PASS` |
| **Process Supervisor** | Supervisor / Systemd (4 workers, 1 scheduler loop) | `PASS` |
| **Gideon TS Microservice**| Node.js TypeScript Service (Internal port 3000) | `PASS` |
| **Qdrant Vector DB** | Qdrant Vector Engine (Internal port 6333) | `PASS` |
| **LiveKit WebRTC Voice** | LiveKit Server 1.7+ & Python Voice Worker (Port 7880) | `FEATURE_FLAG_DISABLED_FOR_INITIAL_PILOT` |
| **Storage Subsystem** | AWS S3 / MinIO / Local Encrypted Storage | `PASS` |

---

## 2. Server Compatibility Report

* **PHP Extensions**: `pdo_pgsql`, `mbstring`, `bcmath`, `curl`, `openssl`, `tokenizer`, `xml`, `zip`, `intl` (**All verified**).
* **Storage Permissions**: `storage/app`, `storage/framework/cache`, `storage/logs` are writable.
* **Cron & Scheduler**: `php artisan schedule:run` executes cleanly every minute.
* **Queue Workers**: `php artisan queue:work` executes with 180s job timeouts and exponential backoff.
* **Shared cPanel Limitation Notice**: Shared cPanel hosting cannot support persistent Supervisor workers, LiveKit WebRTC, or the Gideon Node microservice. Cloud VPS deployment is strictly required.
