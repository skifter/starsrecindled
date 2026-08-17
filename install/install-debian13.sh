#!/usr/bin/env bash

set -Eeuo pipefail
umask 027
IFS=$'\n\t'

SCRIPT_VERSION="2026-08-17.4"
CURRENT_STEP="initialisering"

# STARS_INSTALLER_CONFIG_061
# Husk om værdierne kom eksplicit fra kaldet. Prioritet:
# CLI/environment > eksisterende .env.local/nginx > interaktivt spørgsmål/default.
DOMAIN_EXPLICIT="${DOMAIN+x}"
ENABLE_TLS_EXPLICIT="${ENABLE_TLS+x}"
LE_EMAIL_EXPLICIT="${LE_EMAIL+x}"
MAILER_DSN_EXPLICIT="${MAILER_DSN+x}"
STARS_MAILER_FROM_EXPLICIT="${STARS_MAILER_FROM+x}"

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
ENABLE_TLS="${ENABLE_TLS:-}"
EXISTING_TLS="0"
LE_EMAIL="${LE_EMAIL:-}"
MAILER_DSN="${MAILER_DSN:-smtp://sogo.bellcom.dk:25?require_tls=true}"
STARS_MAILER_FROM="${STARS_MAILER_FROM:-}"

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
EXISTING_ENV_FILE="${APP_ROOT}/.env.local"
EXISTING_INSTALLATION="0"
EXISTING_TLS="0"
CONFIG_SOURCE="fresh/defaults"
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
UPDATE_MODE="0"
UPDATE_SOURCE=""
WEB_CHECK_HTTP_CODE=""
WEB_CHECK_URL=""

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

# STARS_FAST_UPDATE_062
# Kør en kommando som app-brugeren med løbende status og hård timeout.
run_as_app_monitored() {
    local timeout_seconds="$1"
    local label="$2"
    shift 2

    local started="${SECONDS}"
    local pid
    local status

    log "${label}"

    (
        run_as_app \
            timeout \
                --foreground \
                --signal=TERM \
                --kill-after=10s \
                "${timeout_seconds}s" \
                "$@"
    ) &
    pid="$!"

    while kill -0 "${pid}" 2>/dev/null; do
        sleep 15
        if kill -0 "${pid}" 2>/dev/null; then
            log "${label} - stadig i gang ($((SECONDS - started)) sek.)"
        fi
    done

    if wait "${pid}"; then
        status=0
    else
        status="$?"
    fi

    if [[ "${status}" == "124" || "${status}" == "137" ]]; then
        warn "${label} overskred timeout på ${timeout_seconds} sekunder."
        return 124
    fi

    if [[ "${status}" != "0" ]]; then
        warn "${label} fejlede med exitkode ${status}."
        return "${status}"
    fi

    log "${label} - OK efter $((SECONDS - started)) sek."
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


# Læs én kendt værdi fra den eksisterende Symfony .env.local.
# Filen er genereret af Stars-installeren og sources i en isoleret bash-proces.
read_existing_dotenv() {
    local key="$1"
    local file="${EXISTING_ENV_FILE}"
    [[ -r "${file}" ]] || return 1

    /bin/bash -c '
        set -a
        # shellcheck disable=SC1090
        source "$1"
        printf "%s" "${!2-}"
    ' _ "${file}" "${key}"
}

prompt_value() {
    local variable_name="$1"
    local label="$2"
    local default_value="${3-}"
    local answer=""

    if [[ ! -t 0 || ! -t 1 ]]; then
        [[ -n "${default_value}" ]] || die "${label} mangler, og installationen kører ikke interaktivt."
        printf -v "${variable_name}" '%s' "${default_value}"
        return
    fi

    if [[ -n "${default_value}" ]]; then
        read -r -p "${label} [${default_value}]: " answer
        answer="${answer:-${default_value}}"
    else
        while [[ -z "${answer}" ]]; do
            read -r -p "${label}: " answer
        done
    fi
    printf -v "${variable_name}" '%s' "${answer}"
}

prompt_yes_no_01() {
    local variable_name="$1"
    local label="$2"
    local default_value="${3:-1}"
    local answer=""
    local suffix="[Y/n]"
    [[ "${default_value}" == "0" ]] && suffix="[y/N]"

    if [[ ! -t 0 || ! -t 1 ]]; then
        printf -v "${variable_name}" '%s' "${default_value}"
        return
    fi

    while true; do
        read -r -p "${label} ${suffix}: " answer
        answer="${answer:-$([[ "${default_value}" == "1" ]] && printf y || printf n)}"
        case "${answer,,}" in
            y|yes|j|ja) printf -v "${variable_name}" '%s' "1"; return ;;
            n|no|nej)   printf -v "${variable_name}" '%s' "0"; return ;;
        esac
    done
}

