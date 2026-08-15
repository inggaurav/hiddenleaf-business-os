# Queue Workloads & Worker Architecture

## 1. Background Workloads Inventory

| Workload Category | Job Class / Process | Driver | Concurrency & Timeout | Retry Policy |
|---|---|---|---|---|
| **Transactional Email** | `Illuminate\Mail\SendQueuedMailable` | `database` / `redis` | 3 attempts, 60s timeout | Exponential backoff (10s, 30s, 60s) |
| **Communication Sync** | `App\Domain\Communications\Sync\CommunicationSyncJob` | `database` / `redis` | 1 worker/account, 120s timeout | 2 attempts, backoff 30s |
| **Mr. Fox Async Mission Step** | `App\Domain\Automation\Missions\ExecuteMissionStepJob` | `database` / `redis` | 1 worker/workspace, 180s timeout | 1 attempt (prevents duplicate tool calls) |
| **Automation Rules** | `App\Domain\Automation\Execution\ExecuteAutomationJob` | `database` / `redis` | High throughput, 60s timeout | 3 attempts with idempotency check |
| **Knowledge Document Ingestion** | `App\Domain\MrFox\Knowledge\IngestDocumentJob` | `database` / `redis` | 1 worker/document, 300s timeout | 2 attempts, mark `failed` in DB |
| **Webhook Delivery & Ingestion** | `App\Domain\Communications\Webhooks\ProcessWebhookJob` | `database` / `redis` | Concurrency safe, 30s timeout | 3 attempts with HMAC idempotency |

---

## 2. Supervisor Configuration Example

```ini
[program:hiddenleaf-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/hiddenleaf-business-os/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=180
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/hiddenleaf-worker.log
stopwaitsecs=3600
```
