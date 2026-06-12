#!/usr/bin/env bash
# DB 덤프. 사용법: ./scripts/db-backup.sh [백업디렉터리]
# cron 예시: 15 3 * * * /home/ubuntu/total-admin/scripts/db-backup.sh /home/ubuntu/db-backups >> /home/ubuntu/db-backups/backup.log 2>&1

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${1:-$HOME/db-backups/total-admin}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DUMP_FILE="$BACKUP_DIR/total-admin-$STAMP.sql.gz"

mkdir -p "$BACKUP_DIR"
cd "$PROJECT_DIR"

docker compose exec -T db sh -c \
    'exec mariadb-dump --single-transaction --quick --routines --events -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
    | gzip > "$DUMP_FILE"

gzip -t "$DUMP_FILE"

# 보관 기한 지난 덤프 삭제
find "$BACKUP_DIR" -name 'total-admin-*.sql.gz' -mtime +"$KEEP_DAYS" -delete

echo "[$(date '+%Y-%m-%d %H:%M:%S')] 백업 완료: $DUMP_FILE ($(du -h "$DUMP_FILE" | cut -f1))"