resolve_install_configuration() {
    local existing_frontend=""
    local existing_mailer=""
    local existing_from=""
    local nginx_domain=""

    if [[ -r "${EXISTING_ENV_FILE}" ]]; then
        EXISTING_INSTALLATION="1"
        CONFIG_SOURCE="${EXISTING_ENV_FILE}"
        existing_frontend="$(read_existing_dotenv STARS_FRONTEND_BASE_URL || true)"
        existing_mailer="$(read_existing_dotenv MAILER_DSN || true)"
        existing_from="$(read_existing_dotenv STARS_MAILER_FROM || true)"

        if [[ -z "${DOMAIN_EXPLICIT}" && -z "${DOMAIN}" && -n "${existing_frontend}" ]]; then
            case "${existing_frontend}" in
                http://*|https://*)
                    DOMAIN="${existing_frontend#*://}"
                    DOMAIN="${DOMAIN%%/*}"
                    DOMAIN="${DOMAIN%%:*}"
                    ;;
            esac
        fi

        if [[ -z "${MAILER_DSN_EXPLICIT}" && -z "${MAILER_DSN}" && -n "${existing_mailer}" ]]; then
            MAILER_DSN="${existing_mailer}"
        fi
        if [[ -z "${STARS_MAILER_FROM_EXPLICIT}" && -z "${STARS_MAILER_FROM}" && -n "${existing_from}" ]]; then
            STARS_MAILER_FROM="${existing_from}"
        fi

        if [[ "${existing_frontend}" == https://* ]]; then
            EXISTING_TLS="1"
        fi
    fi

    if [[ -f "${NGINX_SITE}" ]]; then
        if grep -Eq 'listen[[:space:]].*443.*ssl' "${NGINX_SITE}"; then
            EXISTING_TLS="1"
        fi
        if [[ -z "${DOMAIN_EXPLICIT}" && -z "${DOMAIN}" ]]; then
            nginx_domain="$(
                awk '
                    $1 == "server_name" {
                        value=$2
                        gsub(/;/, "", value)
                        if (value != "_" && value != "") { print value; exit }
                    }
                ' "${NGINX_SITE}"
            )"
            [[ -n "${nginx_domain}" ]] && DOMAIN="${nginx_domain}"
        fi
    fi

    if [[ -z "${ENABLE_TLS_EXPLICIT}" && -z "${ENABLE_TLS}" ]]; then
        if [[ "${EXISTING_TLS}" == "1" ]]; then
            ENABLE_TLS="1"
        elif [[ "${EXISTING_INSTALLATION}" == "1" ]]; then
            ENABLE_TLS="0"
        fi
    fi

    if [[ -z "${DOMAIN}" ]]; then
        prompt_value DOMAIN "Domain"
    fi
    if [[ -z "${MAILER_DSN}" ]]; then
        prompt_value MAILER_DSN "Mailer DSN" "null://null"
    fi
    if [[ -z "${STARS_MAILER_FROM}" ]]; then
        prompt_value STARS_MAILER_FROM "Mailer from" "js@bellcom.dk"
    fi
    if [[ -z "${ENABLE_TLS}" ]]; then
        prompt_yes_no_01 ENABLE_TLS "Aktivér HTTPS med Let's Encrypt?" "1"
    fi
    if [[ "${ENABLE_TLS}" == "1" && "${EXISTING_TLS}" != "1" && -z "${LE_EMAIL}" ]]; then
        prompt_value LE_EMAIL "Let's Encrypt email" "${STARS_MAILER_FROM}"
    fi
}

[[ "${EUID}" -eq 0 ]] || die "Scriptet skal køres som root."
resolve_install_configuration
[[ -n "${DOMAIN}" ]] || die "DOMAIN mangler. Eksempel: DOMAIN=stars.example.dk"
[[ "${ENABLE_TLS}" == "0" || "${ENABLE_TLS}" == "1" ]] ||
    die "ENABLE_TLS skal være 0 eller 1."

if [[ "${ENABLE_TLS}" == "1" ]]; then
    if [[ "${EXISTING_TLS}" != "1" ]]; then
        [[ -n "${LE_EMAIL}" ]] || die "LE_EMAIL kræves ved første TLS-opsætning."
    fi
    [[ "${DOMAIN}" != *":"* ]] || die "DOMAIN må ikke indeholde portnummer."
    [[ ! "${DOMAIN}" =~ ^[0-9.]+$ ]] || die "Let's Encrypt kræver et domænenavn, ikke en IPv4-adresse."
fi

# En opgradering må ikke fjerne en eksisterende HTTPS-vhost blot fordi
# ENABLE_TLS ikke blev angivet igen. Bevar derfor en fungerende 443-konfiguration.
if [[ -f "${NGINX_SITE}" ]]     && grep -Eq '^[[:space:]]*listen[[:space:]].*443.*ssl' "${NGINX_SITE}"     && grep -Fq "server_name ${DOMAIN}" "${NGINX_SITE}"; then
    EXISTING_TLS="1"
fi
if [[ "${ENABLE_TLS}" == "0" && "${EXISTING_TLS}" == "1" ]]; then
    log "Bevarer eksisterende HTTPS-konfiguration for ${DOMAIN}."
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
log "Konfiguration: domain=${DOMAIN}, TLS=${ENABLE_TLS}, mailer-from=${STARS_MAILER_FROM}"
if [[ "${EXISTING_INSTALLATION}" == "1" ]]; then log "Genbruger eksisterende konfiguration fra ${CONFIG_SOURCE}."; fi

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
require_command timeout

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

CURRENT_STEP="kontrol af package repository"
run_as_app_monitored \
    90 \
    "Kontrollerer adgang til ${PACKAGE_REPOSITORY}" \
    git ls-remote "${PACKAGE_REPOSITORY}" HEAD >/dev/null
log "Repository: OK"

case "${PREVIOUS_CURRENT_KIND}" in
    symlink)
        if [[ -n "${PREVIOUS_CURRENT_TARGET}" && -d "${PREVIOUS_CURRENT_TARGET}" ]]; then
            UPDATE_SOURCE="${PREVIOUS_CURRENT_TARGET}"
        fi
        ;;
    directory)
        if [[ -d "${APP_ROOT}" ]]; then
            UPDATE_SOURCE="${APP_ROOT}"
        fi
        ;;
