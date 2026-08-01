#!/usr/bin/env bash

set -Eeuo pipefail
umask 027
IFS=$'\n\t'

SCRIPT_VERSION="2026-08-01.2"
CURRENT_STEP="initialisering"

APP_NAME="stars"
APP_USER="${APP_USER:-stars}"
APP_GROUP="${APP_GROUP:-stars}"
APP_BASE="${APP_BASE:-/var/www/stars}"
APP_ROOT="${APP_ROOT:-${APP_BASE}/current}"
RELEASES_ROOT="${RELEASES_ROOT:-${APP_BASE}/releases}"
COMPOSER_HOME="${COMPOSER_HOME:-${APP_BASE}/.composer}"

PACKAGE_REPOSITORY="${PACKAGE_REPOSITORY:-https://github.com/skifter/starsrecindled.git}"
PACKAGE_VERSION="${PACKAGE_VERSION:-dev-main}"
SYMFONY_VERSION="${SYMFONY_VERSION:-7.4.*}"

DOMAIN="${DOMAIN:-}"
ENABLE_TLS="${ENABLE_TLS:-0}"
LE_EMAIL="${LE_EMAIL:-}"
MAILER_DSN="${MAILER_DSN:-null://null}"
STARS_MAILER_FROM="${STARS_MAILER_FROM:-js@bellcom.dk}"

DB_NAME="${DB_NAME:-stars}"
DB_USER="${DB_USER:-stars}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

SECRET_ROOT="${SECRET_ROOT:-/etc/stars}"
DATABASE_SECRET_FILE="${DATABASE_SECRET_FILE:-${SECRET_ROOT}/database.env}"
APP_SECRET_FILE="${APP_SECRET_FILE:-${SECRET_ROOT}/app-secret}"

BACKUP_BASE="${BACKUP_BASE:-/var/backups/stars}"
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
BACKUP_DIR="${BACKUP_BASE}/${TIMESTAMP}"
RELEASE_ROOT="${RELEASES_ROOT}/${TIMESTAMP}"
LOG_FILE="${BACKUP_DIR}/install.log"

NGINX_SITE="/etc/nginx/sites-available/stars"
NGINX_LINK="/etc/nginx/sites-enabled/stars"
NGINX_DEFAULT_LINK="/etc/nginx/sites-enabled/default"
WORKER_UNIT="/etc/systemd/system/stars-messenger-worker.service"
RECONCILE_UNIT="/etc/systemd/system/stars-queue-reconcile.service"
RECONCILE_TIMER="/etc/systemd/system/stars-queue-reconcile.timer"

PHP_VERSION=""
PHP_BIN=""
FPM_BIN=""
FPM_SERVICE=""
FPM_SOCKET=""
FPM_POOL=""

PREVIOUS_CURRENT_KIND="none"
PREVIOUS_CURRENT_TARGET=""
DB_EXISTED="0"
SWITCH_COMPLETED="0"

log() {
    printf '[%s] %s\n' "$(date '+%H:%M:%S')" "$*"
}

warn() {
    printf 'ADVARSEL: %s\n' "$*" >&2
}

die() {
    printf 'FEJL: %s\n' "$*" >&2
    exit 1
}

on_error() {
    local status="$?"
    trap - ERR
    printf '\nFEJL i trin: %s\n' "${CURRENT_STEP}" >&2
    printf 'Exitkode: %s\n' "${status}" >&2
    printf 'Backup og log: %s\n' "${BACKUP_DIR}" >&2
    printf 'Ny release: %s\n' "${RELEASE_ROOT}" >&2
    if [[ "${SWITCH_COMPLETED}" == "1" ]]; then
        printf 'Symlinket current er allerede skiftet. Brug rollback-scriptet:\n' >&2
        printf '  %s/rollback.sh\n' "${BACKUP_DIR}" >&2
    fi
    exit "${status}"
}
trap on_error ERR

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Kommandoen mangler: $1"
}

validate_identifier() {
    local value="$1"
    local label="$2"
    [[ "${value}" =~ ^[A-Za-z0-9_]+$ ]] ||
        die "${label} må kun indeholde bogstaver, tal og underscore: ${value}"
}

backup_file() {
    local source="$1"
    local relative="${source#/}"

    if [[ -e "${source}" || -L "${source}" ]]; then
        install -d -m 0700 "${BACKUP_DIR}/files/$(dirname "${relative}")"
        cp -a "${source}" "${BACKUP_DIR}/files/${relative}"
        printf '%s\n' "${source}" >> "${BACKUP_DIR}/existing-files.list"
    else
        printf '%s\n' "${source}" >> "${BACKUP_DIR}/missing-files.list"
    fi
}

