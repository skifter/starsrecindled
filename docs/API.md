# API

Alle endpoints kræver:

```http
X-Stars-Player-Id: 12
Authorization: Bearer <64-tegns-token>
```

Pastebart testsetup:

```bash
API_BASE="http://127.0.0.1:8000"
GAME_ID="1"
PLAYER_ID="1"
TURN_NUMBER="1"
TOKEN="INDSAET_TOKEN_FRA_stars_game_create"
```

## Status

```bash
curl --fail-with-body --silent --show-error \
  -H "X-Stars-Player-Id: ${PLAYER_ID}" \
  -H "Authorization: Bearer ${TOKEN}" \
  "${API_BASE}/stars/api/games/${GAME_ID}/turns/${TURN_NUMBER}"
```

Returnerer spil, rundestatus, afleveringsstatus for spillerne og egne ordrer.
Global univers-tilstand returneres bevidst ikke.

## Gem kladde

```bash
curl --fail-with-body --silent --show-error \
  -X PUT \
  -H 'Content-Type: application/json' \
  -H "X-Stars-Player-Id: ${PLAYER_ID}" \
  -H "Authorization: Bearer ${TOKEN}" \
  --data '{"orders":{"fleets":[],"production":[]}}' \
  "${API_BASE}/stars/api/games/${GAME_ID}/turns/${TURN_NUMBER}/draft"
```

## Aflever

```bash
curl --fail-with-body --silent --show-error \
  -X POST \
  -H 'Content-Type: application/json' \
  -H "X-Stars-Player-Id: ${PLAYER_ID}" \
  -H "Authorization: Bearer ${TOKEN}" \
  --data '{"orders":{"fleets":[],"production":[]}}' \
  "${API_BASE}/stars/api/games/${GAME_ID}/turns/${TURN_NUMBER}/submit"
```

Når sidste spiller afleverer, ændres runden atomisk til `queued`, der oprettes
notifikationer, og `GenerateTurnMessage` lægges på Messenger-køen.

## Genåbn

```bash
curl --fail-with-body --silent --show-error \
  -X POST \
  -H "X-Stars-Player-Id: ${PLAYER_ID}" \
  -H "Authorization: Bearer ${TOKEN}" \
  "${API_BASE}/stars/api/games/${GAME_ID}/turns/${TURN_NUMBER}/reopen"
```

Genåbning er kun mulig, mens runden stadig er `open`.
