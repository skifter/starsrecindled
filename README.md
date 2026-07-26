# Stars Recindled

Installerbart Symfony-bundle til et webbaseret, asynkront og rundebaseret 4X-spil.

Repository: `https://github.com/skifter/starsrecindled`
Composer-pakke: `skifter/starsrecindled`
PHP-namespace: `Bellcom\StarsTurnBundle\` (beholdt for at undgå en unødvendig kodeomdøbning).

Dette repository leverer den første fungerende infrastruktur:

- spil, spillere og runder i MariaDB;
- spiller-token til et simpelt API;
- løbende ordrekladde og endelig aflevering;
- automatisk kølægning, når alle aktive spillere har afleveret;
- rundegenerering i Symfony Messenger;
- e-mail når alle har afleveret;
- e-mail når næste runde er klar;
- idempotente beskeder og mulighed for at genkøe manglende jobs;
- en udskiftelig `TurnEngineInterface`;
- en lille SvelteKit-testklient.

Den medfølgende `DemoTurnEngine` er **ikke** en komplet Stars!-motor. Den beviser hele
flowet og skal erstattes af den egentlige økonomi-, bevægelses-, kamp- og
fog-of-war-engine.

## Hurtig installation

Se [docs/INSTALL-debian.md](docs/INSTALL-debian.md) for en pastebar installation
med backup, validering, test og rollback.

## Installation direkte fra GitHub

I Symfony-applikationens rod:

```bash
REPOSITORY="https://github.com/skifter/starsrecindled.git"

composer config repositories.starsrecindled vcs "${REPOSITORY}"
composer require skifter/starsrecindled:dev-main
```

Registrér bundlet i `config/bundles.php`:

```php
Bellcom\StarsTurnBundle\StarsTurnBundle::class => ['all' => true],
```

Kopiér eksemplerne fra `examples/symfony/` og tilpas miljøvariablerne. Kør derefter:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console lint:yaml config
php bin/console debug:container 'Bellcom\StarsTurnBundle\Domain\TurnEngineInterface'
php bin/console messenger:setup-transports
```

Opret et testspil:

```bash
php bin/console stars:game:create \
  'Alpha Centauri' \
  --player='Alice <alice@example.net>' \
  --player='Bob <bob@example.net>'
```

Kommandoen viser spiller-id og engangstoken. Tokenet gemmes kun som SHA-256-hash.

Start worker:

```bash
php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M -vv
```

## Udskift demo-engine

Opret en service, som implementerer:

```php
Bellcom\StarsTurnBundle\Domain\TurnEngineInterface
```

Konfigurér den derefter:

```yaml
# config/packages/stars_turn.yaml
stars_turn:
  engine_service: App\Stars\Engine\StarsEngine
```

Engine-kontrakten er ren: den modtager starttilstand og alle afleverede ordrer og
returnerer næste tilstand plus individuelle spillerrapporter. Samme input og seed
skal altid give samme resultat.

## Sikkerhed

API-tokenmodellen er beregnet til MVP og private spil. Før offentlig drift bør den
integreres med Symfony Security, CSRF-beskyttelse, rate limiting og rigtig brugerlogin.

Serveren må aldrig sende den globale univers-tilstand til alle browsere. En rigtig
engine skal tilføje en spiller-specifik projection/fog-of-war-service.