run_as_app() {
    (
        cd "${APP_BASE}"
        runuser -u "${APP_USER}" -- \
            env -i \
                HOME="${APP_BASE}" \
                USER="${APP_USER}" \
                LOGNAME="${APP_USER}" \
                SHELL="/bin/bash" \
                COMPOSER_HOME="${COMPOSER_HOME}" \
                COMPOSER_NO_INTERACTION=1 \
                COMPOSER_MEMORY_LIMIT=-1 \
                PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin" \
                "$@"
    )
}

run_console() {
    run_as_app \
        env APP_ENV=prod APP_DEBUG=0 \
        "${PHP_BIN}" "${RELEASE_ROOT}/bin/console" "$@"
}

write_dotenv_file() {
    local destination="$1"
    local database_url="$2"
    local frontend_url="$3"
    local temporary

    temporary="$(mktemp)"

    env \
        OUT_FILE="${temporary}" \
        APP_SECRET_VALUE="${APP_SECRET}" \
        DATABASE_URL_VALUE="${database_url}" \
        MESSENGER_DSN_VALUE="doctrine://default?queue_name=async" \
        MAILER_DSN_VALUE="${MAILER_DSN}" \
        MAILER_FROM_VALUE="${STARS_MAILER_FROM}" \
        FRONTEND_URL_VALUE="${frontend_url}" \
        "${PHP_BIN}" <<'PHP'
<?php

declare(strict_types=1);

function dotenvQuote(string $value): string
{
    return '"'.strtr($value, [
        "\\" => "\\\\",
        '"' => '\\"',
        '$' => '\\$',
        "\r" => '\\r',
        "\n" => '\\n',
    ]).'"';
}

$values = [
    'APP_ENV' => 'prod',
    'APP_DEBUG' => '0',
    'APP_SECRET' => (string) getenv('APP_SECRET_VALUE'),
    'DATABASE_URL' => (string) getenv('DATABASE_URL_VALUE'),
    'MESSENGER_TRANSPORT_DSN' => (string) getenv('MESSENGER_DSN_VALUE'),
    'MAILER_DSN' => (string) getenv('MAILER_DSN_VALUE'),
    'STARS_MAILER_FROM' => (string) getenv('MAILER_FROM_VALUE'),
    'STARS_FRONTEND_BASE_URL' => (string) getenv('FRONTEND_URL_VALUE'),
];

$output = '';
foreach ($values as $key => $value) {
    $output .= $key.'='.dotenvQuote($value).PHP_EOL;
}

$result = file_put_contents((string) getenv('OUT_FILE'), $output);
if ($result === false) {
    fwrite(STDERR, "Kunne ikke skrive .env.local\n");
    exit(1);
}
PHP

    install \
        -o "${APP_USER}" \
        -g "${APP_GROUP}" \
        -m 0640 \
        "${temporary}" \
        "${destination}"

    rm -f "${temporary}"
}

write_rollback_script() {
    local rollback="${BACKUP_DIR}/rollback.sh"

    cat > "${rollback}" <<BASH_ROLLBACK
#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT=$(printf '%q' "${APP_ROOT}")
NEW_RELEASE=$(printf '%q' "${RELEASE_ROOT}")
BACKUP_DIR=$(printf '%q' "${BACKUP_DIR}")
PREVIOUS_CURRENT_KIND=$(printf '%q' "${PREVIOUS_CURRENT_KIND}")
PREVIOUS_CURRENT_TARGET=$(printf '%q' "${PREVIOUS_CURRENT_TARGET}")
FPM_SERVICE=$(printf '%q' "${FPM_SERVICE}")
DB_NAME=$(printf '%q' "${DB_NAME}")
DB_USER=$(printf '%q' "${DB_USER}")
DB_EXISTED=$(printf '%q' "${DB_EXISTED}")

systemctl stop stars-messenger-worker.service 2>/dev/null || true
systemctl stop stars-queue-reconcile.timer 2>/dev/null || true

if [[ -L "\${APP_ROOT}" ]]; then
    rm -f "\${APP_ROOT}"
elif [[ -e "\${APP_ROOT}" ]]; then
    mv "\${APP_ROOT}" "\${APP_ROOT}.failed.\$(date '+%Y%m%d-%H%M%S')"
fi

case "\${PREVIOUS_CURRENT_KIND}" in
    symlink)
        test -d "\${PREVIOUS_CURRENT_TARGET}"
        ln -s "\${PREVIOUS_CURRENT_TARGET}" "\${APP_ROOT}"
        ;;
    directory)
        test -d "\${BACKUP_DIR}/current.before"
        mv "\${BACKUP_DIR}/current.before" "\${APP_ROOT}"
        ;;
    none)
        ;;
    *)
        printf 'Ukendt previous current-type: %s\n' "\${PREVIOUS_CURRENT_KIND}" >&2
        exit 1
        ;;
