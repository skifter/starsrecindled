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
  export let sensorSystemIds: string[] = [];
  export let liveMode = false;
  export let onSelect: (system: StarSystem) => void;
  export let onSelectFleet: (fleet: FleetSummary, system: StarSystem) => void = () => {};

  $: byId = new Map(systems.map((system) => [system.id, system]));
  $: playerIds = players.map((player) => player.id);
  $: sensorSet = new Set(sensorSystemIds);
  $: adjacency = buildAdjacency();
  $: empireTerritories = buildEmpireTerritories();
  $: sensorTerritories = buildSensorTerritories();
  $: visibleKnownCount = systems.filter((system) => system.visibilityState !== 'explored').length;
  $: memorySystemCount = systems.filter((system) => system.visibilityState === 'explored').length;
  let showSensorLayer = false;
  let zoom = 1;
  let panX = 0;
  let panY = 0;
  let dragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let startPanX = 0;
  let startPanY = 0;
  let moved = false;

  type Point = { x: number; y: number };
  type EmpireTerritory = {
    ownerPlayerId: number;
    owner: Owner;
    memory: boolean;
    mixedIntel: boolean;
    path: string;
    systemIds: string[];
  };

  type SensorTerritory = {
    path: string;
    systemIds: string[];
  };

  function buildAdjacency(): Map<string, string[]> {
    const map = new Map<string, string[]>();
    for (const system of systems) map.set(system.id, []);
    for (const route of routes) {
      if (!map.has(route.from) || !map.has(route.to)) continue;
      map.get(route.from)?.push(route.to);
      map.get(route.to)?.push(route.from);
    }
    return map;
  }

  function connectedComponents(systemIds: string[]): string[][] {
    const allowed = new Set(systemIds);
    const remaining = new Set(systemIds);
    const components: string[][] = [];

    while (remaining.size > 0) {
      const first = remaining.values().next().value as string;
      const queue = [first];
      const component: string[] = [];
      remaining.delete(first);

      while (queue.length > 0) {
        const id = queue.shift() as string;
        component.push(id);
        for (const neighbour of adjacency.get(id) ?? []) {
          if (!allowed.has(neighbour) || !remaining.has(neighbour)) continue;
          remaining.delete(neighbour);
          queue.push(neighbour);
        }
      }

      components.push(component);
    }

    return components;
  }

  function ownedColonyComponents(ownerPlayerId: number): string[][] {
    const colonyIds = systems
      .filter((system) => system.ownerPlayerId === ownerPlayerId)
      .map((system) => system.id);
    return connectedComponents(colonyIds);
  }

  function sensorCoverageComponents(): string[][] {
    return connectedComponents([...sensorSet].filter((id) => byId.has(id)));
  }

  function cross(o: Point, a: Point, b: Point): number {
    return (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);
  }

  function convexHull(points: Point[]): Point[] {
    if (points.length <= 2) return [...points];
    const sorted = [...points].sort((a, b) => a.x === b.x ? a.y - b.y : a.x - b.x);
    const lower: Point[] = [];
    for (const point of sorted) {
      while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], point) <= 0) lower.pop();
      lower.push(point);
    }
    const upper: Point[] = [];
    for (const point of [...sorted].reverse()) {
      while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], point) <= 0) upper.pop();
      upper.push(point);
    }
    lower.pop();
    upper.pop();
    return [...lower, ...upper];
  }

  function territoryPath(systemIds: string[]): string {
    const centers = systemIds
      .map((id) => byId.get(id))
      .filter((system): system is StarSystem => Boolean(system))
      .map((system) => ({ x: system.x * 10, y: system.y * 6.2 }));
    if (centers.length === 0) return '';

    // Buffer only the actual colonies before taking the hull. Empire borders
    // therefore represent ownership, never neutral systems that happen to be
    // inside sensor range. Two or more directly connected colonies form one
    // continuous border, as in classic Stars!-style empire maps.
    const buffered: Point[] = [];
    const radius = centers.length === 1 ? 35 : 39;
    for (const center of centers) {
      for (let i = 0; i < 12; i += 1) {
        const angle = (Math.PI * 2 * i) / 12;
        buffered.push({
          x: center.x + Math.cos(angle) * radius,
          y: center.y + Math.sin(angle) * radius,
        });
      }
    }

    const hull = convexHull(buffered);
    if (hull.length === 0) return '';
    return `M ${hull.map((point) => `${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' L ')} Z`;
  }

  function buildEmpireTerritories(): EmpireTerritory[] {
    const ownerIds = [...new Set(
      systems
        .map((system) => system.ownerPlayerId)
        .filter((id): id is number => typeof id === 'number' && id > 0)
    )];
    const territories: EmpireTerritory[] = [];

    for (const ownerPlayerId of ownerIds) {
      for (const component of ownedColonyComponents(ownerPlayerId)) {
        const colonies = component
          .map((id) => byId.get(id))
          .filter((system): system is StarSystem => Boolean(system));
        if (colonies.length === 0) continue;
        const path = territoryPath(component);
        if (!path) continue;
        const exploredCount = colonies.filter((system) => system.visibilityState === 'explored').length;
        territories.push({
          ownerPlayerId,
          owner: ownerForPlayerId(ownerPlayerId, playerIds),
          memory: exploredCount === colonies.length,
          mixedIntel: exploredCount > 0 && exploredCount < colonies.length,
          path,
          systemIds: component,
        });
      }
    }

    return territories;
  }

  function buildSensorTerritories(): SensorTerritory[] {
    return sensorCoverageComponents()
      .map((component) => ({ systemIds: component, path: territoryPath(component) }))
      .filter((territory) => territory.path !== '');
  }

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
    if (system.ownerPlayerId !== currentPlayerId || system.visibilityState === 'explored') return 0;
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
    role="application"
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
      <filter id="territoryBlur"><feGaussianBlur stdDeviation="15"/></filter>
      <filter id="fogHoleBlur"><feGaussianBlur stdDeviation="24"/></filter>
      <pattern id="starDust" width="90" height="72" patternUnits="userSpaceOnUse">
        <circle cx="8" cy="12" r=".8" fill="#d7efff" opacity=".55"/><circle cx="55" cy="35" r=".55" fill="#fff" opacity=".42"/><circle cx="83" cy="64" r=".7" fill="#9bcfff" opacity=".46"/><circle cx="30" cy="59" r=".4" fill="#fff" opacity=".36"/>
      </pattern>
      <mask id="liveFogMask" maskUnits="userSpaceOnUse" x="-1300" y="-1000" width="3600" height="2700">
        <rect x="-1300" y="-1000" width="3600" height="2700" fill="white" />
        {#each systems.filter((system) => sensorSet.has(system.id)) as system}
          <circle cx={system.x * 10} cy={system.y * 6.2} r="92" fill="black" filter="url(#fogHoleBlur)" />
        {/each}
      </mask>
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
      {:else}
        {#each empireTerritories as territory}
          <path
            class="empire-territory-fill"
            class:memory={territory.memory}
            class:mixed-intel={territory.mixedIntel}
            style={`--territory-color:${OWNER_COLORS[territory.owner]}`}
            d={territory.path}
          />
        {/each}
      {/if}

      {#if liveMode}
        <!-- Fog darkens unexplored/remembered space, but stays behind system labels
             so LAST SEEN intelligence remains readable. -->
        <rect class="fog-layer" x="-1300" y="-1000" width="3600" height="2700" mask="url(#liveFogMask)" />
      {/if}

      {#if liveMode && showSensorLayer}
        {#each sensorTerritories as territory}
          <path class="sensor-coverage-fill" style={`--sensor-color:${OWNER_COLORS[playerOwner(currentPlayerId)]}`} d={territory.path} />
          <path class="sensor-coverage-border" style={`--sensor-color:${OWNER_COLORS[playerOwner(currentPlayerId)]}`} d={territory.path} />
        {/each}
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
          class:explored={system.visibilityState === 'explored'}
          class:selected={selectedId === system.id}
          class:valid-destination={validDestination}
          class:invalid-destination={invalidDestination}
          style={`--system-color:${OWNER_COLORS[system.owner]}`}
          transform={`translate(${x} ${y})`}
          role="button"
          tabindex="0"
          aria-label={`Select ${system.name}${system.visibilityState === 'explored' ? `, last seen turn ${system.lastSeenTurn ?? '?'}` : ''}`}
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
          {#if selectedId === system.id}
            <text class:stale={system.visibilityState === 'explored'} class="selection-intel" y="41" text-anchor="middle">
              {system.visibilityState === 'explored' ? `LAST SEEN · T${system.lastSeenTurn ?? '?'}` : `VISIBLE NOW · T${system.lastSeenTurn ?? '?'}`}
            </text>
          {:else if system.visibilityState === 'explored'}
            <text class="last-seen" y="41" text-anchor="middle">LAST SEEN T{system.lastSeenTurn ?? '?'}</text>
          {/if}
        </g>
      {/each}

      {#if !liveMode}<g class="scan-marker" transform="translate(592 370)"><circle r="8"/><path d="M-15 0h30M0-15v30"/></g>{/if}

      {#if liveMode}
        {#each empireTerritories as territory}
          <path
            class="empire-territory-border"
            class:memory={territory.memory}
            class:mixed-intel={territory.mixedIntel}
            class:own={territory.ownerPlayerId === currentPlayerId}
            style={`--territory-color:${OWNER_COLORS[territory.owner]}`}
            d={territory.path}
          />
        {/each}
      {/if}
    </g>
  </svg>

  <div class="map-controls">
    <button aria-label="Center map" onclick={resetView}><Icon name="target" /></button>
    <button class:active={showSensorLayer} aria-label="Toggle sensor coverage" title="Toggle sensor coverage" onclick={() => (showSensorLayer = !showSensorLayer)}><Icon name="layers" /></button>
    <button aria-label="Zoom in" onclick={() => (zoom = Math.min(2.2, zoom + .15))}><Icon name="plus" /></button>
    <button aria-label="Zoom out" onclick={() => (zoom = Math.max(.72, zoom - .15))}><Icon name="minus" /></button>
  </div>

  {#if liveMode && showSensorLayer}
    <div class="sensor-layer-status">
      <strong>Sensor coverage</strong>
      <span>{sensorSet.size} systems in range · {visibleKnownCount} visible now · {memorySystemCount} last known</span>
      <small>Empire borders show ownership. This overlay shows your current scan coverage.</small>
    </div>
  {/if}

  <div class="minimap" aria-hidden="true">
    <svg viewBox="0 0 100 70">
      <defs>
        <filter id="miniTerritoryBlur"><feGaussianBlur stdDeviation="1.8"/></filter>
        <filter id="miniFogHoleBlur"><feGaussianBlur stdDeviation="2.4"/></filter>
        <mask id="miniFogMask" maskUnits="userSpaceOnUse" x="-10" y="-10" width="120" height="90">
          <rect x="-10" y="-10" width="120" height="90" fill="white"/>
          {#each systems.filter((system) => sensorSet.has(system.id)) as system}
            <circle cx={system.x} cy={system.y * .62} r="9" fill="black" filter="url(#miniFogHoleBlur)"/>
          {/each}
        </mask>
      </defs>
      {#if liveMode}
        {#if showSensorLayer}
          {#each sensorTerritories as territory}
            <path class="mini-sensor-territory" style={`--mini-color:${OWNER_COLORS[playerOwner(currentPlayerId)]}`} d={territory.path} transform="scale(.1)"/>
          {/each}
        {/if}
        {#each empireTerritories as territory}
          <path class="mini-territory-fill" class:memory={territory.memory} style={`--mini-color:${OWNER_COLORS[territory.owner]}`} d={territory.path} transform="scale(.1)"/>
        {/each}
        {#each systems.filter((system) => system.ownerPlayerId !== null) as system}
          {@const miniRange = sensorRange(system)}
          {#if miniRange > 0}<circle class="mini-sensor" style={`--mini-color:${OWNER_COLORS[system.owner]}`} cx={system.x} cy={system.y * .62} r={4 + miniRange * 2.4}/>{/if}
          <circle class="mini-colony" class:memory={system.visibilityState === 'explored'} style={`--mini-color:${OWNER_COLORS[system.owner]}`} cx={system.x} cy={system.y * .62} r={system.isCapital ? 1.8 : 1.25}/>
        {/each}
        <rect class="mini-fog" x="-10" y="-10" width="120" height="90" mask="url(#miniFogMask)"/>
        {#each empireTerritories as territory}
          <path class="mini-territory-border" class:memory={territory.memory} style={`--mini-color:${OWNER_COLORS[territory.owner]}`} d={territory.path} transform="scale(.1)"/>
        {/each}
      {:else}
        <path class="mini player" d="M10 15Q38 3 58 23T48 58Q20 64 7 42Z"/><path class="mini crimson" d="M38 2Q68-2 72 17Q59 25 43 16Z"/><path class="mini violet" d="M62 12Q96 11 99 42Q88 67 61 55Z"/><path class="mini amber" d="M4 45Q26 39 36 67Q11 76 1 58Z"/>
      {/if}
      <rect class="viewport" x={Math.max(2, 30 - panX / 25)} y={Math.max(2, 20 - panY / 25)} width={45 / zoom} height={32 / zoom}/>
    </svg>
    <span>{Math.round(zoom * 100)}%</span>
  </div>

  {#if systems.length === 0}
    <div class="empty-universe"><strong>Unexplored space</strong><span>No star systems are currently known in this region.</span></div>
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
      <span class="legend-hint"><b>border</b> owned connected colonies · <b>layers</b> sensor coverage · <b>faded</b> last known</span>
    {:else}
      <span class="friendly">Dominion</span><span class="neutral">Unclaimed</span><span class="hostile-dot">Hostile</span>
    {/if}
  </div>
</div>

<style>
  .galaxy-map { position:relative;width:100%;height:100%;min-height:480px;overflow:hidden;background:#02070e;user-select:none }
  .galaxy-map>svg { display:block;width:100%;height:100%;cursor:grab;touch-action:none }
  .galaxy-map.dragging>svg { cursor:grabbing }
  .territory { fill-opacity:.035;stroke-width:1.3;stroke-dasharray:5 4 }
  .territory.player { fill:#37bfff;stroke:#37bfff }.territory.crimson { fill:#ff544f;stroke:#ff544f }.territory.violet { fill:#bd55ed;stroke:#bd55ed }.territory.amber { fill:#e7a72c;stroke:#e7a72c }
  .empire-territory-fill{fill:var(--territory-color);fill-opacity:.032;stroke:none;filter:url(#territoryBlur);pointer-events:none}.empire-territory-fill.memory{fill-opacity:.012}.empire-territory-fill.mixed-intel{fill-opacity:.022}.empire-territory-border{fill:none;stroke:var(--territory-color);stroke-width:2;stroke-linejoin:round;stroke-linecap:round;stroke-opacity:.7;pointer-events:none;filter:drop-shadow(0 0 3px color-mix(in srgb,var(--territory-color) 30%,transparent))}.empire-territory-border.own{stroke-width:2.4;stroke-opacity:.88}.empire-territory-border.memory{stroke-opacity:.27;stroke-dasharray:7 6;filter:none}.empire-territory-border.mixed-intel{stroke-opacity:.46;stroke-dasharray:12 4}.sensor-coverage-fill{fill:var(--sensor-color);fill-opacity:.035;stroke:none;pointer-events:none}.sensor-coverage-border{fill:none;stroke:var(--sensor-color);stroke-width:1.15;stroke-dasharray:3 5;stroke-opacity:.48;pointer-events:none;filter:drop-shadow(0 0 3px color-mix(in srgb,var(--sensor-color) 24%,transparent))}
  .route { stroke:#5d8ba8;stroke-opacity:.34;stroke-width:1.2 }.route.hostile { stroke:#fa6b62;stroke-dasharray:4 5 }
  .planned-route { fill:none;stroke:#ffd05c;stroke-width:2.2;stroke-dasharray:7 5;opacity:.95;filter:drop-shadow(0 0 4px rgba(255,208,92,.45)) }
  .system { color:var(--system-color);cursor:pointer;outline:none;transition:opacity .15s,filter .15s }.system.explored{opacity:.64;filter:saturate(.42) blur(.18px)}
  .system.invalid-destination { opacity:.32 }.system.valid-destination { opacity:1;filter:none }
  .system:focus-visible .selection-ring,.system:hover .star-glow { opacity:1 }
  .star-glow { fill:url(#starCore);color:var(--system-color);opacity:.72;filter:url(#glow);transition:.15s }
  .star { fill:#fff;stroke:var(--system-color);stroke-width:2;filter:url(#glow) }.star.colonized{stroke-width:3}
  .sensor-orbit{fill:none;stroke:var(--system-color);stroke-width:1;opacity:.58;pointer-events:none;filter:drop-shadow(0 0 3px color-mix(in srgb,var(--system-color) 35%,transparent))}.sensor-orbit-2{stroke-dasharray:5 3;opacity:.47}.sensor-orbit-3{stroke-dasharray:2 4;opacity:.38}.ownership-orbit { fill:none;stroke:var(--system-color);stroke-width:2.4;opacity:.9 }.ownership-orbit.unclaimed{stroke:#dcecff;stroke-width:1.5;stroke-dasharray:3 3;opacity:.7}
  .system-label { fill:#b9cbd8;font-size:10px;letter-spacing:.9px;paint-order:stroke;stroke:#02070e;stroke-width:3px;stroke-linejoin:round }.system.explored .system-label{fill:#758b99}
  .system-status{fill:var(--system-color);font-size:6.7px;font-weight:700;letter-spacing:.65px;paint-order:stroke;stroke:#02070e;stroke-width:2.5px;stroke-linejoin:round}.last-seen,.selection-intel{fill:#91a5b3;font-size:5.7px;letter-spacing:.45px;paint-order:stroke;stroke:#02070e;stroke-width:2px;stroke-linejoin:round}.selection-intel{fill:#8de2ff;font-weight:700}.selection-intel.stale{fill:#ffd68a}
  .selected .system-label { fill:#edfaff;font-weight:700 }
  .selection-ring { fill:none;stroke:#ffd05c;stroke-width:1.8;opacity:.95 }.selection-ring.middle{stroke-dasharray:5 4}.selection-ring.outer{opacity:.42}.selection-ring.pulse{animation:pulse 2s infinite}
  .destination-ring{fill:rgba(255,208,92,.05);stroke:#ffd05c;stroke-width:2;stroke-dasharray:4 3;filter:drop-shadow(0 0 6px rgba(255,208,92,.5));animation:destinationPulse 1.4s ease-in-out infinite}
  .capital { fill:#ffd36b;stroke:#fff0b9;stroke-width:.7;filter:url(#glow) }
  .fleet-marker-group{cursor:default;color:var(--fleet-color);outline:none}.fleet-marker-group.own-fleet{cursor:pointer}.fleet-hit{fill:#02101a;stroke:var(--fleet-color);stroke-width:1;opacity:.88}.fleet-marker{fill:var(--fleet-color);stroke:#eafaff;stroke-width:.55;filter:drop-shadow(0 0 4px var(--fleet-color))}.fleet-marker-group:hover .fleet-hit,.fleet-marker-group.selected-fleet .fleet-hit{stroke:#ffd05c;stroke-width:2}.fleet-marker-group.planning-fleet .fleet-hit{stroke:#ffd05c;stroke-width:2.5;animation:destinationPulse 1.2s ease-in-out infinite}
  .fog-layer{fill:#01050a;fill-opacity:.78;pointer-events:none;filter:saturate(.55)}
  .scan-marker circle,.scan-marker path { fill:none;stroke:#54d1ff;stroke-width:1;opacity:.65 }
  .map-controls { position:absolute;left:14px;bottom:116px;display:grid;gap:5px }
  .map-controls button { width:38px;height:38px;display:grid;place-items:center;color:#84d9ff;border:1px solid rgba(66,176,231,.4);background:rgba(3,16,29,.9);cursor:pointer }
  .map-controls button:hover { background:rgba(12,55,83,.95);border-color:#48c8ff }.map-controls button.active{color:#ffd76d;border-color:rgba(255,208,92,.7);background:rgba(52,39,8,.94);box-shadow:0 0 10px rgba(255,208,92,.12)}
  .sensor-layer-status{position:absolute;left:62px;bottom:116px;display:grid;gap:2px;max-width:330px;padding:.48rem .65rem;border:1px solid rgba(255,208,92,.35);background:rgba(4,14,24,.92);pointer-events:none}.sensor-layer-status strong{color:#ffd76d;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em}.sensor-layer-status span{color:#a8c4d5;font-size:.64rem}.sensor-layer-status small{color:#607b8d;font-size:.58rem;line-height:1.35}.minimap { position:absolute;left:14px;bottom:14px;width:180px;height:90px;border:1px solid rgba(69,178,232,.42);background:rgba(1,8,15,.9);padding:5px }
  .minimap svg { display:block;width:100%;height:100%;cursor:default }.minimap .mini { stroke-width:.5;fill-opacity:.12 }.minimap .player { fill:#35c0ff;stroke:#35c0ff }.minimap .crimson { fill:#ff5f58;stroke:#ff5f58 }.minimap .violet { fill:#c864ef;stroke:#c864ef }.minimap .amber { fill:#f0ae39;stroke:#f0ae39 }.minimap .viewport { fill:none;stroke:#fff;stroke-width:1;opacity:.7 }
  .mini-sensor-territory{fill:var(--mini-color);fill-opacity:.04;stroke:var(--mini-color);stroke-width:2.3;stroke-dasharray:1.5 1.5;stroke-opacity:.45}.mini-territory-fill{fill:var(--mini-color);fill-opacity:.09;stroke:none;filter:url(#miniTerritoryBlur)}.mini-territory-fill.memory{fill-opacity:.035}.mini-territory-border{fill:none;stroke:var(--mini-color);stroke-width:5;stroke-linejoin:round;stroke-opacity:.7}.mini-territory-border.memory{stroke-opacity:.3;stroke-dasharray:2 2}.minimap .mini-sensor{fill:color-mix(in srgb,var(--mini-color) 8%,transparent);stroke:var(--mini-color);stroke-width:.35;stroke-dasharray:1.3 1.2;opacity:.65}.minimap .mini-colony{fill:var(--mini-color);stroke:#e7f8ff;stroke-width:.2}.minimap .mini-colony.memory{opacity:.45}.mini-fog{fill:#01050a;fill-opacity:.58}.minimap span { position:absolute;right:7px;bottom:4px;color:#7fb5d1;font-size:9px }
  .map-legend { position:absolute;top:12px;left:14px;max-width:calc(100% - 28px);display:flex;align-items:center;gap:.85rem;padding:.5rem .7rem;background:rgba(2,10,18,.86);border:1px solid rgba(65,159,210,.22);color:#7893a5;font-size:.68rem;overflow-x:auto;white-space:nowrap }
  .map-legend span::before { content:'';display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:.35rem }.friendly::before { background:#43c9ff;box-shadow:0 0 8px #43c9ff }.neutral::before { background:#dbefff }.hostile-dot::before { background:#ff645d;box-shadow:0 0 8px #ff645d }
  .legend-entry{display:inline-flex;align-items:center;color:#9cb2c0}.legend-entry::before{display:none!important}.legend-entry i{width:8px;height:8px;border-radius:50%;background:var(--legend-color);box-shadow:0 0 8px var(--legend-color);margin-right:.35rem}.unclaimed-entry i{background:#dcecff;box-shadow:none}.legend-hint{margin-left:.25rem;color:#5f798b}.legend-hint::before{display:none!important}.legend-hint b{color:#8eabba;font-weight:500}
  .planning-hint{position:absolute;top:54px;left:14px;display:flex;align-items:center;gap:.45rem;padding:.45rem .65rem;border:1px solid rgba(255,208,92,.45);background:rgba(49,35,5,.9);color:#ffd76d;font-size:.68rem;pointer-events:none}
  .empty-universe{position:absolute;inset:0;display:grid;place-content:center;justify-items:center;gap:.4rem;padding:2rem;text-align:center;background:rgba(2,8,14,.82)}.empty-universe strong{color:#e7f8ff;text-transform:uppercase;letter-spacing:.08em}.empty-universe span{max-width:520px;color:#7792a4;font-size:.75rem;line-height:1.5}
  @keyframes pulse { 0%,100% { r:21;opacity:.85 } 50% { r:29;opacity:.12 } }
  @keyframes destinationPulse { 0%,100% { opacity:.55 } 50% { opacity:1 } }
  @media (max-width:760px) { .galaxy-map{min-height:420px}.minimap{width:135px;height:72px}.map-controls{bottom:96px}.sensor-layer-status{left:54px;bottom:96px;max-width:250px}.sensor-layer-status small{display:none}.map-legend{left:8px;right:8px;max-width:none}.legend-hint{display:none}.planning-hint{left:8px;top:50px} }
</style>
