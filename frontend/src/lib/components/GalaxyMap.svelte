<script lang="ts">
  import Icon from './Icon.svelte';
  import type { RouteLink, StarSystem } from '../types';

  export let systems: StarSystem[] = [];
  export let routes: RouteLink[] = [];
  export let plannedRoutes: RouteLink[] = [];
  export let selectedId = '';
  export let liveMode = false;
  export let onSelect: (system: StarSystem) => void;

  $: byId = new Map(systems.map((system) => [system.id, system]));
  let zoom = 1;
  let panX = 0;
  let panY = 0;
  let dragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let startPanX = 0;
  let startPanY = 0;
  let moved = false;

  const ownerColor: Record<string, string> = {
    player: '#47c8ff', neutral: '#dcecff', crimson: '#ff675f', violet: '#ce72ff', amber: '#ffbd48'
  };

  function starPoint(id: string): { x: number; y: number } {
    const system = byId.get(id);
    return { x: (system?.x ?? 0) * 10, y: (system?.y ?? 0) * 6.2 };
  }

  function handleWheel(event: WheelEvent): void {
    event.preventDefault();
    const next = Math.min(2.2, Math.max(.72, zoom + (event.deltaY < 0 ? .12 : -.12)));
    zoom = Number(next.toFixed(2));
  }

  function pointerDown(event: PointerEvent): void {
    if (event.button !== 0) return;
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

  function resetView(): void {
    zoom = 1;
    panX = 0;
    panY = 0;
  }
</script>

<div class="galaxy-map" class:dragging>
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
        <g
          class="system"
          class:selected={selectedId === system.id}
          style={`--system-color:${ownerColor[system.owner]}`}
          transform={`translate(${x} ${y})`}
          role="button"
          tabindex="0"
          aria-label={`Select ${system.name}`}
          onclick={(event) => selectSystem(event, system)}
          onkeydown={(event) => selectSystem(event, system)}
        >
          {#if selectedId === system.id}
            <circle class="selection-ring outer" r="35" /><circle class="selection-ring middle" r="25" /><circle class="selection-ring pulse" r="17" />
          {/if}
          <circle class="star-glow" r={system.isCapital ? 17 : 12} />
          <circle class="star" r={system.isCapital ? 5.5 : 4} />
          {#if system.isCapital}<path class="capital" d="M-8-15 0-23 8-15 5-8-5-8Z" />{/if}
          {#if system.fleets.length > 0}<path class="fleet-marker" d="M14 -12 23-8 16-4 18 0 11-4Z" />{/if}
          <text class="system-label" y="20" text-anchor="middle">{system.name.toUpperCase()}</text>
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
    <svg viewBox="0 0 100 70"><path class="mini player" d="M10 15Q38 3 58 23T48 58Q20 64 7 42Z"/><path class="mini crimson" d="M38 2Q68-2 72 17Q59 25 43 16Z"/><path class="mini violet" d="M62 12Q96 11 99 42Q88 67 61 55Z"/><path class="mini amber" d="M4 45Q26 39 36 67Q11 76 1 58Z"/><rect x={Math.max(2, 30 - panX / 25)} y={Math.max(2, 20 - panY / 25)} width={45 / zoom} height={32 / zoom}/></svg>
    <span>{Math.round(zoom * 100)}%</span>
  </div>

  {#if systems.length === 0}
    <div class="empty-universe"><strong>No server galaxy</strong><span>This game predates the 0.5.1 universe generator. Create a new game to test live galaxy gameplay.</span></div>
  {/if}

  <div class="map-legend"><span class="friendly">{liveMode ? 'Yours' : 'Dominion'}</span><span class="neutral">Unclaimed</span><span class="hostile-dot">{liveMode ? 'Other player' : 'Hostile'}</span></div>
</div>

<style>
  .galaxy-map { position: relative; width: 100%; height: 100%; min-height: 480px; overflow: hidden; background: #02070e; user-select: none; }
  .galaxy-map svg { display: block; width: 100%; height: 100%; cursor: grab; touch-action: none; }
  .galaxy-map.dragging svg { cursor: grabbing; }
  .territory { fill-opacity: .035; stroke-width: 1.3; stroke-dasharray: 5 4; }
  .territory.player { fill: #37bfff; stroke: #37bfff; }.territory.crimson { fill: #ff544f; stroke: #ff544f; }.territory.violet { fill: #bd55ed; stroke: #bd55ed; }.territory.amber { fill: #e7a72c; stroke: #e7a72c; }
  .route { stroke: #5d8ba8; stroke-opacity: .34; stroke-width: 1.2; }.route.hostile { stroke: #fa6b62; stroke-dasharray: 4 5; }
  .planned-route { fill: none; stroke: #67d5ff; stroke-width: 2; stroke-dasharray: 7 6; opacity: .75; }
  .system { color: var(--system-color); cursor: pointer; outline: none; }
  .system:focus-visible .selection-ring, .system:hover .star-glow { opacity: 1; }
  .star-glow { fill: url(#starCore); color: var(--system-color); opacity: .7; filter: url(#glow); transition: .15s; }
  .star { fill: #fff; stroke: var(--system-color); stroke-width: 2; filter: url(#glow); }
  .system-label { fill: #b9cbd8; font-size: 10px; letter-spacing: .9px; paint-order: stroke; stroke: #02070e; stroke-width: 3px; stroke-linejoin: round; }
  .selected .system-label { fill: #edfaff; font-weight: 700; }
  .selection-ring { fill: none; stroke: #52ceff; stroke-width: 1.6; opacity: .9; }.selection-ring.middle { stroke-dasharray: 5 4; }.selection-ring.outer { opacity: .35; }.selection-ring.pulse { animation: pulse 2s infinite; }
  .capital { fill: #ffd36b; stroke: #fff0b9; stroke-width: .7; filter: url(#glow); }
  .fleet-marker { fill: #48caff; stroke: #d9f6ff; stroke-width: .7; }
  .scan-marker circle, .scan-marker path { fill: none; stroke: #54d1ff; stroke-width: 1; opacity: .65; }
  .map-controls { position: absolute; left: 14px; bottom: 116px; display: grid; gap: 5px; }
  .map-controls button { width: 38px; height: 38px; display: grid; place-items: center; color: #84d9ff; border: 1px solid rgba(66,176,231,.4); background: rgba(3,16,29,.9); cursor: pointer; }
  .map-controls button:hover { background: rgba(12,55,83,.95); border-color: #48c8ff; }
  .minimap { position: absolute; left: 14px; bottom: 14px; width: 180px; height: 90px; border: 1px solid rgba(69,178,232,.42); background: rgba(1,8,15,.9); padding: 5px; }
  .minimap svg { cursor: default; }.minimap .mini { stroke-width: .5; fill-opacity: .12; }.minimap .player { fill: #35c0ff; stroke: #35c0ff; }.minimap .crimson { fill: #ff5f58; stroke: #ff5f58; }.minimap .violet { fill: #c864ef; stroke: #c864ef; }.minimap .amber { fill: #f0ae39; stroke: #f0ae39; }.minimap rect { fill: none; stroke: #fff; stroke-width: 1; opacity: .7; }
  .minimap span { position: absolute; right: 7px; bottom: 4px; color: #7fb5d1; font-size: 9px; }
  .map-legend { position: absolute; top: 12px; left: 14px; display: flex; gap: .8rem; padding: .5rem .7rem; background: rgba(2,10,18,.72); border: 1px solid rgba(65,159,210,.18); color: #7893a5; font-size: .68rem; }
  .empty-universe { position:absolute; inset:0; display:grid; place-content:center; gap:.5rem; padding:2rem; text-align:center; pointer-events:none; background:radial-gradient(circle at 50% 45%,rgba(21,88,121,.16),transparent 30%); }.empty-universe strong{color:#dff6ff;font-size:1rem;letter-spacing:.08em;text-transform:uppercase}.empty-universe span{max-width:520px;color:#7894a7;font-size:.75rem;line-height:1.55}
  .map-legend span::before { content: ''; display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: .35rem; }.friendly::before { background:#43c9ff; box-shadow:0 0 8px #43c9ff }.neutral::before { background:#dbefff }.hostile-dot::before { background:#ff645d; box-shadow:0 0 8px #ff645d }
  @keyframes pulse { 0%,100% { r:17; opacity:.8 } 50% { r:24; opacity:.12 } }
  @media (max-width: 760px) { .galaxy-map { min-height: 420px; }.minimap { width: 135px; height: 72px; }.map-controls { bottom: 96px; }.map-legend { display:none; } }
</style>