esac

if [[ \
    -n "${UPDATE_SOURCE}" \
    && -f "${UPDATE_SOURCE}/composer.json" \
    && -f "${UPDATE_SOURCE}/bin/console" \
    && -d "${UPDATE_SOURCE}/vendor/skifter/starsrecindled" \
]]; then
    UPDATE_MODE="1"
    CURRENT_STEP="oprettelse af update-release fra current"
    log "Eksisterende installation fundet."
    log "Update-base: ${UPDATE_SOURCE}"
    log "Kopierer aktiv release lokalt; Symfony create-project springes over."

    install -d -o "${APP_USER}" -g "${APP_GROUP}" -m 0755 "${RELEASE_ROOT}"

    rsync \
        -a \
        --exclude='var/cache/' \
        --exclude='var/log/' \
        --exclude='frontend/node_modules/' \
        "${UPDATE_SOURCE}/" \
        "${RELEASE_ROOT}/"

    install -d \
        -o "${APP_USER}" -g "${APP_GROUP}" -m 0770 \
        "${RELEASE_ROOT}/var" \
        "${RELEASE_ROOT}/var/cache" \
        "${RELEASE_ROOT}/var/log"

    chown -R "${APP_USER}:${APP_GROUP}" "${RELEASE_ROOT}"

    run_as_app composer \
        --working-dir="${RELEASE_ROOT}" \
        config repositories.starsrecindled vcs "${PACKAGE_REPOSITORY}"

    CURRENT_STEP="opdatering af Stars Composer-pakke"
    run_as_app_monitored \
        900 \
        "Opdaterer Stars-pakken fra Git repository" \
        composer \
            --working-dir="${RELEASE_ROOT}" \
            update \
            "skifter/starsrecindled" \
            --with-all-dependencies \
            --prefer-source \
            --no-interaction \
            --no-scripts \
            --no-plugins \
            --ansi