esac

if [[ -f "\${BACKUP_DIR}/existing-files.list" ]]; then
    while IFS= read -r target; do
        source_file="\${BACKUP_DIR}/files/\${target#/}"
        install -d -m 0755 "\$(dirname "\${target}")"
        rm -rf "\${target}"
        cp -a "\${source_file}" "\${target}"
    done < "\${BACKUP_DIR}/existing-files.list"
fi

if [[ -f "\${BACKUP_DIR}/missing-files.list" ]]; then
    while IFS= read -r target; do
        rm -rf "\${target}"
    done < "\${BACKUP_DIR}/missing-files.list"
fi

systemctl daemon-reload
systemctl restart "\${FPM_SERVICE}"
nginx -t
systemctl reload nginx

if [[ "\${PREVIOUS_CURRENT_KIND}" != "none" ]]; then
    systemctl enable --now stars-messenger-worker.service 2>/dev/null || true
    systemctl enable --now stars-queue-reconcile.timer 2>/dev/null || true
else
    systemctl disable stars-messenger-worker.service 2>/dev/null || true
    systemctl disable stars-queue-reconcile.timer 2>/dev/null || true
fi

printf 'Fil- og release-rollback udført.\n'
printf 'Den nye release er ikke slettet: %s\n' "\${NEW_RELEASE}"

if [[ -f "\${BACKUP_DIR}/stars.sql.gz" ]]; then
    printf '\nDatabasebackup findes. Gendan den kun, hvis migrationerne også skal rulles tilbage:\n'
    printf '  mariadb --execute="DROP DATABASE IF EXISTS \\\`%s\\\`; CREATE DATABASE \\\`%s\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"\n' "\${DB_NAME}" "\${DB_NAME}"
    printf '  gzip -dc %q | mariadb %q\n' "\${BACKUP_DIR}/stars.sql.gz" "\${DB_NAME}"
elif [[ "\${DB_EXISTED}" == "0" ]]; then
    printf '\nDatabasen blev oprettet af installationen. Fjern den kun, hvis alle nye data må slettes:\n'
    printf '  mariadb --execute="DROP DATABASE IF EXISTS \\\`%s\\\`; DROP USER IF EXISTS '"'"'%s'"'"'@'"'"'127.0.0.1'"'"'; DROP USER IF EXISTS '"'"'%s'"'"'@'"'"'localhost'"'"';"\n' "\${DB_NAME}" "\${DB_USER}" "\${DB_USER}"
fi
BASH_ROLLBACK

    chmod 0700 "${rollback}"
}

[[ "${EUID}" -eq 0 ]] || die "Scriptet skal køres som root."
[[ -n "${DOMAIN}" ]] || die "DOMAIN mangler. Eksempel: DOMAIN=stars.example.dk"
[[ "${ENABLE_TLS}" == "0" || "${ENABLE_TLS}" == "1" ]] ||
    die "ENABLE_TLS skal være 0 eller 1."

if [[ "${ENABLE_TLS}" == "1" ]]; then
    [[ -n "${LE_EMAIL}" ]] || die "LE_EMAIL kræves, når ENABLE_TLS=1."
    [[ "${DOMAIN}" != *":"* ]] || die "DOMAIN må ikke indeholde portnummer."
    [[ ! "${DOMAIN}" =~ ^[0-9.]+$ ]] || die "Let's Encrypt kræver et domænenavn, ikke en IPv4-adresse."
fi

validate_identifier "${DB_NAME}" "DB_NAME"
validate_identifier "${DB_USER}" "DB_USER"
[[ "${DB_PORT}" =~ ^[0-9]+$ ]] || die "DB_PORT skal være numerisk."

if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    source /etc/os-release
    if [[ "${ID:-}" != "debian" || "${VERSION_ID:-}" != "13" ]]; then
        warn "Scriptet er skrevet til Debian 13; fundet: ${PRETTY_NAME:-ukendt system}."
    fi
fi

