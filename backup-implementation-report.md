# Database Backup Implementation Report

**Project:** In Time — Services Marketplace
**Topic:** Automatic encrypted backups for the production MySQL database
**Date:** 2026-08-18

---

## 1. What this backup does

Every night at 03:30, the server makes a full copy of the production database, compresses it, and encrypts it with a secret passphrase. The encrypted file is stored on the server for 14 days, after which old backups are deleted automatically.

A full backup means that if the database is ever lost or damaged (disk failure, accidental deletion, corrupted data), the entire system — users, wallets, chat messages, serving requests, escrow transactions — can be restored to the state of the last backup.

---

## 2. How the backup works (the pipeline)

The backup is a chain of four steps, connected by pipes:

```
mysqldump (extract data)
    -> gzip (compress, smaller file)
    -> openssl (encrypt with passphrase)
    -> backup file (.sql.gz.enc)
```

| Step | Tool | Purpose |
|------|------|---------|
| 1. Extract | `mysqldump` | Reads all tables and data from the MySQL database and produces plain SQL text. Uses `--single-transaction` so the backup happens in one consistent snapshot **without stopping the app**. |
| 2. Compress | `gzip` | Shrinks the file (about 4x smaller), saving disk space. |
| 3. Encrypt | `openssl` | Encrypts the compressed file with AES-256-CBC using a secret passphrase (`BACKUP_PASSPHRASE`). The backup file is useless without this passphrase. |
| 4. Store | `backup.sh` | Writes the encrypted file into `/opt/in-time/backups/` with a timestamp name. |

---

## 3. The backup script

The script is a simple shell file stored on the server at `/opt/in-time/backup.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /opt/in-time

# Load DB_PASSWORD + BACKUP_PASSPHRASE from server .env
set -a; . ./.env; set +a

STAMP=$(date +%Y%m%d_%H%M%S)
OUT=/opt/in-time/backups/in_time_${STAMP}.sql.gz.enc

docker compose -f docker-compose.prod.yml exec -T \
  -e MYSQL_PWD="$DB_PASSWORD" db \
  mysqldump -u in_time --single-transaction --no-tablespaces --routines --events in_time \
  | gzip \
  | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE \
  > "$OUT"

find /opt/in-time/backups -name 'in_time_*.enc' -mtime +14 -delete

echo "$(date) backup OK: $(stat -c%s "$OUT") bytes -> $OUT"
```

**Explanation of the script lines:**

| Line | What it does |
|------|--------------|
| `set -euo pipefail` | If any step fails, the script stops with an error instead of silently producing a broken backup. |
| `. ./.env` | Reads the secret values (`DB_PASSWORD`, `BACKUP_PASSPHRASE`) from the server's environment file — secrets are never written inside the script. |
| `STAMP=$(date ...)` | Creates a unique timestamp for the backup filename (e.g. `in_time_20260818_084646.sql.gz.enc`). |
| `docker compose exec -T db mysqldump ...` | Runs the MySQL dump tool **inside** the database container. The `--single-transaction` flag takes a consistent snapshot without locking tables, so the app keeps working normally during the backup. `--no-tablespaces` avoids needing extra database privileges. |
| `\| gzip` | Compresses the dump. |
| `\| openssl enc -aes-256-cbc -pbkdf2` | Encrypts it with AES-256 (strong encryption) using the passphrase from the environment. |
| `find ... -mtime +14 -delete` | Deletes backups older than 14 days (automatic retention). |
| `echo ... bytes` | Writes a confirmation line with the file size so we can see the backup succeeded. |

---

## 4. Setup commands (what was done on the server)

All commands were run on the production server as root.

**Step 1 — Generate a secret passphrase and add it to the environment file**

```bash
echo "BACKUP_PASSPHRASE=$(openssl rand -base64 32)" >> /opt/in-time/.env
```

This creates a random 32-byte key and stores it in the server's `.env` file. The passphrase is needed to decrypt backups, so it must be kept safe.

**Step 2 — Create the backups folder**

```bash
mkdir -p /opt/in-time/backups
```

**Step 3 — Create the backup script**

```bash
cat > /opt/in-time/backup.sh <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
cd /opt/in-time

set -a; . ./.env; set +a

STAMP=$(date +%Y%m%d_%H%M%S)
OUT=/opt/in-time/backups/in_time_${STAMP}.sql.gz.enc

docker compose -f docker-compose.prod.yml exec -T \
  -e MYSQL_PWD="$DB_PASSWORD" db \
  mysqldump -u in_time --single-transaction --no-tablespaces --routines --events in_time \
  | gzip \
  | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE \
  > "$OUT"

find /opt/in-time/backups -name 'in_time_*.enc' -mtime +14 -delete

echo "$(date) backup OK: $(stat -c%s "$OUT") bytes -> $OUT"
EOF
```

This writes the script content into the file in one go.

**Step 4 — Make the script executable**

```bash
chmod +x /opt/in-time/backup.sh
```

**Step 5 — Run the backup manually**

```bash
/opt/in-time/backup.sh
```

The first run printed a mysqldump error about missing `PROCESS` privilege for tablespaces (explained in section 5.2); the flag `--no-tablespaces` was added to the script and the backup then ran cleanly:

Output:

```
Tue Aug 18 08:46:47 UTC 2026 backup OK: 109392 bytes -> /opt/in-time/backups/in_time_20260818_084646.sql.gz.enc
```

