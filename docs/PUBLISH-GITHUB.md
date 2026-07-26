# Publicering på GitHub

Repositoryet er målrettet:

```text
https://github.com/skifter/starsrecindled
```

## 1. Tag backup af arbejdskopien

```bash
SOURCE_ROOT="/path/to/starsrecindled"
BACKUP_FILE="${HOME}/starsrecindled-$(date +%Y%m%d-%H%M%S).tar.gz"

tar -C "$(dirname "${SOURCE_ROOT}")" -czf "${BACKUP_FILE}" "$(basename "${SOURCE_ROOT}")"
printf 'Backup: %s\n' "${BACKUP_FILE}"
```

## 2. Initialisér og push

```bash
SOURCE_ROOT="/path/to/starsrecindled"
REPOSITORY="git@github.com:skifter/starsrecindled.git"

cd "${SOURCE_ROOT}"
git init
git branch -M main
git remote add origin "${REPOSITORY}"
git add .
git commit -m 'Initial Stars Recindled MVP'
git push -u origin main
```

Hvis `origin` allerede findes:

```bash
REPOSITORY="git@github.com:skifter/starsrecindled.git"

git remote set-url origin "${REPOSITORY}"
git remote -v
```

## 3. Kontrollér repositoryet

```bash
git status --short
git remote -v
git log -1 --oneline
```

GitHub Actions kører automatisk Composer-validering, PHP-syntaxkontrol og PHPUnit på `main` og pull requests.

## Installation i en Symfony-applikation

Indtil pakken eventuelt registreres på Packagist, tilføjes GitHub-repositoryet i rodprojektet:

```bash
APP_ROOT="/var/www/stars/current"
REPOSITORY="https://github.com/skifter/starsrecindled.git"

cd "${APP_ROOT}"
composer config repositories.starsrecindled vcs "${REPOSITORY}"
composer require skifter/starsrecindled:dev-main
```

Når der er lavet et stabilt tag, eksempelvis `v0.1.0`, kan installationen fastlåses:

```bash
composer require skifter/starsrecindled:^0.1
```

Det kræver fortsat repository-konfigurationen ovenfor, medmindre pakken registreres på Packagist.

## Rollback af første push

Hvis repositoryet skal nulstilles lokalt uden at slette kildefilerne:

```bash
SOURCE_ROOT="/path/to/starsrecindled"

cd "${SOURCE_ROOT}"
rm -rf .git
```

En allerede pushet commit fjernes ikke fra GitHub af denne lokale rollback. Brug i stedet en ny revert-commit ved et offentligt repository.