install -d -m 0700 "${BACKUP_DIR}"
touch "${LOG_FILE}"
chmod 0600 "${LOG_FILE}"
exec > >(tee -a "${LOG_FILE}") 2>&1

log "Stars Recindled-installation ${SCRIPT_VERSION}"
log "Backup: ${BACKUP_DIR}"
log "Release: ${RELEASE_ROOT}"

CURRENT_STEP="installation af Debian-pakker"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install --yes \
    ca-certificates \
    certbot \
    composer \
    curl \
    git \
    mariadb-client \
    mariadb-server \
    nginx \
    nodejs \
    npm \
    openssl \
    php-apcu \
    php-cli \
    php-curl \
    php-fpm \
    php-intl \
    php-mbstring \
    php-mysql \
    php-opcache \
    php-xml \
    php-zip \
    python3-certbot-nginx \
    rsync \
    unzip

require_command composer
require_command curl
require_command git
require_command mariadb
require_command mariadb-dump
require_command nginx
require_command node
require_command npm
require_command openssl
require_command php
require_command runuser

PHP_BIN="$(command -v php)"
PHP_VERSION="$(${PHP_BIN} -r 'printf("%d.%d", PHP_MAJOR_VERSION, PHP_MINOR_VERSION);')"
FPM_BIN="$(command -v "php-fpm${PHP_VERSION}" || true)"
FPM_SERVICE="php${PHP_VERSION}-fpm"
FPM_SOCKET="/run/php/php${PHP_VERSION}-fpm-stars.sock"
FPM_POOL="/etc/php/${PHP_VERSION}/fpm/pool.d/stars.conf"

[[ -n "${FPM_BIN}" ]] || die "Kunne ikke finde php-fpm${PHP_VERSION}."

log "PHP-version: ${PHP_VERSION}"
log "Composer: $(timeout 30s env -i HOME=/root USER=root LOGNAME=root SHELL=/bin/bash COMPOSER_HOME=/root/.composer COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1 PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin composer --version --no-ansi)"
log "Node: $(node --version)"

CURRENT_STEP="oprettelse af systembruger og mapper"
if ! getent group "${APP_GROUP}" >/dev/null; then
    groupadd --system "${APP_GROUP}"
fi

if ! id "${APP_USER}" >/dev/null 2>&1; then
    useradd \
        --system \
        --gid "${APP_GROUP}" \
        --home-dir "${APP_BASE}" \
        --shell /usr/sbin/nologin \
        "${APP_USER}"
else
    usermod --append --groups "${APP_GROUP}" "${APP_USER}"
fi

install -d -o "${APP_USER}" -g "${APP_GROUP}" -m 0755 "${APP_BASE}"
install -d -o "${APP_USER}" -g "${APP_GROUP}" -m 0755 "${RELEASES_ROOT}"
install -d -o "${APP_USER}" -g "${APP_GROUP}" -m 0750 "${COMPOSER_HOME}"
install -d -o root -g root -m 0700 "${SECRET_ROOT}"
install -d -o root -g root -m 0700 "${BACKUP_BASE}"

CURRENT_STEP="backup af eksisterende installation"
: > "${BACKUP_DIR}/existing-files.list"
: > "${BACKUP_DIR}/missing-files.list"
chmod 0600 "${BACKUP_DIR}/existing-files.list" "${BACKUP_DIR}/missing-files.list"

backup_file "${NGINX_SITE}"
backup_file "${NGINX_LINK}"
backup_file "${NGINX_DEFAULT_LINK}"
backup_file "${FPM_POOL}"
backup_file "${WORKER_UNIT}"
backup_file "${RECONCILE_UNIT}"
backup_file "${RECONCILE_TIMER}"
backup_file "${DATABASE_SECRET_FILE}"
backup_file "${APP_SECRET_FILE}"

if [[ -L "${APP_ROOT}" ]]; then
    PREVIOUS_CURRENT_KIND="symlink"
    PREVIOUS_CURRENT_TARGET="$(readlink -f "${APP_ROOT}")"
elif [[ -d "${APP_ROOT}" ]]; then
    PREVIOUS_CURRENT_KIND="directory"
elif [[ -e "${APP_ROOT}" ]]; then
    die "${APP_ROOT} findes, men er hverken mappe eller symlink."
fi

systemctl enable --now mariadb
systemctl enable --now nginx
systemctl enable --now "${FPM_SERVICE}"

DB_EXISTED="$(mariadb --batch --skip-column-names --execute="
    SELECT COUNT(*)
    FROM information_schema.SCHEMATA
    WHERE SCHEMA_NAME = '${DB_NAME}';
