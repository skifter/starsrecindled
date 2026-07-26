# Arkitektur

## Ansvar

- Symfony håndterer HTTP, validering, services og CLI.
- MariaDB er autoritativ lagring og første Messenger-transport.
- Messenger-workers genererer runder og sender e-mail.
- SvelteKit-klienten er en separat frontend og taler kun JSON API.
- `TurnEngineInterface` indeholder al deterministisk spilmotorlogik.

## Transaktionsmodel

Den sidste aflevering låser `stars_turn` med pessimistic write lock. Kun den proces,
der ændrer status fra `open` til `queued`, planlægger “alle har afleveret” og
generering. Handleren kontrollerer status igen, så dublerede Messenger-beskeder er
ufarlige.

Den medfølgende handler udfører engine-beregningen i én databasetransaktion. Det er
sikkert og enkelt for MVP. Når en rigtig engine bruger mere end cirka 10 sekunder,
bør flowet opdeles i:

1. atomisk reservation med generation-id;
2. beregning uden åben transaktion;
3. atomisk publicering med compare-and-swap.

## Fog of war

Den globale `initialState` og `resultState` må kun bruges internt. En produktionsklar
udgave skal implementere en `PlayerProjectionInterface`, som filtrerer planeter,
flåder, design, rapporter og sensordata pr. spiller.

## Reproducerbarhed

En engine må kun afhænge af:

- rundens initial-state;
- alle afleverede ordrer;
- det gemte random seed;
- rules-version.

Samme input skal give byte-for-byte ækvivalent output efter kanonisk JSON-sortering.
