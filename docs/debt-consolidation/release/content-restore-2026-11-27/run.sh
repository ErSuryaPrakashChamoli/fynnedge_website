#!/usr/bin/env bash
# Production content restore approved by the Owner on 27 Nov 2026
# (docs/debt-consolidation/36–39). Run from the application root, as the
# production operator, one phase at a time and in this order:
#
#   pause-dc → preflight → backup → preview → apply → rulesets → verify → smoke
#
# Every phase appends "UTC time | operator | host | phase | result" to
# $EVIDENCE_DIR/actions.log and keeps its full output beside it.
# It never runs migrations, seeders or a whole-database restore.
set -euo pipefail
export LC_ALL=C

PHASE="${1:-}"
OPERATOR="${OPERATOR:?Set OPERATOR to your full name, e.g. OPERATOR=\"A. Kumar\" $0 ${PHASE:-preflight}}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHECKS=(php "$HERE/checks.php")
EVIDENCE_DIR="${EVIDENCE_DIR:-storage/app/private/incident-2026-11-26}"
REHEARSAL_PREVIEW="${REHEARSAL_PREVIEW:-$HERE/../../evidence/2026-11-27-restore-rehearsal-preview.txt}"
SITE_URL="${SITE_URL:-https://fynnedge.com}"
FROM="2026-11-26 13:05:00"
TO="2026-11-26 13:09:59"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

mkdir -p "$EVIDENCE_DIR" && chmod 700 "$EVIDENCE_DIR"
LOG="$EVIDENCE_DIR/actions.log"

log() { printf '%s | %s | %s | %s | %s\n' "$(date -u +%FT%TZ)" "$OPERATOR" "$(hostname)" "$PHASE" "$*" | tee -a "$LOG"; }
stop() { log "STOPPED: $*"; exit 1; }
checks() { # checks <output name> <checks.php subcommand>
    local out="$EVIDENCE_DIR/$1-$STAMP.txt"
    if "${CHECKS[@]}" "$2" | tee "$out"; then log "all checks passed ($out)"; else stop "a check failed, see $out"; fi
}

# The preview rows, whitespace-collapsed and tagged by section, so two previews
# compare line by line however Symfony padded the columns.
preview_rows() {
    awk '
        /^Fields to restore:/ { s = "R"; next }
        /^Left as they are:/ { s = "K"; next }
        /^Created in the window and still live:/ { s = "C"; next }
        /^Also changed in the window/ { s = "O"; next }
        /^\| (Record|Type) +\|/ { next }
        /^\|/ && s != "" { line = $0; gsub(/ +/, " ", line); print s " " line }
    ' "$1" | sort -u
}
record_of() { sed -E 's/^[A-Z] \| ([^|]*[^ ]) \|.*/\1/'; }
record_field_of() { sed -E 's/^[A-Z] \| ([^|]*[^ ]) \| ([^|]*[^ ]) \|.*/\1 | \2/'; }

compare_previews() { # compare_previews <rehearsal> <production> → prints report, returns 1 on a material difference
    local rehearsal production material=0
    rehearsal="$(mktemp)"; production="$(mktemp)"
    preview_rows "$1" > "$rehearsal"; preview_rows "$2" > "$production"
    local kept_fields kept_records
    kept_fields="$(grep '^K ' "$production" | record_field_of || true)"
    kept_records="$(grep '^K ' "$production" | record_of || true)"

    echo "Rehearsal: $(grep -c '^R ' "$rehearsal") fields to restore, $(grep -c '^C ' "$rehearsal") created to hide"
    echo "Production: $(grep -c '^R ' "$production") fields to restore, $(grep -c '^C ' "$production") created to hide, $(grep -c '^K ' "$production" || true) left as they are"

    while IFS= read -r row; do
        echo "MATERIAL: only in production: $row"; material=1
    done < <(comm -13 "$rehearsal" "$production" | grep '^[RCO] ' || true)

    while IFS= read -r row; do
        case "$row" in
            R*) key="$(record_field_of <<< "$row")"
                if grep -qxF "$key" <<< "$kept_fields"; then echo "EXPLAINED: staff changed it after the window, kept: $key"; else echo "MATERIAL: missing from production: $row"; material=1; fi ;;
            C*) key="$(record_of <<< "$row")"
                if grep -qxF "$key" <<< "$kept_records"; then echo "EXPLAINED: created record edited by staff, kept live: $key"; else echo "MATERIAL: missing from production: $row"; material=1; fi ;;
            O*) echo "MATERIAL: other-type count differs: $row"; material=1 ;;
        esac
    done < <(comm -23 "$rehearsal" "$production" | grep '^[RCO] ' || true)

    rm -f "$rehearsal" "$production"
    return "$material"
}