")"

if [[ "${DB_EXISTED}" != "0" ]]; then
    log "Tager databasebackup af ${DB_NAME}."
    mariadb-dump \
        --single-transaction \
        --routines \
        --events \
        --triggers \
        "${DB_NAME}" | gzip -9 > "${BACKUP_DIR}/stars.sql.gz"
    chmod 0600 "${BACKUP_DIR}/stars.sql.gz"
fi

systemctl stop stars-messenger-worker.service 2>/dev/null || true
systemctl stop stars-queue-reconcile.timer 2>/dev/null || true

CURRENT_STEP="oprettelse af database og databasebruger"
DB_PASSWORD=""
if [[ -f "${DATABASE_SECRET_FILE}" ]]; then
    # Filen er oprettet af dette eller et tidligere installationsscript.
    # shellcheck disable=SC1090
    source "${DATABASE_SECRET_FILE}"
fi

if [[ -z "${DB_PASSWORD:-}" ]]; then
    DB_PASSWORD="$(openssl rand -hex 24)"
fi

APP_SECRET=""
if [[ -s "${APP_SECRET_FILE}" ]]; then
    APP_SECRET="$(tr -d '\r\n' < "${APP_SECRET_FILE}")"
fi
if [[ -z "${APP_SECRET}" ]]; then
    APP_SECRET="$(openssl rand -hex 32)"
fi

cat > "${DATABASE_SECRET_FILE}" <<EOF_DB_SECRET
DB_NAME="${DB_NAME}"
DB_USER="${DB_USER}"
DB_HOST="${DB_HOST}"
DB_PORT="${DB_PORT}"
DB_PASSWORD="${DB_PASSWORD}"
EOF_DB_SECRET
chmod 0600 "${DATABASE_SECRET_FILE}"
chown root:root "${DATABASE_SECRET_FILE}"

printf '%s\n' "${APP_SECRET}" > "${APP_SECRET_FILE}"
chmod 0600 "${APP_SECRET_FILE}"
chown root:root "${APP_SECRET_FILE}"

mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1'
    IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'127.0.0.1'
    IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.*
    TO '${DB_USER}'@'127.0.0.1';

CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost'
    IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost'
    IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.*
    TO '${DB_USER}'@'localhost';
SQL

DB_CLIENT_CONFIG="$(mktemp)"
cat > "${DB_CLIENT_CONFIG}" <<EOF_DB_CLIENT
[client]
protocol=tcp
host=${DB_HOST}
port=${DB_PORT}
user=${DB_USER}
password=${DB_PASSWORD}
database=${DB_NAME}
EOF_DB_CLIENT
chmod 0600 "${DB_CLIENT_CONFIG}"

mariadb \
    --defaults-extra-file="${DB_CLIENT_CONFIG}" \
    --batch \
    --skip-column-names \
    --execute='SELECT DATABASE(), CURRENT_USER(), VERSION();'
rm -f "${DB_CLIENT_CONFIG}"

CURRENT_STEP="oprettelse af Symfony-værtsapplikation"
install -d -o "${APP_USER}" -g "${APP_GROUP}" -m 0755 "${RELEASE_ROOT}"
rmdir "${RELEASE_ROOT}"

run_as_app composer create-project \
    "symfony/skeleton:${SYMFONY_VERSION}" \
    "${RELEASE_ROOT}" \
    --prefer-dist \
    --no-interaction \
    --no-progress

run_as_app composer \
    --working-dir="${RELEASE_ROOT}" \
    config repositories.starsrecindled vcs "${PACKAGE_REPOSITORY}"

run_as_app composer \
    --working-dir="${RELEASE_ROOT}" \
    require \
    "skifter/starsrecindled:${PACKAGE_VERSION}" \
    symfony/orm-pack \
    doctrine/doctrine-migrations-bundle \
    symfony/mailer \
    symfony/monolog-bundle \
    --with-all-dependencies \
    --no-interaction \
    --no-progress

PACKAGE_ROOT="${RELEASE_ROOT}/vendor/skifter/starsrecindled"
test -f "${PACKAGE_ROOT}/composer.json"
test -f "${PACKAGE_ROOT}/examples/symfony/config/packages/stars_turn.yaml"
test -f "${PACKAGE_ROOT}/examples/symfony/config/packages/messenger.yaml"
test -f "${PACKAGE_ROOT}/examples/symfony/config/routes/stars_turn.yaml"