else
    UPDATE_MODE="0"
    CURRENT_STEP="oprettelse af Symfony-værtsapplikation"
    install -d -o "${APP_USER}" -g "${APP_GROUP}" -m 0755 "${RELEASE_ROOT}"
    rmdir "${RELEASE_ROOT}"

    run_as_app_monitored \
        900 \
        "Opretter ny Symfony-værtsapplikation ${SYMFONY_VERSION}" \
        composer \
            create-project \
            "symfony/skeleton:${SYMFONY_VERSION}" \
            "${RELEASE_ROOT}" \
            --prefer-dist \
            --no-interaction \
            --ansi

    run_as_app composer \
        --working-dir="${RELEASE_ROOT}" \
        config repositories.starsrecindled vcs "${PACKAGE_REPOSITORY}"

    CURRENT_STEP="installation af Stars Composer-pakke"
    run_as_app_monitored \
        900 \
        "Henter Stars-pakken og Composer-afhængigheder" \
        composer \
            --working-dir="${RELEASE_ROOT}" \
            require \
            "skifter/starsrecindled:${PACKAGE_VERSION}" \
            symfony/orm-pack \
            doctrine/doctrine-migrations-bundle \
            symfony/mailer \
            symfony/monolog-bundle \
            --with-all-dependencies \
            --prefer-source \
            --no-interaction \
            --ansi
fi

PACKAGE_ROOT="${RELEASE_ROOT}/vendor/skifter/starsrecindled"
test -f "${PACKAGE_ROOT}/composer.json"
test -f "${PACKAGE_ROOT}/examples/symfony/config/packages/stars_turn.yaml"
test -f "${PACKAGE_ROOT}/examples/symfony/config/packages/stars_account.yaml"
test -f "${PACKAGE_ROOT}/examples/symfony/config/packages/messenger.yaml"
test -f "${PACKAGE_ROOT}/examples/symfony/config/routes/stars_turn.yaml"
test -f "${PACKAGE_ROOT}/examples/symfony/config/routes/stars_account.yaml"

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
    "${PACKAGE_ROOT}/examples/symfony/config/packages/stars_account.yaml" \
    "${RELEASE_ROOT}/config/packages/stars_account.yaml"

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

install \
    -o "${APP_USER}" -g "${APP_GROUP}" -m 0644 \
    "${PACKAGE_ROOT}/examples/symfony/config/routes/stars_account.yaml" \
    "${RELEASE_ROOT}/config/routes/stars_account.yaml"

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
if [[ "${ENABLE_TLS}" == "1" || "${EXISTING_TLS}" == "1" ]]; then
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

CURRENT_STEP="optimering af PHP-afhængigheder"
if [[ "${UPDATE_MODE}" == "1" ]]; then
    run_as_app_monitored \
        600 \
        "Optimerer PHP-autoload for update-release" \
        composer \
            --working-dir="${RELEASE_ROOT}" \
            install \
            --no-dev \
            --optimize-autoloader \
            --classmap-authoritative \
            --no-interaction \
            --no-scripts \
            --no-plugins \
            --ansi
