# Backup and Disaster Recovery Strategy

## 1. Scope of Backups

1. **PostgreSQL Primary Database**: Complete transactional state, accounting ledgers, users, workspaces, communications, automations, and missions.
2. **Private File Storage (S3 / MinIO / Local)**: Invoices, knowledge documents, avatars, contract attachments.
3. **Configuration & Encryption Keys**: `APP_KEY`, `.env` parameters, JWT keys.
4. **Vector Store**: Qdrant vector collections (or reproducible from canonical SQL knowledge files).

---

## 2. Backup Procedures

### PostgreSQL Automated Backup
```bash
#!/usr/bin/env bash
BACKUP_DATE=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="/var/backups/hiddenleaf/db_backup_${BACKUP_DATE}.sql.gz"
pg_dump -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE | gzip > $BACKUP_FILE
aws s3 cp $BACKUP_FILE s3://hiddenleaf-backups/postgres/
```

### Storage Directory Backup
```bash
aws s3 sync /var/www/hiddenleaf-business-os/storage/app s3://hiddenleaf-backups/storage/
```

---

## 3. Restore Verification Procedure

1. **Clean Restoration Target**:
   ```bash
   createdb -h localhost -U postgres hiddenleaf_restore_test
   gunzip -c db_backup_latest.sql.gz | psql -h localhost -U postgres hiddenleaf_restore_test
   ```
2. **Integrity Validation**:
   ```bash
   php artisan hiddenleaf:check --database=hiddenleaf_restore_test
   ```
3. **Data Parity Check**:
   Confirm row counts of `users`, `organizations`, `workspaces`, `sales_invoices`, and `comm_conversations`.
