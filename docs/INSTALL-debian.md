# Installation på Debian/Ubuntu

Eksemplet antager:

```bash
APP_ROOT="/var/www/stars/current"
PACKAGE_REPOSITORY="https://github.com/skifter/starsrecindled.git"
DATABASE_NAME="stars"
DATABASE_USER="stars"
```

Symfony 7.4 LTS kræver PHP 8.2 eller nyere. Debian 12 kan bruge PHP 8.2; Debian 13
eller en kontrolleret PHP-kilde kan bruges til nyere PHP.

## 1. Pakker

```bash
sudo apt-get update
sudo apt-get install -y \
  git unzip mariadb-server \
  php-cli php-fpm php-mysql php-mbstring php-xml php-curl php-intl php-zip
```

Installér Composer efter den officielle Composer-procedure.

## 2. Backup

```bash
APP_ROOT="/var/www/stars/current"
BACKUP_ROOT="/var/backups/stars/$(date +%Y%m%d-%H%M%S)"

sudo install -d -m 0750 "${BACKUP_ROOT}"
sudo cp -a "${APP_ROOT}/composer.json" "${APP_ROOT}/composer.lock" "${BACKUP_ROOT}/" 2>/dev/null || true
sudo cp -a "${APP_ROOT}/config" "${BACKUP_ROOT}/config" 2>/dev/null || true
sudo mariadb-dump --single-transaction --routines --triggers stars \
  | sudo gzip -9 > "${BACKUP_ROOT}/stars.sql.gz" 2>/dev/null || true
```

## 3. Installer pakken

```bash
APP_ROOT="/var/www/stars/current"
PACKAGE_REPOSITORY="https://github.com/skifter/starsrecindled.git"

cd "${APP_ROOT}"
composer config repositories.starsrecindled vcs "${PACKAGE_REPOSITORY}"
composer require \
  skifter/starsrecindled:dev-main \
  doctrine/doctrine-migrations-bundle \
  symfony/doctrine-messenger
```

Til lokal udvikling ved siden af applikationen:

```bash
APP_ROOT="/var/www/stars/current"
PACKAGE_ROOT="/srv/git/starsrecindled"

cd "${APP_ROOT}"
composer config repositories.starsrecindled path "${PACKAGE_ROOT}"
composer require skifter/starsrecindled:@dev
```

## 4. Registrér bundle og konfiguration

Tag først en ny backup af de filer, der ændres:

```bash
APP_ROOT="/var/www/stars/current"
STAMP="$(date +%Y%m%d-%H%M%S)"

cd "${APP_ROOT}"
cp -a config/bundles.php "config/bundles.php.${STAMP}.bak"
cp -a config/packages/doctrine.yaml "config/packages/doctrine.yaml.${STAMP}.bak"
cp -a config/packages/messenger.yaml "config/packages/messenger.yaml.${STAMP}.bak" 2>/dev/null || true
```

Tilføj bundlet i `config/bundles.php` med `vim`:

```bash
vim config/bundles.php
```

```php
Bellcom\StarsTurnBundle\StarsTurnBundle::class => ['all' => true],
```

Kopiér eksempelkonfigurationen og merge eksisterende Doctrine-konfiguration i stedet
for blindt at overskrive den:

```bash
APP_ROOT="/var/www/stars/current"
PACKAGE_ROOT="${APP_ROOT}/vendor/skifter/starsrecindled"

cd "${APP_ROOT}"
install -m 0644 "${PACKAGE_ROOT}/examples/symfony/config/packages/stars_turn.yaml" config/packages/stars_turn.yaml
install -m 0644 "${PACKAGE_ROOT}/examples/symfony/config/packages/doctrine_migrations.yaml" config/packages/doctrine_migrations.yaml
install -m 0644 "${PACKAGE_ROOT}/examples/symfony/config/routes/stars_turn.yaml" config/routes/stars_turn.yaml
```

Tilføj Messenger-routing fra:

```bash
vim "${PACKAGE_ROOT}/examples/symfony/config/packages/messenger.yaml"
vim config/packages/messenger.yaml
```

