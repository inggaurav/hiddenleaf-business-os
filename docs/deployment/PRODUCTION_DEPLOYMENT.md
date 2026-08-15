# Production Deployment Topology & Strategy

## 1. Production Topology

```text
                                 INTERNET
                                    │
                                    ▼
                         CLOUDFLARE / REVERSE PROXY
                         (SSL Termination, WAF, CDN)
                                    │
                                    ▼
                           NGINX WEB SERVER
                                    │
               ┌────────────────────┴────────────────────┐
               ▼                                         ▼
     LARAVEL APP (PHP 8.2+ FPM)                 LIVEKIT VOICE SERVER
     (Web & API Controllers, Inertia)           (WebRTC Realtime Media)
               │                                         │
               ├─────────────────────────────────────────┤
               ▼                                         ▼
      POSTGRESQL 15+ PRIMARY                     GIDEON NODE RUNTIME
      (Transactions & Data)                     (Embeddings & RAG)
               │                                         │
               ├─────────────────────────────────────────┤
               ▼                                         ▼
          REDIS 7+                                 QDRANT VECTOR DB
      (Queue, Cache, Sessions)                  (Semantic Vectors)
```

---

## 2. Zero-Downtime Deployment Workflow

1. **Pull Latest Code**:
   ```bash
   git pull origin main
   ```
2. **Install Production Dependencies**:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```
3. **Run Database Migrations**:
   ```bash
   php artisan migrate --force
   ```
4. **Optimize Configurations & Routes**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. **Restart Background Queues & Horizon**:
   ```bash
   php artisan queue:restart
   ```
6. **Execute Diagnostic Health Check**:
   ```bash
   php artisan hiddenleaf:check
   curl -f http://localhost/health/ready || exit 1
   ```
