<script lang="ts">
  import Icon from './Icon.svelte';
  import { OWNER_COLORS, ownerForPlayerId } from '../player-colors';
  import type { AccountTurnStatusPlayer, FleetSummary, Owner, RouteLink, StarSystem } from '../types';

  export let systems: StarSystem[] = [];
  export let routes: RouteLink[] = [];
  export let plannedRoutes: RouteLink[] = [];
  export let players: AccountTurnStatusPlayer[] = [];
  export let currentPlayerId = 0;
  export let selectedId = '';
  export let selectedFleetId = '';
  export let planningFleetId = '';
  export let validDestinationIds: string[] = [];
  export let liveMode = false;
  export let onSelect: (system: StarSystem) => void;
  export let onSelectFleet: (fleet: FleetSummary, system: StarSystem) => void = () => {};

  $: byId = new Map(systems.map((system) => [system.id, system]));
  $: playerIds = players.map((player) => player.id);
  let zoom = 1;
  let panX = 0;
  let panY = 0;
  let dragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let startPanX = 0;
  let startPanY = 0;
  let moved = false;

  function starPoint(id: string): { x: number; y: number } {
    const system = byId.get(id);
    return { x: (system?.x ?? 0) * 10, y: (system?.y ?? 0) * 6.2 };
  }

  function playerOwner(playerId: number): Owner {
    return ownerForPlayerId(playerId, playerIds);
  }

  function fleetColor(fleet: FleetSummary): string {
    return fleet.ownerPlayerId ? OWNER_COLORS[ownerForPlayerId(fleet.ownerPlayerId, playerIds)] : OWNER_COLORS.neutral;
  }

  function sensorRange(system: StarSystem): number {
    if (system.ownerPlayerId !== currentPlayerId) return 0;
    return Math.max(0, Math.min(3, Math.round(system.sensorRange ?? 1)));
  }

  function systemStatus(system: StarSystem): string {
    if (system.ownerPlayerId === null || system.owner === 'neutral') return 'UNCLAIMED';
    if (system.ownerPlayerId === currentPlayerId) return system.isCapital ? 'YOUR CAPITAL' : 'YOUR COLONY';
    return (system.ownerLabel ?? 'OTHER PLAYER').toUpperCase();
  }

  function handleWheel(event: WheelEvent): void {
    event.preventDefault();
    const next = Math.min(2.2, Math.max(.72, zoom + (event.deltaY < 0 ? .12 : -.12)));
    zoom = Number(next.toFixed(2));
  }

  function pointerDown(event: PointerEvent): void {
    if (event.button !== 0) return;

    const target = event.target;
    if (target instanceof Element && target.closest('.system')) {
      dragging = false;
      moved = false;
      return;
    }

    dragging = true;
    moved = false;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    startPanX = panX;
    startPanY = panY;
    (event.currentTarget as SVGSVGElement).setPointerCapture(event.pointerId);
  }

  function pointerMove(event: PointerEvent): void {
    if (!dragging) return;
    const dx = event.clientX - dragStartX;
    const dy = event.clientY - dragStartY;
    if (Math.abs(dx) + Math.abs(dy) > 4) moved = true;
    panX = startPanX + dx / zoom;
    panY = startPanY + dy / zoom;
  }

  function pointerUp(event: PointerEvent): void {
    dragging = false;
    const target = event.currentTarget as SVGSVGElement;
    if (target.hasPointerCapture(event.pointerId)) target.releasePointerCapture(event.pointerId);
  }

  function selectSystem(event: MouseEvent | KeyboardEvent, system: StarSystem): void {
    event.stopPropagation();
    if (event instanceof KeyboardEvent && event.key !== 'Enter' && event.key !== ' ') return;
    if (!moved) onSelect(system);
  }

  function selectFleet(event: MouseEvent | KeyboardEvent, fleet: FleetSummary, system: StarSystem): void {
    event.stopPropagation();
    if (event instanceof KeyboardEvent && event.key !== 'Enter' && event.key !== ' ') return;
    if (!moved) onSelectFleet(fleet, system);
  }

  function resetView(): void {
    zoom = 1;
    panX = 0;
    panY = 0;
  }
</script>

