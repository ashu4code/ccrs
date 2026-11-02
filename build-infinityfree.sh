#!/usr/bin/env bash
# =============================================================================
# build-infinityfree.sh — assemble an upload-ready InfinityFree package.
#
# Produces: dist/infinityfree/htdocs/  (mirrors the server layout exactly)
#
#   ./build-infinityfree.sh            build + assemble
#   ./build-infinityfree.sh --skip-build   reuse existing public/build
#   ./build-infinityfree.sh --zip      also create dist/infinityfree/htdocs.zip
#
# Requires: php, composer, npm (build only). Safe to re-run; dist/ is recreated.
# Your local vendor/, node_modules/ and .env are never modified.
# =============================================================================
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST="$ROOT/dist/infinityfree"
APP="$DIST/htdocs"
ENV_SOURCE="$ROOT/.env.production"
HTACCESS_SOURCE="$ROOT/htdocs/.htaccess"

ZIP=0
SKIP_BUILD=0

for arg in "$@"; do
    case "$arg" in
        --zip) ZIP=1 ;;
        --skip-build) SKIP_BUILD=1 ;;
        -h|--help) sed -n '2,15p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 1 ;;
    esac
done

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m  ! %s\033[0m\n' "$1"; }
ok() { printf '\033[1;32m  + %s\033[0m\n' "$1"; }
die() { printf '\033[1;31m  x %s\033[0m\n' "$1" >&2; exit 1; }

# -----------------------------------------------------------------------------
# 1. Preflight
# -----------------------------------------------------------------------------
say "Preflight checks"

command -v php >/dev/null || die "php not found in PATH"
command -v composer >/dev/null || die "composer not found in PATH"
[ -f "$ENV_SOURCE" ] || die "Missing .env.production (production env file)"
[ -f "$HTACCESS_SOURCE" ] || die "Missing htdocs/.htaccess"
[ -f "$ROOT/artisan" ] || die "Run this script from the Laravel project root"

ENV_SIZE=$(wc -c < "$HTACCESS_SOURCE")
[ "$ENV_SIZE" -lt 10240 ] || die "htdocs/.htaccess is ${ENV_SIZE} bytes; InfinityFree caps .htaccess at 10 KB"

grep -q '^APP_KEY=base64:.\+' "$ENV_SOURCE" || die "APP_KEY missing from .env.production"
grep -q '^APP_ENV=production' "$ENV_SOURCE" || die "APP_ENV must be production in .env.production"
grep -q '^APP_DEBUG=false' "$ENV_SOURCE" || die "APP_DEBUG must be false in .env.production"
ok "Production env is present and sane"

PLACEHOLDERS=$(grep -nE '=[^#]*<[^>]+>' "$ENV_SOURCE" | sed 's/^/     /' || true)
if [ -n "$PLACEHOLDERS" ]; then
    warn "Placeholders still in .env.production (fix before upload):"
    printf '%s\n' "$PLACEHOLDERS"
fi

# -----------------------------------------------------------------------------
# 2. Frontend build
# -----------------------------------------------------------------------------
if [ "$SKIP_BUILD" -eq 1 ]; then
    say "Skipping frontend build (--skip-build)"
else
    say "Building frontend (Vite + Tailwind)"
    if [ ! -d "$ROOT/node_modules" ]; then
        ( cd "$ROOT" && npm ci )
    fi
    ( cd "$ROOT" && npm run build )
    ok "public/build regenerated"
fi

[ -f "$ROOT/public/build/manifest.json" ] || die "public/build/manifest.json missing — build failed"
ok "Vite manifest found"

# -----------------------------------------------------------------------------
# 3. Recreate staging tree
# -----------------------------------------------------------------------------
say "Creating staging tree"
case "$DIST" in
    */dist/infinityfree) rm -rf "$DIST" ;;
    *) die "Refusing to delete unexpected path: $DIST" ;;
esac
mkdir -p "$APP"

# Runtime directories only. tests/, node_modules/, docs/ and dev tooling stay home.
for dir in app bootstrap config database public resources routes storage; do
    cp -R "$ROOT/$dir" "$APP/$dir"
done
cp "$ROOT/artisan" "$ROOT/composer.json" "$ROOT/composer.lock" "$APP/"
cp "$ENV_SOURCE" "$APP/.env"
cp "$HTACCESS_SOURCE" "$APP/.htaccess"

# -----------------------------------------------------------------------------
# 4. Prune everything that must not ship
# -----------------------------------------------------------------------------
say "Pruning local-only files"