else
    run_as_app_monitored \
        600 \
        "Optimerer PHP-autoload" \
        composer \
            --working-dir="${RELEASE_ROOT}" \
            install \
            --no-dev \
            --optimize-autoloader \
            --classmap-authoritative \
            --no-interaction \
            --ansi
fi

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
if [[ "${EXISTING_TLS}" == "1" && "${ENABLE_TLS}" == "0" ]]; then
    log "Bevarer eksisterende nginx-site med HTTPS."
    # PHP kan være blevet opgraderet. Ret kun Stars FPM-socketten i den bevarede vhost.
    sed -i -E "s#fastcgi_pass[[:space:]]+unix:/run/php/php[^;]+;#fastcgi_pass unix:${FPM_SOCKET};#g" "${NGINX_SITE}"
else
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
fi
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
ExecStart=${PHP_BIN} bin/console stars:invitation:reconcile --no-interaction
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
fi
if [[ "${ENABLE_TLS}" == "1" || "${EXISTING_TLS}" == "1" ]]; then
    CURRENT_STEP="HTTPS-funktionstest"
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

CURRENT_STEP="webserver-status og curl-kontrol"
if systemctl is-active --quiet nginx; then
    log "Webserver: OK (nginx kører)"
else
    die "Webserver: FEJL (nginx kører ikke)"
fi

WEB_CHECK_URL="${FRONTEND_URL}/"
if [[ "${FRONTEND_URL}" == https://* ]]; then
    WEB_CHECK_RESOLVE="${DOMAIN}:443:127.0.0.1"
else
    WEB_CHECK_RESOLVE="${DOMAIN}:80:127.0.0.1"
fi

if ! WEB_CHECK_HTTP_CODE="$(curl \
    --silent \
    --show-error \
    --location \
    --output /dev/null \
    --write-out '%{http_code}' \
    --connect-timeout 5 \
    --max-time 20 \
    --resolve "${WEB_CHECK_RESOLVE}" \
    "${WEB_CHECK_URL}")"; then
    die "Curl-kontrol: FEJL (${WEB_CHECK_URL} kunne ikke hentes)"
fi

if [[ ! "${WEB_CHECK_HTTP_CODE}" =~ ^(2|3)[0-9][0-9]$ ]]; then
    die "Curl-kontrol: FEJL (${WEB_CHECK_URL} gav HTTP ${WEB_CHECK_HTTP_CODE})"
fi
log "Curl-kontrol: OK (HTTP ${WEB_CHECK_HTTP_CODE}) ${WEB_CHECK_URL}"

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
if [[ "${UPDATE_MODE}" == "1" ]]; then
    printf 'Installationstype: UPDATE (current genbrugt; ingen Symfony create-project)\n'
else
    printf 'Installationstype: NY INSTALLATION\n'
fi
printf 'Adresse:       %s\n' "${FRONTEND_URL}"
printf 'Applikation:   %s -> %s\n' "${APP_ROOT}" "${RELEASE_ROOT}"
printf 'Database:      %s\n' "${DB_NAME}"
printf 'Databasebruger:%s\n' "${DB_USER}"
printf 'Secrets:       %s og %s\n' "${DATABASE_SECRET_FILE}" "${APP_SECRET_FILE}"
printf 'Log og backup: %s\n' "${BACKUP_DIR}"
printf 'Rollback:      %s/rollback.sh\n' "${BACKUP_DIR}"
printf 'Webserver:      OK (nginx)\n'
printf 'Curl-kontrol:   OK (HTTP %s)\n' "${WEB_CHECK_HTTP_CODE}"
printf '\nOpret et testspil med:\n'
printf '  cd %q\n' "${APP_ROOT}"
printf '  sudo -u %q APP_ENV=prod %q bin/console stars:game:create \\\n' "${APP_USER}" "${PHP_BIN}"
printf '    %q \\\n' 'Installationsprøve'
printf '    --player=%q \\\n' 'Skifter <skifter@skifter.info>'
printf '    --player=%q\n' 'Testspiller <test@example.net>'