CURRENT_STEP="konfiguration af Symfony-bundle"
env BUNDLES_FILE="${RELEASE_ROOT}/config/bundles.php" "${PHP_BIN}" <<'PHP'
<?php

declare(strict_types=1);

$file = (string) getenv('BUNDLES_FILE');
$content = file_get_contents($file);
if ($content === false) {
    fwrite(STDERR, "Kunne ikke læse config/bundles.php\n");
    exit(1);
}

$class = 'Bellcom\\StarsTurnBundle\\StarsTurnBundle::class';
if (!str_contains($content, $class)) {
    $line = "    Bellcom\\StarsTurnBundle\\StarsTurnBundle::class => ['all' => true],\n";
    $updated = preg_replace('/\];\s*$/', $line."];\n", $content, 1, $count);
    if ($updated === null || $count !== 1) {
        fwrite(STDERR, "Kunne ikke patche config/bundles.php\n");
        exit(1);
    }
    file_put_contents($file, $updated);
}
PHP

install \
    -o "${APP_USER}" -g "${APP_GROUP}" -m 0644 \
    "${PACKAGE_ROOT}/examples/symfony/config/packages/stars_turn.yaml" \
    "${RELEASE_ROOT}/config/packages/stars_turn.yaml"

install \
    -o "${APP_USER}" -g "${APP_GROUP}" -m 0644 \
    "${PACKAGE_ROOT}/examples/symfony/config/packages/doctrine_migrations.yaml" \
    "${RELEASE_ROOT}/config/packages/doctrine_migrations.yaml"

install \
    -o "${APP_USER}" -g "${APP_GROUP}" -m 0644 \
    "${PACKAGE_ROOT}/examples/symfony/config/packages/messenger.yaml" \
    "${RELEASE_ROOT}/config/packages/messenger.yaml"

install -d \
    -o "${APP_USER}" -g "${APP_GROUP}" -m 0755 \
    "${RELEASE_ROOT}/config/routes"

install \
    -o "${APP_USER}" -g "${APP_GROUP}" -m 0644 \
    "${PACKAGE_ROOT}/examples/symfony/config/routes/stars_turn.yaml" \
    "${RELEASE_ROOT}/config/routes/stars_turn.yaml"

cat > "${RELEASE_ROOT}/config/packages/stars_doctrine.yaml" <<'YAML'
doctrine:
  orm:
    mappings:
      StarsTurnBundle:
        is_bundle: true
        type: attribute
        dir: src/Entity
        prefix: Bellcom\StarsTurnBundle\Entity
        alias: StarsTurn
YAML
chown "${APP_USER}:${APP_GROUP}" "${RELEASE_ROOT}/config/packages/stars_doctrine.yaml"
chmod 0644 "${RELEASE_ROOT}/config/packages/stars_doctrine.yaml"

DATABASE_URL="mysql://${DB_USER}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_NAME}?charset=utf8mb4"
if [[ "${ENABLE_TLS}" == "1" ]]; then
    FRONTEND_URL="https://${DOMAIN}"
else
    FRONTEND_URL="http://${DOMAIN}"
fi

write_dotenv_file \
    "${RELEASE_ROOT}/.env.local" \
    "${DATABASE_URL}" \
    "${FRONTEND_URL}"

install -d \
    -o "${APP_USER}" -g "${APP_GROUP}" -m 0770 \
    "${RELEASE_ROOT}/var" \
    "${RELEASE_ROOT}/var/cache" \
    "${RELEASE_ROOT}/var/log"

chown -R "${APP_USER}:${APP_GROUP}" "${RELEASE_ROOT}"

run_as_app composer \
    --working-dir="${RELEASE_ROOT}" \
    install \
    --no-dev \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-interaction \
    --no-progress

CURRENT_STEP="bygning af Svelte-frontend"
rm -rf "${RELEASE_ROOT}/frontend"
cp -a "${PACKAGE_ROOT}/frontend" "${RELEASE_ROOT}/frontend"
chown -R "${APP_USER}:${APP_GROUP}" "${RELEASE_ROOT}/frontend"

run_as_app npm \
    --prefix "${RELEASE_ROOT}/frontend" \
    install \
    --no-audit \
    --no-fund

run_as_app npm \
    --prefix "${RELEASE_ROOT}/frontend" \
    run check

run_as_app npm \
    --prefix "${RELEASE_ROOT}/frontend" \
    run build