verify_backup() { # verify_backup <file.sql.gz>
    local file="$1" tables_dump tables_live
    [ -s "$file" ] || stop "backup $file is missing or empty"
    gzip -t "$file" || stop "backup $file is not a valid gzip file"
    zcat "$file" | tail -n 1 | grep '^-- Dump completed' > /dev/null || stop "backup $file has no 'Dump completed' trailer"
    tables_dump="$(zcat "$file" | grep -c '^CREATE TABLE ' || true)"
    tables_live="$("${CHECKS[@]}" table-count)"
    [ "$tables_dump" = "$tables_live" ] || stop "backup has $tables_dump tables, database has $tables_live"
    local populated
    populated="$(zcat "$file" | grep -o '^INSERT INTO `[a-z0-9_]*`' | sort -u)"
    for table in audit_logs loan_products loan_landing_pages pages articles faqs testimonials debt_applications debt_consents journey_sessions eligibility_results; do
        grep -qxF "INSERT INTO \`$table\`" <<< "$populated" || stop "backup has no rows for $table"
    done
    sha256sum "$file" > "$file.sha256"
    log "backup verified: $tables_dump tables, completion trailer present, key tables populated, sha256 $(cut -d' ' -f1 "$file.sha256")"

    if [ -n "${VERIFY_DB:-}" ]; then # optional test restore into an empty scratch database on the same server
        IFS=$'\t' read -r host port database user password < <("${CHECKS[@]}" credentials)
        [ "$VERIFY_DB" != "$database" ] || stop "VERIFY_DB must not be the production database"
        zcat "$file" | MYSQL_PWD="$password" mysql -h"$host" -P"$port" -u"$user" "$VERIFY_DB" || stop "test restore into $VERIFY_DB failed"
        diff <("${CHECKS[@]}" row-counts) <("${CHECKS[@]}" row-counts "$VERIFY_DB") > "$EVIDENCE_DIR/backup-restore-test-$STAMP.diff" \
            || stop "test restore row counts differ from live, see $EVIDENCE_DIR/backup-restore-test-$STAMP.diff"
        log "backup test-restored into $VERIFY_DB: content row counts identical to live"
    fi
}