The backup was created successfully (109,392 bytes ≈ 107 KB).

**Step 6 — Schedule the backup daily at 03:30**

```bash
(crontab -l 2>/dev/null; echo "30 3 * * * /opt/in-time/backup.sh >> /opt/in-time/backups/backup.log 2>&1") | crontab -
```

This adds an entry to the server's task scheduler (cron). The time `30 3 * * *` means: every day at 03:30 AM. The results of each run are appended to `backup.log`.

Verify the schedule was installed:

```bash
crontab -l
```

Output:

```
30 3 * * * /opt/in-time/backup.sh >> /opt/in-time/backups/backup.log 2>&1
```

---

## 5. Verification — proving the backup works

### 5.1 Local test (before deploying to production)

The full pipeline was tested locally first (on a scratch database in the local XAMPP MariaDB) before being deployed to production:

```bash
# Create test data
mysql -uroot -e "CREATE DATABASE dump_test; CREATE TABLE dump_test.t (id INT PRIMARY KEY, name VARCHAR(20)); INSERT INTO dump_test.t VALUES (1,'hello'),(2,'world');"

# Dump with the same flags used in production
# (--no-tablespaces was not needed locally — MariaDB does not require the PROCESS privilege)
mysqldump -uroot --single-transaction --routines --events dump_test > dump.sql

# Compress
php -r "file_put_contents('dump.sql.gz', gzencode(file_get_contents('dump.sql'), 9));"

# Encrypt (the exact production command)
openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE -in dump.sql.gz -out dump.sql.gz.enc

# Decrypt and compare — the round trip must be identical
openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in dump.sql.gz.enc -out dump-roundtrip.sql.gz
```

**Result:** The SHA-256 checksum of the original dump and the decrypted round trip were identical — the encryption/decryption cycle works correctly.

### 5.2 Production backup verification

The first manual run on the server printed an error:

```
mysqldump: Error: 'Access denied; you need (at least one of) the PROCESS privilege(s) for this operation' when trying to dump tablespaces
```

MySQL 8.0 tries to include tablespace definitions in the dump, which requires the `PROCESS` database privilege that the application user does not have. The fix was to add `--no-tablespaces` to the mysqldump command in the script — this skips tablespaces, which are not needed to restore a single database. After this fix the backup ran cleanly, and these checks were run on that backup:

```bash
# Check the file exists
ls -la /opt/in-time/backups/
```

```bash
# Decrypt and check the compressed stream is valid
cd /opt/in-time && set -a && . ./.env && set +a
openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in backups/in_time_20260818_084646.sql.gz.enc | gzip -t && echo "GZIP STREAM OK"
```

Output: `GZIP STREAM OK`

```bash
# Count how many tables are inside the backup
openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in backups/in_time_20260818_084646.sql.gz.enc | gzip -dc | grep -c "CREATE TABLE"
```

Output: `36` — all 36 tables of the production database are in the backup.

### 5.3 Restore test (restore drill)

To prove the backup can actually be restored, it was loaded into a temporary test database:

```bash
openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in backups/in_time_20260818_084646.sql.gz.enc | gzip -dc | docker compose -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" db sh -c 'mysql -uroot -e "CREATE DATABASE IF NOT EXISTS restore_test" && mysql -uroot restore_test'
```

```bash
docker compose -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" db mysql -uroot -e "SELECT COUNT(*) AS tables_restored FROM information_schema.tables WHERE table_schema='restore_test';"
```

Output: `tables_restored = 36` — all 36 tables restored successfully. The test database was then deleted:

```bash
docker compose -f docker-compose.prod.yml exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" db mysql -uroot -e "DROP DATABASE restore_test"
```

---

## 6. How to restore a backup (real recovery procedure)

If the production database is ever lost or damaged:

```bash
cd /opt/in-time && set -a && . ./.env && set +a

openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE \
  -in backups/in_time_<latest>.enc \
  | gzip -dc \
  | docker compose -f docker-compose.prod.yml exec -T \
    -e MYSQL_PWD="$DB_ROOT_PASSWORD" db sh -c 'mysql -uroot in_time'
```

The pipeline is exactly the backup in reverse: decrypt → decompress → load into MySQL.

---

## 7. Summary of the implementation

| Item | Value |
|------|-------|
| Backup location | `/opt/in-time/backups/` |
| Backup file format | `in_time_<timestamp>.sql.gz.enc` |
| Schedule | Daily at 03:30 (cron) |
| Retention | 14 days (older files deleted automatically) |
| Database | MySQL 8.0, database `in_time` (36 tables) |
| Compression | gzip |
| Encryption | AES-256-CBC with PBKDF2, passphrase `BACKUP_PASSPHRASE` |
| App downtime during backup | None (`--single-transaction` snapshot) |
| Verification | Restore drill passed — 36/36 tables restored |
| Log file | `/opt/in-time/backups/backup.log` |

---

## 8. Notes

- The backup file is encrypted, so even if someone gains access to the server's files, they cannot read the data without the passphrase.
- The passphrase `BACKUP_PASSPHRASE` is stored in the server's `.env` file (never committed to the code repository) and must be kept in a safe place — losing it means backups can never be decrypted.
- Suggested future improvement: copy backups to a second location (off the server) so that a server disk failure cannot destroy both the database and its backups.
