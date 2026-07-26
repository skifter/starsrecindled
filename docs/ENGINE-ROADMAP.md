# Roadmap for den egentlige 4X-engine

Bundlet holder turn-workflow og spilmotor adskilt. Udbygningen bør ske som små,
deterministiske moduler med golden-master-scenarier.

## Fase 1: Spilbar vertical slice

- galaksegenerering med seed;
- hjemmeplaneter og neutrale planeter;
- befolkning, mineraler, fabrikker og miner;
- flådedesign med masse, motor og brændstof;
- waypoint-bevægelse;
- transport af kolonister og mineraler;
- kolonisering;
- spiller-specifik rapport og fog of war.

## Fase 2: Klassisk Stars!-dybde

- race-design og habitability;
- forskning og tech-niveauer;
- produktion queues;
- scanning og cloaking;
- minefelter og minerydning;
- stjernebaser og orbitaler;
- diplomati og transfer;
- kamp med reproducerbar taktikopløsning.

## Fase 3: Kompatibilitet og kvalitet

- dokumenterede testcases mod lovligt observeret originaladfærd;
- separate rule sets for originale quirks og rettede regler;
- replay og checksum pr. runde;
- adminværktøj til force-generate, rollback og spillererstatning;
- rate limiting, Symfony Security og invitationer;
- browsernotifikationer/Mercure efter behov.

## Foreslået domæneopdeling

```text
src/StarsEngine/
├── Economy/
├── Movement/
├── Production/
├── Research/
├── Sensors/
├── Colonisation/
├── Minefields/
├── Combat/
├── Race/
├── Projection/
└── TurnPipeline/
```

Hvert trin i `TurnPipeline` skal modtage og returnere immutable state-data. Der må
ikke læses fra databasen inde i engine-laget.
