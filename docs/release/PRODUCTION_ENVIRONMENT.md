# Production Environment & Infrastructure Topology

## 1. Production Target Specification

| Component | Target Requirement | Current State / Configuration | Status |
|---|---|---|---|
| **Operating System** | Ubuntu 22.04 LTS / Debian 12 | Linux x86_64 Cloud VPS / Bare Metal | `READY` |
| **PHP Runtime** | PHP 8.2+ with CLI, FPM, BCMath, Mbstring, PDO_PGSQL, Redis, Intl, XML | Installed with all required extensions | `READY` |
| **Node.js Runtime** | Node.js 20+ LTS & NPM 10+ | Installed for TypeScript/Vite bundling and Gideon runtime | `READY` |
| **Database** | PostgreSQL 15+ Primary Instance | Monotonic sequence locking, JSONB support, transaction isolation | `READY` |
| **Cache & Queue Driver** | Redis 7+ (or Database fallback) | Dedicated Redis instance for caching, queue jobs, and lock synchronization | `READY` |
| **Web Server & Reverse Proxy** | Nginx 1.24+ with HTTP/2 & TLS 1.3 | Configured with gzip compression, security headers, and static caching | `READY` |
| **Process Supervisor** | Supervisor / Systemd | Managing 4 queue workers, 1 scheduler loop, 1 Gideon TS service | `READY` |
| **Gideon TS Runtime** | Node.js TS Service (port 3000) | Local loopback service for RAG embeddings and knowledge processing | `READY` |
| **Vector Store** | Qdrant Vector DB (or Lexical fallback) | Running on port 6333 for dense vector similarity | `READY` |
| **Voice Realtime Agent** | LiveKit Server 1.7+ & Python Worker | WebRTC media server for low-latency bidirectional voice streams | `OPTIONAL / PILOT_FEATURE_FLAG` |
| **Private File Storage** | AWS S3 / MinIO / Local Private Storage | Encrypted private bucket for invoices, knowledge docs, and contracts | `READY` |

---

## 2. Infrastructure Compatibility Gate

* **Shared cPanel Compatibility**: **INCOMPATIBLE** (Shared cPanel cannot run background Supervisor queue workers, persistent Node.js runtimes, or LiveKit WebRTC media servers).
* **Recommended Infrastructure**: Managed Cloud VPS (e.g. AWS EC2, DigitalOcean Droplet, Hetzner Cloud) with Docker or Systemd Supervisor.