test -f "${RELEASE_ROOT}/frontend/build/index.html"
find "${RELEASE_ROOT}/frontend/build" -type d -exec chmod 0755 {} +
find "${RELEASE_ROOT}/frontend/build" -type f -exec chmod 0644 {} +
# nginx (www-data) skal kunne traversere release- og frontend-mappen.
chmod o+x "${RELEASE_ROOT}" "${RELEASE_ROOT}/frontend"
rm -rf "${RELEASE_ROOT}/frontend/node_modules"

CURRENT_STEP="konfiguration og validering af PHP-FPM"
cat > "${FPM_POOL}" <<EOF_FPM
[stars]
user = ${APP_USER}
group = ${APP_GROUP}

listen = ${FPM_SOCKET}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 4
pm.max_requests = 500

chdir = /
catch_workers_output = yes
clear_env = yes

php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 8M
php_admin_value[post_max_size] = 8M
EOF_FPM
chmod 0644 "${FPM_POOL}"

"${FPM_BIN}" -t
systemctl restart "${FPM_SERVICE}"
test -S "${FPM_SOCKET}"

CURRENT_STEP="konfiguration og validering af nginx"
cat > "${NGINX_SITE}" <<EOF_NGINX
server {
    listen 80;
    listen [::]:80;

    server_name ${DOMAIN};

    root ${APP_ROOT}/frontend/build;
    index index.html;

    client_max_body_size 8m;

    location ^~ /stars/ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME ${APP_ROOT}/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT ${APP_ROOT}/public;
        fastcgi_param HTTP_PROXY "";
        fastcgi_pass unix:${FPM_SOCKET};
    }

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location ~ \.php(?:\$|/) {
        return 404;
    }

}
EOF_NGINX
chmod 0644 "${NGINX_SITE}"

CURRENT_STEP="Symfony-validering og databasemigration"
"${PHP_BIN}" -l "${RELEASE_ROOT}/config/bundles.php"
run_console lint:yaml "${RELEASE_ROOT}/config"
run_console lint:container
run_console debug:container 'Bellcom\StarsTurnBundle\Domain\TurnEngineInterface'
run_console debug:router | grep -F 'stars_turn_'
run_console dbal:run-sql 'SELECT 1' --no-interaction
run_console doctrine:migrations:migrate --no-interaction --allow-no-migration
run_console messenger:setup-transports --no-interaction

# På en helt ny installation kan bundle-migrationen efterlade ufarlige
# kolonne-/indeksforskelle i forhold til ORM-mappingen. Synkronisér kun en
# første installation og stop ved potentielt destruktiv SQL. Opgraderinger
# skal fortsat leveres som rigtige Doctrine-migrationer.
if ! run_console doctrine:schema:validate --no-interaction; then
    if [[ "${PREVIOUS_CURRENT_KIND}" != "none" ]]; then
        die "Databaseskemaet er ikke i sync. Opret en Doctrine-migration før opgradering."
    fi

    SCHEMA_DIFF_FILE="${BACKUP_DIR}/schema-update.sql"
    run_console doctrine:schema:update --dump-sql --no-interaction | tee "${SCHEMA_DIFF_FILE}"

    if grep -Eiq '(^|[[:space:];])(DROP|TRUNCATE)[[:space:]]' "${SCHEMA_DIFF_FILE}"; then
        die "Schemaændringen indeholder DROP eller TRUNCATE. Se ${SCHEMA_DIFF_FILE}."
    fi

    warn "Synkroniserer databaseskemaet for den nye installation. SQL er gemt i ${SCHEMA_DIFF_FILE}."
    run_console doctrine:schema:update --force --no-interaction
    run_console doctrine:schema:validate --no-interaction
fi

run_console cache:clear
run_console cache:warmup

write_rollback_script

CURRENT_STEP="atomisk aktivering af release"
if [[ "${PREVIOUS_CURRENT_KIND}" == "directory" ]]; then
    mv "${APP_ROOT}" "${BACKUP_DIR}/current.before"
fi

TEMP_LINK="${APP_BASE}/.current-${TIMESTAMP}"
ln -s "${RELEASE_ROOT}" "${TEMP_LINK}"
mv -Tf "${TEMP_LINK}" "${APP_ROOT}"
SWITCH_COMPLETED="1"

CURRENT_STEP="installation af systemd-services"
cat > "${WORKER_UNIT}" <<EOF_WORKER
[Unit]
Description=Stars Symfony Messenger worker
After=network-online.target mariadb.service
Wants=network-online.target

[Service]
Type=simple
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${APP_ROOT}
Environment=APP_ENV=prod
Environment=APP_DEBUG=0
ExecStart=${PHP_BIN} bin/console messenger:consume async --time-limit=3600 --memory-limit=256M --no-interaction
ExecReload=/bin/kill -USR2 \$MAINPID
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=60
PrivateTmp=true
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
EOF_WORKER