# Stale caches generated against the local (dev) vendor set would reference
# packages that --no-dev removes. They are rebuilt in step 5.
rm -f "$APP"/bootstrap/cache/*.php

# Compiled views, sessions, logs, testing disks and local uploads.
rm -rf "$APP/storage/framework/views"/*.php
rm -rf "$APP/storage/framework/sessions"/* \
       "$APP/storage/framework/cache/data"/* \
       "$APP/storage/framework/cache/locks"/* \
       "$APP/storage/framework/testing"
rm -rf "$APP/storage/logs"/*.log
rm -rf "$APP/storage/pail"
rm -rf "$APP/storage/app/private"/*
rm -rf "$APP/storage/app/public"/*
rm -f  "$APP/storage"/*.key

# Local uploads, hot-reload marker, symlinked public/storage and asset sources.
rm -rf "$APP/public/storage" "$APP/public/hot" "$APP/public/fonts-manifest.dev.json"
rm -rf "$APP/resources/css" "$APP/resources/js"

# Dev-only database artefacts.
rm -rf "$APP/database/factories"

# Anything the host rejects or that is simply not needed at runtime.
find "$APP" -name '.DS_Store' -delete
find "$APP" -name '*.log' -not -path '*/vendor/*' -delete
rm -f "$APP/.phpunit.result.cache"

ok "Local data, uploads and dev artefacts removed"

# Keep the empty writable directories Laravel expects.
mkdir -p "$APP/storage/app/private" \
         "$APP/storage/app/public" \
         "$APP/storage/framework/cache/data" \
         "$APP/storage/framework/cache/locks" \
         "$APP/storage/framework/sessions" \
         "$APP/storage/framework/views" \
         "$APP/storage/logs" \
         "$APP/bootstrap/cache"

# -----------------------------------------------------------------------------
# 5. Install production dependencies inside the staging tree
# -----------------------------------------------------------------------------
say "Installing production vendor/ (dev packages excluded)"
( cd "$APP" && composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader )

say "Regenerating package manifest"
( cd "$APP" && php artisan package:discover --ansi )
ok "bootstrap/cache/packages.php + services.php rebuilt for prod"

# Best effort: pre-compile Blade views to save CPU on the shared host.
if ( cd "$APP" && php artisan view:cache ); then
    ok "Blade views pre-compiled"
else
    warn "view:cache failed — views will compile on first request (harmless)"
fi

# Never ship a config cache: it bakes in one .env and cannot be cleared on the host.
rm -f "$APP/bootstrap/cache/config.php" "$APP"/bootstrap/cache/routes-*.php

# -----------------------------------------------------------------------------
# 6. Verify the package
# -----------------------------------------------------------------------------
say "Verifying package"

[ -f "$APP/vendor/autoload.php" ] || die "vendor/autoload.php missing"
[ -f "$APP/bootstrap/cache/packages.php" ] || die "packages.php missing"
[ -f "$APP/public/build/manifest.json" ] || die "Vite manifest missing"
[ -f "$APP/.env" ] || die ".env missing"
[ -f "$APP/.htaccess" ] || die "root .htaccess missing"
[ -d "$APP/public" ] || die "public/ missing"
ok "Required files present"

[ ! -f "$APP/.env.local" ] || die "A stray .env.local leaked into the package"

FILE_COUNT=$(find "$APP" -type f | wc -l | tr -d ' ')
SIZE=$(du -sh "$APP" | cut -f1)
ok "Files: $FILE_COUNT (host limit ~30,000)   Size: $SIZE (host limit 5 GB)"
if [ "$FILE_COUNT" -gt 28000 ]; then
    warn "Close to the 30,000 inode limit. Remove database/ or resources/ if uploads fail."
fi

BIG=$(find "$APP" -type f -size +900k \( -name '*.php' -o -name '*.phtml' \) 2>/dev/null || true)
if [ -n "$BIG" ]; then
    warn "PHP files over the host's 1 MB per-file limit (silently dropped on upload):"
    printf '%s\n' "$BIG" | sed 's/^/     /'
    warn "Re-run the composer step with --classmap-authoritative removed (plain --no-dev) to shrink autoload_classmap.php"
else
    ok "No PHP file exceeds the 1 MB per-file limit"
fi

if grep -qE 'max:20480' "$APP/app/Http/Controllers/GrievanceController.php" 2>/dev/null; then
    warn "Attachments still allow 20 MB, but InfinityFree deletes files over 10 MB. Lower max:20480 to max:8192."
fi

if [ "$ZIP" -eq 1 ]; then
    say "Creating zip archive"
    ZIP_PATH="$DIST/htdocs.zip"
    rm -f "$ZIP_PATH"
    # .NET's CreateFromDirectory includes dotfiles (.env, .htaccess); Compress-Archive does not.
    powershell.exe -NoProfile -Command \
        "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::CreateFromDirectory('$(cygpath -w "$APP")', '$(cygpath -w "$ZIP_PATH")', [System.IO.Compression.CompressionLevel]::Optimal, \$false)" \
        >/dev/null
    [ -f "$ZIP_PATH" ] || die "zip creation failed"
    ok "Created $ZIP_PATH ($(du -sh "$ZIP_PATH" | cut -f1))"
fi

# -----------------------------------------------------------------------------
# 7. Done
# -----------------------------------------------------------------------------
say "Package ready"
cat <<EOF

  Upload the CONTENTS of:
    $APP

  to the InfinityFree htdocs/ folder (FTP client, not the file manager — it
  cannot handle $FILE_COUNT files). The server tree becomes:

    htdocs/.htaccess   htdocs/.env   htdocs/app   htdocs/bootstrap
    htdocs/config      htdocs/database   htdocs/public   htdocs/resources
    htdocs/routes      htdocs/storage    htdocs/vendor

  After uploading (FTP, chmod 775 on these folders and everything inside):
    htdocs/storage
    htdocs/bootstrap/cache

  Then:
    1. Confirm htdocs/.env has the real APP_URL and DB_* values.
    2. Import the database dump through phpMyAdmin.
    3. Visit https://<your-site>/ and log in.
    4. Enable the HTTPS redirect block in htdocs/.htaccess once SSL is active.

EOF
