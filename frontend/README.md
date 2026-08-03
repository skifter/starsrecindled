# Stars Rekindled player client

Version 0.2.0 replaces the original technical JSON dashboard with a playable GUI prototype.

## Included screens

- Cinematic main menu.
- Login/direct game access using the existing Game ID, Player ID and bearer token.
- Responsive player shell with top resources, navigation and turn controls.
- Interactive SVG galaxy map with zoom, pan, systems, routes and territory overlays.
- Planet, fleet, research, diplomacy and turn-report views.
- Context panel for the selected star system.
- Visual production, waypoint, colonization and research order creation.
- Existing API actions for status, draft, submit and reopen.
- Technical JSON editor for compatibility with future engine orders.
- Demonstration mode when no backend game is available.

## Backend boundary

The live backend currently exposes the turn workflow and the player's order object. Galaxy, planet, fleet, research and diplomacy data are therefore supplied by `src/lib/demo-data.ts` until the backend implements a player-specific projection/fog-of-war endpoint.

Do not replace the demo data with the global engine state. The server must return only the authenticated player's visible projection.

## Development

```bash
cd frontend
npm install
npm run check
npm run dev -- --host 127.0.0.1
```

Open the demo directly:

```text
/?demo=1
```

Open the access screen directly:

```text
/?access=1&game=1&player=1&turn=1
```

## Production build

```bash
cd frontend
npm ci
npm run check
npm run build
test -r build/index.html
```

The static output remains in `frontend/build`, matching the existing Debian installer and nginx setup.