cat > "${RECONCILE_UNIT}" <<EOF_RECONCILE
[Unit]
Description=Reconcile Stars queued turns and notifications
After=network-online.target mariadb.service
Wants=network-online.target

[Service]
Type=oneshot
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${APP_ROOT}
Environment=APP_ENV=prod
Environment=APP_DEBUG=0
ExecStart=${PHP_BIN} bin/console stars:queue:reconcile --no-interaction
PrivateTmp=true
NoNewPrivileges=true
EOF_RECONCILE

cat > "${RECONCILE_TIMER}" <<'EOF_TIMER'
[Unit]
Description=Run Stars queue reconciliation every minute

[Timer]
OnBootSec=2min
OnUnitActiveSec=1min
AccuracySec=10s
Persistent=true

[Install]
WantedBy=timers.target
EOF_TIMER

chmod 0644 "${WORKER_UNIT}" "${RECONCILE_UNIT}" "${RECONCILE_TIMER}"
systemd-analyze verify \
    "${WORKER_UNIT}" \
    "${RECONCILE_UNIT}" \
    "${RECONCILE_TIMER}"

systemctl daemon-reload
systemctl enable --now stars-messenger-worker.service
systemctl enable --now stars-queue-reconcile.timer

CURRENT_STEP="aktivering af nginx-site"
ln -sfn "${NGINX_SITE}" "${NGINX_LINK}"
rm -f "${NGINX_DEFAULT_LINK}"
nginx -t
systemctl reload nginx

CURRENT_STEP="HTTP-funktionstest"
curl \
    --fail-with-body \
    --silent \
    --show-error \
    --resolve "${DOMAIN}:80:127.0.0.1" \
    "http://${DOMAIN}/" \
    >/dev/null

if [[ "${ENABLE_TLS}" == "1" ]]; then
    CURRENT_STEP="Let's Encrypt-certifikat"
    certbot \
        --nginx \
        --non-interactive \
        --agree-tos \
        --redirect \
        --email "${LE_EMAIL}" \
        --domains "${DOMAIN}"

    curl \
        --fail-with-body \
        --silent \
        --show-error \
        "https://${DOMAIN}/" \
        >/dev/null
fi

CURRENT_STEP="slutkontrol"
systemctl is-active --quiet "${FPM_SERVICE}"
systemctl is-active --quiet nginx
systemctl is-active --quiet mariadb
systemctl is-active --quiet stars-messenger-worker.service
systemctl is-active --quiet stars-queue-reconcile.timer

test "$(readlink -f "${APP_ROOT}")" = "${RELEASE_ROOT}"
test -f "${APP_ROOT}/composer.json"
test -f "${APP_ROOT}/bin/console"
test -f "${APP_ROOT}/frontend/build/index.html"
test -f "${APP_ROOT}/.env.local"

run_as_app env APP_ENV=prod APP_DEBUG=0 "${PHP_BIN}" "${APP_ROOT}/bin/console" about
run_as_app env APP_ENV=prod APP_DEBUG=0 "${PHP_BIN}" "${APP_ROOT}/bin/console" doctrine:migrations:status

write_rollback_script
ln -sfn "${BACKUP_DIR}" "${BACKUP_BASE}/last-install"

printf '\nStars Recindled er installeret.\n'
printf 'Adresse:       %s\n' "${FRONTEND_URL}"
printf 'Applikation:   %s -> %s\n' "${APP_ROOT}" "${RELEASE_ROOT}"
printf 'Database:      %s\n' "${DB_NAME}"
printf 'Databasebruger:%s\n' "${DB_USER}"
printf 'Secrets:       %s og %s\n' "${DATABASE_SECRET_FILE}" "${APP_SECRET_FILE}"
printf 'Log og backup: %s\n' "${BACKUP_DIR}"
printf 'Rollback:      %s/rollback.sh\n' "${BACKUP_DIR}"
printf '\nOpret et testspil med:\n'
printf '  cd %q\n' "${APP_ROOT}"
printf '  sudo -u %q APP_ENV=prod %q bin/console stars:game:create \\\n' "${APP_USER}" "${PHP_BIN}"
printf '    %q \\\n' 'Installationsprøve'
printf '    --player=%q \\\n' 'Skifter <js@bellcom.dk>'
printf '    --player=%q\n' 'Testspiller <test@example.net>'