Tilføj `StarsTurnBundle`-mappingen fra Doctrine-eksemplet til den eksisterende
`config/packages/doctrine.yaml`.

## 5. Miljø

```bash
APP_ROOT="/var/www/stars/current"
cd "${APP_ROOT}"

cat >> .env.local <<'EOF'
DATABASE_URL="mysql://stars:CHANGE_ME@127.0.0.1:3306/stars?serverVersion=10.11.0-MariaDB&charset=utf8mb4"
MESSENGER_TRANSPORT_DSN="doctrine://default?queue_name=async"
MAILER_DSN="smtp://127.0.0.1:25"
STARS_MAILER_FROM="stars@example.net"
STARS_FRONTEND_BASE_URL="https://stars.example.net"
EOF
```

Erstat adgangskoden og domænet. Brug Symfony Secrets eller deployment secrets i
produktion.

## 6. MariaDB

```bash
DATABASE_NAME="stars"
DATABASE_USER="stars"
DATABASE_PASSWORD="CHANGE_ME"

sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`${DATABASE_NAME}\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DATABASE_USER}'@'127.0.0.1'
  IDENTIFIED BY '${DATABASE_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DATABASE_NAME}\`.*
  TO '${DATABASE_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

## 7. Validering og migration

```bash
APP_ROOT="/var/www/stars/current"
cd "${APP_ROOT}"

php bin/console lint:yaml config
php bin/console lint:container
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:setup-transports
php bin/console debug:router | grep -F 'stars_turn_'
php bin/console debug:container 'Bellcom\StarsTurnBundle\Domain\TurnEngineInterface'
```

## 8. Funktionstest

```bash
APP_ROOT="/var/www/stars/current"
cd "${APP_ROOT}"

php bin/console stars:game:create \
  'Installationsprøve' \
  --player='Alice <alice@example.net>' \
  --player='Bob <bob@example.net>'

php bin/console messenger:consume async --limit=10 -vv
```

Brug de viste tokens med curl-eksemplerne i `docs/API.md`.

## 9. systemd-worker

Backup først:

```bash
SERVICE="/etc/systemd/system/stars-messenger-worker.service"
[ ! -e "${SERVICE}" ] || sudo cp -a "${SERVICE}" "${SERVICE}.$(date +%Y%m%d-%H%M%S).bak"
```

Installér worker og reconciliation timer:

```bash
APP_ROOT="/var/www/stars/current"
PACKAGE_ROOT="${APP_ROOT}/vendor/skifter/starsrecindled"

sudo install -m 0644 "${PACKAGE_ROOT}/deploy/systemd/stars-messenger-worker.service" \
  /etc/systemd/system/stars-messenger-worker.service
sudo install -m 0644 "${PACKAGE_ROOT}/deploy/systemd/stars-queue-reconcile.service" \
  /etc/systemd/system/stars-queue-reconcile.service
sudo install -m 0644 "${PACKAGE_ROOT}/deploy/systemd/stars-queue-reconcile.timer" \
  /etc/systemd/system/stars-queue-reconcile.timer

sudo systemctl daemon-reload
sudo systemctl enable --now stars-messenger-worker.service stars-queue-reconcile.timer
```

Test:

```bash
sudo systemctl status --no-pager stars-messenger-worker.service
sudo systemctl status --no-pager stars-queue-reconcile.timer
sudo journalctl -u stars-messenger-worker.service -n 100 --no-pager
```

## Rollback

Stop først workers:

```bash
sudo systemctl disable --now stars-messenger-worker.service stars-queue-reconcile.timer
```

Rul sidste migration tilbage:

```bash
APP_ROOT="/var/www/stars/current"
cd "${APP_ROOT}"
php bin/console doctrine:migrations:migrate prev --no-interaction
```

Fjern pakken og gendan konfiguration:

```bash
APP_ROOT="/var/www/stars/current"
cd "${APP_ROOT}"
composer remove skifter/starsrecindled
```

Gendan derefter de tidsstemplede `.bak`-filer og database-dumpet fra
`/var/backups/stars/` efter behov.