case "$PHASE" in
    pause-dc)
        log "start: switch off 'Accept individuals' and 'Accept businesses'; DC pages stay on"
        checks pause-dc pause-dc
        ;;

    preflight)
        php artisan list --raw | grep '^content:restore-from-audit-log ' > /dev/null || stop "content:restore-from-audit-log is not deployed here"
        checks preflight fingerprint
        "${CHECKS[@]}" snapshot > "$EVIDENCE_DIR/snapshot-before.txt"
        database="$("${CHECKS[@]}" dbname)"
        read -r -p "Is '$database' (shown above) the production database to restore? Type its name to confirm: " answer
        [ "$answer" = "$database" ] || stop "database not confirmed"
        log "operator confirmed database '$database'; preservation snapshot saved"
        ;;

    backup)
        command -v mysqldump > /dev/null || stop "mysqldump is not installed here: take the backup on the database host, then run: $0 verify-backup FILE"
        IFS=$'\t' read -r host port database user password < <("${CHECKS[@]}" credentials)
        file="${BACKUP_DIR:-$EVIDENCE_DIR}/backup-$database-$STAMP.sql.gz"
        extra=()
        for option in --column-statistics=0 --no-tablespaces; do mysqldump --help 2>/dev/null | grep -- "${option%%=*}" > /dev/null && extra+=("$option"); done
        log "start: mysqldump --single-transaction of '$database' to $file"
        MYSQL_PWD="$password" mysqldump -h"$host" -P"$port" -u"$user" --single-transaction --quick --routines --triggers --hex-blob \
            --default-character-set=utf8mb4 "${extra[@]}" "$database" | gzip > "$file"
        chmod 600 "$file"
        verify_backup "$file"
        log "copy $file and $file.sha256 off this server before the next phase"
        ;;

    verify-backup)
        verify_backup "${2:?usage: $0 verify-backup FILE}"
        ;;

    preview)
        out="$EVIDENCE_DIR/preview-$STAMP.txt"
        log "start: preview for $FROM – $TO UTC"
        php artisan content:restore-from-audit-log --from="$FROM" --to="$TO" --no-ansi | tee "$out"
        [ -s "$REHEARSAL_PREVIEW" ] || stop "rehearsal preview $REHEARSAL_PREVIEW not found (set REHEARSAL_PREVIEW)"
        if compare_previews "$REHEARSAL_PREVIEW" "$out" | tee "$EVIDENCE_DIR/preview-compare-$STAMP.txt"; then
            echo "MATCH $out" > "$EVIDENCE_DIR/preview-verdict.txt"
            log "preview matches the rehearsal (differences, if any, are staff edits made after the window); see preview-compare-$STAMP.txt"
        else
            echo "DIFFERS $out" > "$EVIDENCE_DIR/preview-verdict.txt"
            stop "preview differs materially from the rehearsal: report to the Owner before applying. See preview-compare-$STAMP.txt"
        fi
        ;;

    apply)
        grep '^MATCH ' "$EVIDENCE_DIR/preview-verdict.txt" > /dev/null 2>&1 || stop "no preview matching the rehearsal: run the preview phase first"
        ls "$EVIDENCE_DIR"/backup-*.sql.gz.sha256 > /dev/null 2>&1 || [ -n "${BACKUP_VERIFIED_ELSEWHERE:-}" ] || stop "no verified backup in $EVIDENCE_DIR"
        out="$EVIDENCE_DIR/apply-$STAMP.txt"
        log "start: apply with --hide-created for $FROM – $TO UTC"
        php artisan content:restore-from-audit-log --from="$FROM" --to="$TO" --apply --hide-created --no-ansi | tee "$out"
        grep 'Restored .* record(s) and hid' "$out" > /dev/null || stop "the restore did not complete; nothing was changed unless the output says otherwise, see $out"
        log "applied: $(grep -o 'Restored .*' "$out")"
        ;;

    rulesets)
        log "start: archive demo eligibility rule sets #3 and #4"
        checks rulesets archive-rulesets
        ;;

    verify)
        [ -s "$EVIDENCE_DIR/snapshot-before.txt" ] || stop "no preservation snapshot: the preflight phase saves it"
        SNAPSHOT_FILE="$EVIDENCE_DIR/snapshot-before.txt" checks verify verify
        out="$EVIDENCE_DIR/preview-after-$STAMP.txt"
        php artisan content:restore-from-audit-log --from="$FROM" --to="$TO" --no-ansi > "$out"
        grep '^Fields to restore: 0 across 0 record(s)' "$out" > /dev/null || stop "the window still has fields to restore, see $out"
        log "second preview finds nothing left to restore ($out)"
        ;;

    smoke)
        out="$EVIDENCE_DIR/smoke-$STAMP.txt"; failed=0; bust="restore-check=$STAMP"
        probe() { # probe <path> <expected status> [text that must be absent] [text that must be present]
            local body status
            body="$(mktemp)"
            status="$(curl -s -o "$body" -w '%{http_code}' -A 'FynnEdge restore smoke test' "$SITE_URL$1?$bust")"
            local ok=1
            [ "$status" = "$2" ] || ok=0
            [ -z "${3:-}" ] || ! grep -F "$3" "$body" > /dev/null || ok=0
            [ -z "${4:-}" ] || grep -F "$4" "$body" > /dev/null || ok=0
            printf '%s | %s %s (expected %s)%s%s\n' "$([ $ok = 1 ] && echo PASS || echo FAIL)" "$1" "$status" "$2" "${3:+, without \"$3\"}" "${4:+, with \"$4\"}" | tee -a "$out"
            [ $ok = 1 ] || failed=1
            rm -f "$body"
        }
        probe / 200 'DEMO Lender'
        probe /partners 200 'DEMO Lender'
        probe /loans/personal-loans 200
        probe /loans/gold-loan 200 'Quick funding against your gold jewellery' 'use the gold you already have'
        for slug in 1-lakh-personal-loan 3-lakh-personal-loan 5-lakh-personal-loan 10-lakh-personal-loan personal-loan-for-salaried \
            personal-loan-for-self-employed personal-loan-for-women personal-loan-for-senior-citizens personal-loan-for-medical-expenses \
            personal-loan-for-travel personal-loan-for-home-renovation personal-loan-for-debt-consolidation; do
            probe "/loans/personal-loans/$slug" 200
        done
        probe /loans/personal-loan/personal-loan-in-delhi 404
        for path in /about /privacy-policy /terms /disclaimer /grievance /careers /credit-report-terms; do
            probe "$path" 200 'November 26, 2026'
        done
        for path in /debt-consolidation /personal-debt-consolidation /business-debt-consolidation /debt-consolidation/how-it-works \
            /debt-consolidation/calculator /debt-consolidation/faqs; do
            probe "$path" 200
        done
        probe /debt-consolidation/assessment 200 '' 'Online assessments are paused'
        echo "INFO | /loans/personal-loan answers $(curl -s -o /dev/null -w '%{http_code}' "$SITE_URL/loans/personal-loan?$bust") (404 when hidden; 200 if the Owner kept it)" | tee -a "$out"
        stale="$(curl -s "$SITE_URL/sitemap.xml?$bust" | grep -cE '2026-11-26T13:0[59]' || true)"
        echo "$([ "$stale" = 0 ] && echo PASS || echo FAIL) | sitemap entries still dated 13:05 or 13:09 on 26 Nov: $stale" | tee -a "$out"
        [ "$stale" = 0 ] || failed=1
        [ $failed = 0 ] && log "smoke tests passed against $SITE_URL ($out)" || stop "a smoke test failed, see $out"
        ;;

    *)
        echo "Usage: OPERATOR=\"Full Name\" $0 pause-dc|preflight|backup|verify-backup FILE|preview|apply|rulesets|verify|smoke" >&2
        exit 2
        ;;
esac
