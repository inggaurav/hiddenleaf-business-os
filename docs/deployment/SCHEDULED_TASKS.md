# Scheduled Tasks & Cron Strategy

## 1. Scheduled Tasks Inventory

| Command / Task | Frequency | Purpose | Concurrency Guard |
|---|---|---|---|
| `php artisan communications:sync` | Every 5 minutes | Polls connected Gmail/Slack/Social accounts for new inbound messages | `withoutOverlapping()` |
| `php artisan invoices:check-aging` | Daily at 00:00 | Recalculates invoice aging buckets and triggers overdue signals | `withoutOverlapping()` |
| `php artisan trials:check-expiration` | Daily at 01:00 | Transitions expired 14-day trial organizations to read-only state | `withoutOverlapping()` |
| `php artisan proposals:expire-stale` | Hourly | Auto-expires unreviewed Mr. Fox action proposals older than 24 hours | `withoutOverlapping()` |
| `php artisan queue:prune-failed` | Daily at 02:00 | Prunes failed jobs older than 7 days | `onOneServer()` |
| `php artisan telescope:prune` | Daily at 03:00 | Prunes application debug entries | `onOneServer()` |

---

## 2. Crontab Setup

In Linux production environment:
```cron
* * * * * cd /var/www/hiddenleaf-business-os && php artisan schedule:run >> /dev/null 2>&1
```