<div class="galaxy-map" class:dragging class:planning={planningFleetId !== ''}>
  <svg
    viewBox="0 0 1000 620"
    preserveAspectRatio="xMidYMid meet"
    aria-label="Interactive galaxy map"
    onwheel={handleWheel}
    onpointerdown={pointerDown}
    onpointermove={pointerMove}
    onpointerup={pointerUp}
    onpointercancel={pointerUp}
  >
    <defs>
      <radialGradient id="nebulaBlue"><stop offset="0" stop-color="#1d9dd4" stop-opacity=".26"/><stop offset="1" stop-color="#1d9dd4" stop-opacity="0"/></radialGradient>
      <radialGradient id="nebulaViolet"><stop offset="0" stop-color="#9b45bd" stop-opacity=".24"/><stop offset="1" stop-color="#9b45bd" stop-opacity="0"/></radialGradient>
      <radialGradient id="starCore"><stop offset="0" stop-color="#fff"/><stop offset=".25" stop-color="#d9f4ff"/><stop offset=".65" stop-color="currentColor"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></radialGradient>
      <filter id="glow"><feGaussianBlur stdDeviation="3" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
      <pattern id="starDust" width="90" height="72" patternUnits="userSpaceOnUse">
        <circle cx="8" cy="12" r=".8" fill="#d7efff" opacity=".55"/><circle cx="55" cy="35" r=".55" fill="#fff" opacity=".42"/><circle cx="83" cy="64" r=".7" fill="#9bcfff" opacity=".46"/><circle cx="30" cy="59" r=".4" fill="#fff" opacity=".36"/>
      </pattern>
    </defs>

    <rect width="1000" height="620" fill="#02070e" />
    <rect width="1000" height="620" fill="url(#starDust)" />
    <ellipse cx="420" cy="335" rx="360" ry="230" fill="url(#nebulaBlue)" />
    <ellipse cx="790" cy="290" rx="300" ry="245" fill="url(#nebulaViolet)" />
    <ellipse cx="260" cy="500" rx="220" ry="145" fill="#b57a20" opacity=".05" />

    <g transform={`translate(${panX} ${panY}) scale(${zoom})`}>
      {#if !liveMode}
        <path class="territory player" d="M165 142 C250 96 410 104 520 162 C613 212 618 335 570 428 C512 502 348 504 232 445 C150 398 107 267 165 142Z" />
        <path class="territory crimson" d="M395 8 C520 -20 695 9 716 110 C663 165 565 168 486 146 C430 112 400 67 395 8Z" />
        <path class="territory violet" d="M626 122 C774 88 955 120 1004 274 L1004 520 C875 554 741 507 670 438 C610 358 580 217 626 122Z" />
        <path class="territory amber" d="M54 390 C180 363 328 420 354 548 C282 636 118 648 20 564 C1 491 15 435 54 390Z" />
      {/if}

      {#each routes as route}
        {@const from = starPoint(route.from)}
        {@const to = starPoint(route.to)}
        <line class:hostile={route.kind === 'hostile'} class="route" x1={from.x} y1={from.y} x2={to.x} y2={to.y} />
      {/each}

      {#if !liveMode}<path class="planned-route" d="M490 298 Q545 350 680 564" />{/if}
      {#each plannedRoutes as route}
        {@const from = starPoint(route.from)}
        {@const to = starPoint(route.to)}
        <line class="planned-route" x1={from.x} y1={from.y} x2={to.x} y2={to.y} />
      {/each}

      {#each systems as system}
        {@const x = system.x * 10}
        {@const y = system.y * 6.2}
        {@const validDestination = planningFleetId !== '' && validDestinationIds.includes(system.id)}
        {@const invalidDestination = planningFleetId !== '' && !validDestination && !system.fleets.some((fleet) => fleet.id === planningFleetId)}
        <g
          class="system"
          class:selected={selectedId === system.id}
          class:valid-destination={validDestination}
          class:invalid-destination={invalidDestination}
          style={`--system-color:${OWNER_COLORS[system.owner]}`}
          transform={`translate(${x} ${y})`}
          role="button"
          tabindex="0"
          aria-label={`Select ${system.name}`}
          onclick={(event) => selectSystem(event, system)}
          onkeydown={(event) => selectSystem(event, system)}
        >
          {#if validDestination}<circle class="destination-ring" r="31" />{/if}
          {#if selectedId === system.id}
            <circle class="selection-ring outer" r="38" /><circle class="selection-ring middle" r="29" /><circle class="selection-ring pulse" r="21" />
          {/if}
          {#if liveMode}
            {@const scanRange = sensorRange(system)}
            {#if scanRange >= 3}<ellipse class="sensor-orbit sensor-orbit-3" rx="34" ry="20" transform="rotate(-48)" />{/if}
            {#if scanRange >= 2}<ellipse class="sensor-orbit sensor-orbit-2" rx="27" ry="16" transform="rotate(31)" />{/if}
            {#if scanRange >= 1}<ellipse class="sensor-orbit sensor-orbit-1" rx="21" ry="12" transform="rotate(-17)" />{/if}
            <circle class="ownership-orbit" class:unclaimed={system.owner === 'neutral'} r={system.isCapital ? 15 : 12} />
          {/if}
          <circle class="star-glow" r={system.isCapital ? 17 : 12} />
          <circle class="star" class:colonized={liveMode && system.owner !== 'neutral'} r={system.isCapital ? 5.5 : 4} />
          {#if system.isCapital}<path class="capital" d="M-8-15 0-23 8-15 5-8-5-8Z" />{/if}

          {#each system.fleets as fleet, fleetIndex}
            {@const fleetX = 14 + (fleetIndex % 3) * 10}
            {@const fleetY = -13 - Math.floor(fleetIndex / 3) * 10}
            <g
              class="fleet-marker-group"
              class:own-fleet={fleet.ownerPlayerId === currentPlayerId}
              class:selected-fleet={fleet.id === selectedFleetId}
              class:planning-fleet={fleet.id === planningFleetId}
              transform={`translate(${fleetX} ${fleetY})`}
              style={`--fleet-color:${fleetColor(fleet)}`}
              role="button"
              tabindex={fleet.ownerPlayerId === currentPlayerId ? 0 : -1}
              aria-label={`${fleet.name}, ${fleet.ships} ships`}
              onclick={(event) => selectFleet(event, fleet, system)}
              onkeydown={(event) => selectFleet(event, fleet, system)}
            >
              <circle class="fleet-hit" r="7" />
              <path class="fleet-marker" d="M-5 -4 5 0 -5 4 -2 0Z" />
            </g>
          {/each}

          <text class="system-label" y="20" text-anchor="middle">{system.name.toUpperCase()}</text>
          {#if liveMode}<text class="system-status" y="31" text-anchor="middle">{systemStatus(system)}</text>{/if}
        </g>
      {/each}

      {#if !liveMode}<g class="scan-marker" transform="translate(592 370)"><circle r="8"/><path d="M-15 0h30M0-15v30"/></g>{/if}
    </g>
  </svg>

  <div class="map-controls">
    <button aria-label="Center map" onclick={resetView}><Icon name="target" /></button>
    <button aria-label="Map layers"><Icon name="layers" /></button>
    <button aria-label="Zoom in" onclick={() => (zoom = Math.min(2.2, zoom + .15))}><Icon name="plus" /></button>
    <button aria-label="Zoom out" onclick={() => (zoom = Math.max(.72, zoom - .15))}><Icon name="minus" /></button>
  </div>

  <div class="minimap" aria-hidden="true">
    <svg viewBox="0 0 100 70">
      {#if liveMode}
        {#each systems.filter((system) => system.ownerPlayerId !== null) as system}
          {@const miniRange = sensorRange(system)}
          {#if miniRange > 0}<circle class="mini-sensor" style={`--mini-color:${OWNER_COLORS[system.owner]}`} cx={system.x} cy={system.y * .62} r={4 + miniRange * 2.4}/>{/if}
          <circle class="mini-colony" style={`--mini-color:${OWNER_COLORS[system.owner]}`} cx={system.x} cy={system.y * .62} r={system.isCapital ? 1.8 : 1.25}/>{/each}
      {:else}
        <path class="mini player" d="M10 15Q38 3 58 23T48 58Q20 64 7 42Z"/><path class="mini crimson" d="M38 2Q68-2 72 17Q59 25 43 16Z"/><path class="mini violet" d="M62 12Q96 11 99 42Q88 67 61 55Z"/><path class="mini amber" d="M4 45Q26 39 36 67Q11 76 1 58Z"/>
      {/if}
      <rect x={Math.max(2, 30 - panX / 25)} y={Math.max(2, 20 - panY / 25)} width={45 / zoom} height={32 / zoom}/></svg>
    <span>{Math.round(zoom * 100)}%</span>
  </div>

  {#if systems.length === 0}
    <div class="empty-universe"><strong>No server galaxy</strong><span>This game predates the 0.5.1 universe generator. Create a new game to test live galaxy gameplay.</span></div>
  {/if}

  {#if planningFleetId !== ''}
    <div class="planning-hint"><Icon name="target" size={16}/><span>Choose one of the highlighted connected systems.</span></div>
  {/if}

  <div class="map-legend">
    {#if liveMode}
      {#each players as player}
        <span class="legend-entry" style={`--legend-color:${OWNER_COLORS[playerOwner(player.id)]}`}>
          <i></i>{player.name}{player.id === currentPlayerId ? ' (you)' : ''}
        </span>
      {/each}
      <span class="legend-entry unclaimed-entry"><i></i>Unclaimed</span>
      <span class="legend-hint"><b>orbit rings</b> colony sensors · <b>solid</b> colony · <b>dotted</b> unclaimed</span>
    {:else}
      <span class="friendly">Dominion</span><span class="neutral">Unclaimed</span><span class="hostile-dot">Hostile</span>
    {/if}
  </div>
</div>

<style>
  .galaxy-map { position:relative;width:100%;height:100%;min-height:480px;overflow:hidden;background:#02070e;user-select:none }
  .galaxy-map svg { display:block;width:100%;height:100%;cursor:grab;touch-action:none }
  .galaxy-map.dragging svg { cursor:grabbing }
  .territory { fill-opacity:.035;stroke-width:1.3;stroke-dasharray:5 4 }
  .territory.player { fill:#37bfff;stroke:#37bfff }.territory.crimson { fill:#ff544f;stroke:#ff544f }.territory.violet { fill:#bd55ed;stroke:#bd55ed }.territory.amber { fill:#e7a72c;stroke:#e7a72c }
  .route { stroke:#5d8ba8;stroke-opacity:.34;stroke-width:1.2 }.route.hostile { stroke:#fa6b62;stroke-dasharray:4 5 }
  .planned-route { fill:none;stroke:#ffd05c;stroke-width:2.2;stroke-dasharray:7 5;opacity:.95;filter:drop-shadow(0 0 4px rgba(255,208,92,.45)) }
  .system { color:var(--system-color);cursor:pointer;outline:none;transition:opacity .15s }
  .system.invalid-destination { opacity:.32 }.system.valid-destination { opacity:1 }
  .system:focus-visible .selection-ring,.system:hover .star-glow { opacity:1 }
  .star-glow { fill:url(#starCore);color:var(--system-color);opacity:.72;filter:url(#glow);transition:.15s }
  .star { fill:#fff;stroke:var(--system-color);stroke-width:2;filter:url(#glow) }.star.colonized{stroke-width:3}
  .sensor-orbit{fill:none;stroke:var(--system-color);stroke-width:1;opacity:.58;pointer-events:none;filter:drop-shadow(0 0 3px color-mix(in srgb,var(--system-color) 35%,transparent))}.sensor-orbit-2{stroke-dasharray:5 3;opacity:.47}.sensor-orbit-3{stroke-dasharray:2 4;opacity:.38}.ownership-orbit { fill:none;stroke:var(--system-color);stroke-width:2.4;opacity:.9 }.ownership-orbit.unclaimed{stroke:#dcecff;stroke-width:1.5;stroke-dasharray:3 3;opacity:.7}
  .system-label { fill:#b9cbd8;font-size:10px;letter-spacing:.9px;paint-order:stroke;stroke:#02070e;stroke-width:3px;stroke-linejoin:round }
  .system-status{fill:var(--system-color);font-size:6.7px;font-weight:700;letter-spacing:.65px;paint-order:stroke;stroke:#02070e;stroke-width:2.5px;stroke-linejoin:round}
  .selected .system-label { fill:#edfaff;font-weight:700 }
  .selection-ring { fill:none;stroke:#ffd05c;stroke-width:1.8;opacity:.95 }.selection-ring.middle{stroke-dasharray:5 4}.selection-ring.outer{opacity:.42}.selection-ring.pulse{animation:pulse 2s infinite}
  .destination-ring{fill:rgba(255,208,92,.05);stroke:#ffd05c;stroke-width:2;stroke-dasharray:4 3;filter:drop-shadow(0 0 6px rgba(255,208,92,.5));animation:destinationPulse 1.4s ease-in-out infinite}
  .capital { fill:#ffd36b;stroke:#fff0b9;stroke-width:.7;filter:url(#glow) }
  .fleet-marker-group{cursor:default;color:var(--fleet-color);outline:none}.fleet-marker-group.own-fleet{cursor:pointer}.fleet-hit{fill:#02101a;stroke:var(--fleet-color);stroke-width:1;opacity:.88}.fleet-marker{fill:var(--fleet-color);stroke:#eafaff;stroke-width:.55;filter:drop-shadow(0 0 4px var(--fleet-color))}.fleet-marker-group:hover .fleet-hit,.fleet-marker-group.selected-fleet .fleet-hit{stroke:#ffd05c;stroke-width:2}.fleet-marker-group.planning-fleet .fleet-hit{stroke:#ffd05c;stroke-width:2.5;animation:destinationPulse 1.2s ease-in-out infinite}
  .scan-marker circle,.scan-marker path { fill:none;stroke:#54d1ff;stroke-width:1;opacity:.65 }
  .map-controls { position:absolute;left:14px;bottom:116px;display:grid;gap:5px }
  .map-controls button { width:38px;height:38px;display:grid;place-items:center;color:#84d9ff;border:1px solid rgba(66,176,231,.4);background:rgba(3,16,29,.9);cursor:pointer }
  .map-controls button:hover { background:rgba(12,55,83,.95);border-color:#48c8ff }
  .minimap { position:absolute;left:14px;bottom:14px;width:180px;height:90px;border:1px solid rgba(69,178,232,.42);background:rgba(1,8,15,.9);padding:5px }
  .minimap svg { cursor:default }.minimap .mini { stroke-width:.5;fill-opacity:.12 }.minimap .player { fill:#35c0ff;stroke:#35c0ff }.minimap .crimson { fill:#ff5f58;stroke:#ff5f58 }.minimap .violet { fill:#c864ef;stroke:#c864ef }.minimap .amber { fill:#f0ae39;stroke:#f0ae39 }.minimap rect { fill:none;stroke:#fff;stroke-width:1;opacity:.7 }
  .minimap .mini-sensor{fill:color-mix(in srgb,var(--mini-color) 8%,transparent);stroke:var(--mini-color);stroke-width:.35;stroke-dasharray:1.3 1.2;opacity:.65}.minimap .mini-colony{fill:var(--mini-color);stroke:#e7f8ff;stroke-width:.2}.minimap span { position:absolute;right:7px;bottom:4px;color:#7fb5d1;font-size:9px }
  .map-legend { position:absolute;top:12px;left:14px;max-width:calc(100% - 28px);display:flex;align-items:center;gap:.85rem;padding:.5rem .7rem;background:rgba(2,10,18,.86);border:1px solid rgba(65,159,210,.22);color:#7893a5;font-size:.68rem;overflow-x:auto;white-space:nowrap }
  .map-legend span::before { content:'';display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:.35rem }.friendly::before { background:#43c9ff;box-shadow:0 0 8px #43c9ff }.neutral::before { background:#dbefff }.hostile-dot::before { background:#ff645d;box-shadow:0 0 8px #ff645d }
  .legend-entry{display:inline-flex;align-items:center;color:#9cb2c0}.legend-entry::before{display:none!important}.legend-entry i{width:8px;height:8px;border-radius:50%;background:var(--legend-color);box-shadow:0 0 8px var(--legend-color);margin-right:.35rem}.unclaimed-entry i{background:#dcecff;box-shadow:none}.legend-hint{margin-left:.25rem;color:#5f798b}.legend-hint::before{display:none!important}.legend-hint b{color:#8eabba;font-weight:500}
  .planning-hint{position:absolute;top:54px;left:14px;display:flex;align-items:center;gap:.45rem;padding:.45rem .65rem;border:1px solid rgba(255,208,92,.45);background:rgba(49,35,5,.9);color:#ffd76d;font-size:.68rem;pointer-events:none}
  .empty-universe{position:absolute;inset:0;display:grid;place-content:center;justify-items:center;gap:.4rem;padding:2rem;text-align:center;background:rgba(2,8,14,.82)}.empty-universe strong{color:#e7f8ff;text-transform:uppercase;letter-spacing:.08em}.empty-universe span{max-width:520px;color:#7792a4;font-size:.75rem;line-height:1.5}
  @keyframes pulse { 0%,100% { r:21;opacity:.85 } 50% { r:29;opacity:.12 } }
  @keyframes destinationPulse { 0%,100% { opacity:.55 } 50% { opacity:1 } }
  @media (max-width:760px) { .galaxy-map{min-height:420px}.minimap{width:135px;height:72px}.map-controls{bottom:96px}.map-legend{left:8px;right:8px;max-width:none}.legend-hint{display:none}.planning-hint{left:8px;top:50px} }
</style>
