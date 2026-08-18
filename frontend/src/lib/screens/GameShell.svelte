<script lang="ts">
  import DetailPanel from '../components/DetailPanel.svelte';
  import GalaxyMap from '../components/GalaxyMap.svelte';
  import Icon from '../components/Icon.svelte';
  import Logo from '../components/Logo.svelte';
  import PlayersView from './PlayersView.svelte';
  import FleetsView from './FleetsView.svelte';
  import PlanetsView from './PlanetsView.svelte';
  import TurnReportView from './TurnReportView.svelte';
  import ResearchView from './ResearchView.svelte';
  import DesignsView from './DesignsView.svelte';
  import SectionViews from '../components/SectionViews.svelte';
  import { routes as demoRoutes, systems as demoSystems } from '../demo-data';
  import { mapLiveUniverse } from '../live-universe';
  import { OWNER_COLORS, ownerForPlayerId } from '../player-colors';
  import type { AccountGameAccess, AccountTurnStatus, ConnectionSettings, FleetSummary, GameSection, PlayerOrders, ProductionOrder, RouteLink, StarSystem } from '../types';

  export let connection: ConnectionSettings;
  export let game: AccountGameAccess | null = null;
  export let orders: PlayerOrders;
  export let status: AccountTurnStatus | null = null;
  export let busy = false;
  export let message = '';
  export let demoMode = false;
  export let onOrdersChange: (orders: PlayerOrders) => void;
  export let onSaveDraft: () => void;
  export let onSubmit: () => void;
  export let onReopen: () => void;
  export let onRefresh: () => void;
  export let onExit: () => void;

  const navigation: { id: GameSection; label: string; icon: string }[] = [
    { id: 'galaxy', label: 'Galaxy', icon: 'galaxy' },
    { id: 'planets', label: 'Planets', icon: 'planet' },
    { id: 'fleets', label: 'Fleets', icon: 'fleet' },
    { id: 'designs', label: 'Designs', icon: 'build' },
    { id: 'players', label: 'Players', icon: 'user' },
    { id: 'research', label: 'Research', icon: 'research' },
    { id: 'diplomacy', label: 'Diplomacy', icon: 'diplomacy' },
    { id: 'report', label: 'Turn report', icon: 'report' }
  ];

  const topResources = [
    { icon: 'industry', value: '12.4K', income: '+305' },
    { icon: 'research', value: '8.7K', income: '+218' },
    { icon: 'planet', value: '6.1K', income: '+142' },
    { icon: 'energy', value: '2.3K', income: '+67' }
  ];

  const PRODUCTION_COSTS: Record<string, number> = {
    'Scout Wing': 300,
    'Defense Grid': 250,
    'Orbital Factory': 400,
    'Deep Space Array': 350
  };

  let activeSection: GameSection = 'galaxy';
  let selectedSystem: StarSystem | null = demoSystems[0] ?? null;
  let selectedFleet: FleetSummary | null = null;
  let planningFleet: FleetSummary | null = null;
  let sidebarOpen = false;
  let rightPanelOpen = true;
  let showTechnical = false;
  let technicalText = '';
  let localNotice = '';

  $: serverTurnNumber = demoMode ? connection.turnNumber : (status?.turn.number ?? connection.turnNumber);
  $: serverTurnStatus = demoMode ? 'demo' : (status?.turn.status ?? 'loading');
  $: turnPlayers = demoMode ? [] : (status?.players ?? []);
  $: submittedCount = turnPlayers.filter((player) => player.submitted).length;
  $: playerCount = turnPlayers.length || game?.players.length || 0;
  $: pendingCount = Math.max(playerCount - submittedCount, 0);
  $: ownSubmitted = !demoMode && status?.you.submitted === true;
  $: editableTurn = demoMode || (!ownSubmitted && serverTurnStatus === 'open');
  $: liveUniverse = mapLiveUniverse(status?.state, connection.playerId, status?.players ?? []);
  $: gameSystems = demoMode ? demoSystems : liveUniverse.systems;
  $: gameRoutes = demoMode ? demoRoutes : liveUniverse.routes;
  $: gameYear = demoMode ? 2195 + serverTurnNumber : (status?.state?.year ?? (2400 + serverTurnNumber - 1));
  $: allLiveFleets = gameSystems.flatMap((system) => system.fleets);
  $: selectedFleetCurrent = selectedFleet
    ? (allLiveFleets.find((fleet) => fleet.id === selectedFleet?.id) ?? selectedFleet)
    : null;
  $: planningFleetCurrent = planningFleet
    ? (allLiveFleets.find((fleet) => fleet.id === planningFleet?.id) ?? planningFleet)
    : null;
  $: ownFleetInSelected = selectedSystem
    ? (selectedFleetCurrent?.systemId === selectedSystem.id && selectedFleetCurrent.ownerPlayerId === connection.playerId
      ? selectedFleetCurrent
      : selectedSystem.fleets.find((fleet) => fleet.ownerPlayerId === connection.playerId) ?? null)
    : null;
  $: colonizerInSelected = selectedSystem?.fleets.find((fleet) =>
    fleet.ownerPlayerId === connection.playerId && colonyCapacity(fleet) > 0
  ) ?? null;
  $: movementRange = demoMode ? 1 : Math.max(1, planningFleetCurrent?.movementRange ?? 1);
  $: validDestinationIds = planningFleetCurrent
    ? systemsWithinRange(planningFleetCurrent.systemId ?? '', movementRange)
    : [];
  $: researchProjectId = orders.research?.[0]?.technologyId ?? orders.research?.[0]?.field ?? status?.research?.activeTechnologyId ?? '';
  $: researchProjectName = status?.research_catalog?.find((technology) => technology.id === researchProjectId)?.name ?? 'Choose technology';
  $: playerIds = (status?.players ?? []).map((player) => player.id);
  $: currentPlayerOwner = ownerForPlayerId(connection.playerId, playerIds);
  $: currentPlayerColor = OWNER_COLORS[currentPlayerOwner];
  $: plannedRoutes = demoMode ? [] : (orders.fleets ?? []).flatMap((order): RouteLink[] => {
    if (order.action !== 'move' || !order.targetSystemId) return [];
    const source = gameSystems.find((system) => system.fleets.some((fleet) => fleet.id === order.fleetId));
    return source ? [{ from: source.id, to: order.targetSystemId, kind: 'planned' }] : [];
  });
  $: {
    const ownHomeSystem = gameSystems.find(
      (system) => system.ownerPlayerId === connection.playerId && system.isCapital
    ) ?? gameSystems.find(
      (system) => system.ownerPlayerId === connection.playerId
    ) ?? gameSystems[0] ?? null;

    const replacement = gameSystems.find(
      (system) => system.id === selectedSystem?.id
    ) ?? (demoMode ? (gameSystems[0] ?? null) : ownHomeSystem);

    if (replacement !== selectedSystem) selectedSystem = replacement;
  }
  $: turnStateLabel = busy
    ? 'SYNCING'
    : demoMode
      ? 'DEMO'
      : serverTurnStatus !== 'open'
        ? (serverTurnStatus === 'queued' ? 'PROCESSING' : serverTurnStatus.toUpperCase())
        : ownSubmitted
          ? (pendingCount > 0 ? `WAITING FOR ${pendingCount}` : 'SUBMITTED')
          : 'YOUR TURN';

  function selectSystem(system: StarSystem): void {
    if (planningFleet && !demoMode) {
      const sourceSystemId = planningFleet.systemId ?? '';
      if (system.id === sourceSystemId) {
        localNotice = 'Select a different system within movement range.';
        return;
      }
      const distance = routeDistance(sourceSystemId, system.id, movementRange);
      if (distance === null || distance > movementRange) {
        localNotice = `${system.name} is outside ${planningFleet.name}'s ${movementRange}-hop movement range.`;
        return;
      }

      const nextFleetOrders = (orders.fleets ?? []).filter((order) => order.fleetId !== planningFleet?.id);
      const plannedFleet = planningFleet;
      updateOrders(
        { ...orders, fleets: [...nextFleetOrders, { fleetId: plannedFleet.id, action: 'move', targetSystemId: system.id }] },
        `Waypoint set: ${plannedFleet.name} → ${system.name}. Save draft or submit the turn to keep the order.`
      );
      selectedFleet = plannedFleet;
      planningFleet = null;
    }

    selectedSystem = system;
    activeSection = 'galaxy';
    rightPanelOpen = true;
    showNotice(`${system.name} selected.`);
  }

  function showNotice(notice: string): void {
    localNotice = notice;
    window.setTimeout(() => { if (localNotice === notice) localNotice = ''; }, 3200);
  }

  function updateOrders(next: PlayerOrders, notice: string): void {
    onOrdersChange(next);
    showNotice(notice);
  }

  function routeDistance(from: string, to: string, maxDistance: number): number | null {
    if (!from || !to) return null;
    if (from === to) return 0;

    const adjacency = new Map<string, string[]>();
    for (const system of gameSystems) adjacency.set(system.id, []);
    for (const route of gameRoutes) {
      if (!adjacency.has(route.from) || !adjacency.has(route.to)) continue;
      adjacency.get(route.from)?.push(route.to);
      adjacency.get(route.to)?.push(route.from);
    }

    const visited = new Set<string>([from]);
    let frontier = [from];
    for (let distance = 1; distance <= Math.max(1, maxDistance); distance += 1) {
      const next: string[] = [];
      for (const systemId of frontier) {
        for (const neighbour of adjacency.get(systemId) ?? []) {
          if (visited.has(neighbour)) continue;
          if (neighbour === to) return distance;
          visited.add(neighbour);
          next.push(neighbour);
        }
      }
      if (next.length === 0) break;
      frontier = next;
    }

    return null;
  }

  function systemsWithinRange(from: string, maxDistance: number): string[] {
    if (!from) return [];
    return gameSystems
      .map((system) => system.id)
      .filter((systemId) => systemId !== from && routeDistance(from, systemId, maxDistance) !== null);
  }

  function colonyCapacity(fleet: FleetSummary): number {
    if (typeof fleet.colonizationCapacity === 'number') return fleet.colonizationCapacity;
    return fleet.role === 'Exploration fleet' ? 1 : 0;
  }

  function serverUniverseReady(): boolean {
    if (demoMode) return true;
    if (gameSystems.length > 0) return true;
    localNotice = 'This game has no server galaxy. Create a new game after the 0.5.1 deployment.';
    return false;
  }

  function productionCost(item: string, modelId = '', productionKind = '', sourceModelId = ''): number {
    if (modelId) {
      const design = status?.model_catalog?.designs.find((entry) => entry.id === modelId);
      if (design) return design.industryCost;
      const installation = status?.model_catalog?.installations.find((entry) => entry.id === modelId);
      if (installation) {
        if (productionKind === 'upgrade') {
          if (installation.upgradeFrom !== sourceModelId) return 0;
          return installation.upgradeCost ?? installation.stats.industryCost ?? 0;
        }
        return installation.stats.industryCost ?? 0;
      }
    }
    return PRODUCTION_COSTS[item] ?? 0;
  }

  function preferredInstallation(family: string) {
    return [...(status?.model_catalog?.installations ?? [])]
      .filter((model) => model.family === family && model.unlocked)
      .sort((a, b) => b.version - a.version)[0] ?? null;
  }

  function currentScoutDesign() {
    return status?.model_catalog?.designs.find((design) => design.current)
      ?? status?.model_catalog?.designs[0]
      ?? null;
  }

  function hasInstallation(system: StarSystem | null, family: string): boolean {
    return (system?.installations ?? []).some((installation) => installation.family === family);
  }

  function projectedIndustry(system: StarSystem): number {
    const industry = system.resources.find((resource) => resource.id === 'industry');
    if (!industry) return 0;
    return industry.value + industry.income;
  }

  function reservedIndustry(systemId: string): number {
    return (orders.production ?? [])
      .filter((order) => order.systemId === systemId)
      .reduce((sum, order) => sum + productionCost(order.item, order.modelId ?? '', order.productionKind ?? '', order.sourceModelId ?? '') * Math.max(1, order.quantity), 0);
  }

  function addProductionForSystem(system: StarSystem, item: string, modelId = '', metadata: Partial<ProductionOrder> = {}): void {
    const productionKind = metadata.productionKind ?? '';
    const sourceModelId = metadata.sourceModelId ?? '';

    if (!demoMode) {
      if (!editableTurn) {
        localNotice = 'Reopen the turn before changing production orders.';
        return;
      }
      if (system.ownerPlayerId !== connection.playerId) {
        localNotice = 'Production can only be ordered in one of your colonies.';
        return;
      }

      const cost = productionCost(item, modelId, productionKind, sourceModelId);
      const remaining = projectedIndustry(system) - reservedIndustry(system.id);
      if (cost > 0 && remaining < cost) {
        localNotice = `${system.name} needs ${cost.toLocaleString('en-US')} industry for ${item}; ${Math.max(0, remaining).toLocaleString('en-US')} remains after queued work.`;
        return;
      }
    }

    const existing = (orders.production ?? []).find(
      (order) => order.systemId === system.id
        && order.item === item
        && (order.modelId ?? '') === modelId
        && (order.productionKind ?? '') === productionKind
        && (order.sourceModelId ?? '') === sourceModelId
    );

    if (existing && productionKind === 'upgrade') {
      localNotice = `${item} is already queued at ${system.name}.`;
      return;
    }

    const nextProduction: ProductionOrder[] = existing
      ? (orders.production ?? []).map((order) =>
          order === existing ? { ...order, quantity: order.quantity + 1 } : order
        )
      : [...(orders.production ?? []), {
          systemId: system.id,
          item,
          quantity: 1,
          ...(modelId ? { modelId } : {}),
          ...metadata
        }];

    updateOrders(
      { ...orders, production: nextProduction },
      `${item} queued at ${system.name}.`
    );
  }

  function addInstallationUpgrade(system: StarSystem, targetModelId: string, sourceModelId: string): void {
    const target = status?.model_catalog?.installations.find((model) => model.id === targetModelId);
    const source = (system.installations ?? []).find((installation) => installation.modelId === sourceModelId);
    if (!target || !source || target.upgradeFrom !== sourceModelId) {
      localNotice = 'That installation upgrade is no longer available. Refresh the turn state.';
      return;
    }

    addProductionForSystem(system, `Upgrade to ${target.name}`, target.id, {
      productionKind: 'upgrade',
      sourceModelId,
      sourceModelVersion: source.version,
      modelName: target.name,
      modelVersion: target.version,
      upgradeTurns: target.upgradeTurns ?? 2
    });
  }

  function addProduction(item: string, modelId = ''): void {
    const system = selectedSystem;
    if (!system) return;
    if (!modelId && item === 'Scout Wing') {
      const design = currentScoutDesign();
      addProductionForSystem(system, design?.name ?? item, design?.id ?? '');
      return;
    }
    if (!modelId && item === 'Orbital Factory') {
      const model = preferredInstallation('orbital_factory');
      addProductionForSystem(system, model?.name ?? item, model?.id ?? '');
      return;
    }
    addProductionForSystem(system, item, modelId);
  }

  function removeProduction(systemId: string, item: string): void {
    if (!editableTurn && !demoMode) {
      localNotice = 'Reopen the turn before changing production orders.';
      return;
    }

    let removed = false;
    const nextProduction = (orders.production ?? []).flatMap((order) => {
      if (!removed && order.systemId === systemId && order.item === item) {
        removed = true;
        return order.quantity > 1 ? [{ ...order, quantity: order.quantity - 1 }] : [];
      }
      return [order];
    });

    if (!removed) return;
    const systemName = gameSystems.find((system) => system.id === systemId)?.name ?? systemId;
    updateOrders({ ...orders, production: nextProduction }, `${item} removed from ${systemName}.`);
  }

  function removeProductionFromSelected(item: string): void {
    const system = selectedSystem;
    if (!system) return;
    removeProduction(system.id, item);
  }

  function openPlanet(system: StarSystem): void {
    planningFleet = null;
    selectedSystem = system;
    selectedFleet = null;
    activeSection = 'galaxy';
    rightPanelOpen = true;
    sidebarOpen = false;
    showNotice(`${system.name} selected.`);
  }

  function selectFleet(fleet: FleetSummary, system: StarSystem, openGalaxy = false): void {
    selectedFleet = fleet;
    selectedSystem = system;
    rightPanelOpen = true;
    if (openGalaxy) activeSection = 'galaxy';

    if (!demoMode && fleet.ownerPlayerId !== connection.playerId) {
      localNotice = `${fleet.name} belongs to ${fleet.ownerLabel ?? 'another player'}.`;
      return;
    }

    localNotice = `${fleet.name} selected · ${fleet.ships.toLocaleString('en-US')} ships at ${system.name}.`;
  }

  function selectFleetFromDetail(fleet: FleetSummary): void {
    const system = selectedSystem;
    if (!system) return;
    selectFleet(fleet, system);
  }

  function beginWaypointFromDetail(fleet: FleetSummary): void {
    const system = selectedSystem;
    if (!system) return;
    beginWaypointForFleet(fleet, system);
  }

  function beginWaypointForFleet(fleet: FleetSummary, system: StarSystem): void {
    if (!serverUniverseReady()) return;
    if (!demoMode && !editableTurn) {
      localNotice = 'Reopen the turn before changing fleet orders.';
      return;
    }
    if (!demoMode && fleet.ownerPlayerId !== connection.playerId) {
      localNotice = 'You can only set waypoints for your own fleets.';
      return;
    }

    selectedFleet = fleet;
    selectedSystem = system;
    planningFleet = fleet;
    activeSection = 'galaxy';
    rightPanelOpen = true;
    const fleetRange = demoMode ? 1 : Math.max(1, fleet.movementRange ?? 1);
    localNotice = `Planning ${fleet.name}: choose one of the highlighted systems within ${fleetRange} hop${fleetRange === 1 ? '' : 's'}.`;
  }

  function addWaypoint(action: 'move' | 'colonize' = 'move'): void {
    if (!selectedSystem || !serverUniverseReady()) return;

    if (demoMode) {
      const fleetId = selectedSystem.fleets[0]?.id ?? 'fleet-1';
      updateOrders({ ...orders, fleets: [...(orders.fleets ?? []), { fleetId, action, targetSystemId: selectedSystem.id }] }, `${action === 'colonize' ? 'Colonization' : 'Waypoint'} order added for ${selectedSystem.name}`);
      return;
    }

    if (!editableTurn) {
      localNotice = 'Reopen the turn before changing fleet orders.';
      return;
    }

    if (action === 'colonize') {
      if (selectedSystem.ownerPlayerId !== null) {
        localNotice = 'Select an unclaimed system to establish a colony.';
        return;
      }

      const fleet = colonizerInSelected;
      if (!fleet) {
        localNotice = 'A fleet with an unused colony module must be present in the unclaimed system.';
        return;
      }

      const nextFleetOrders = (orders.fleets ?? []).filter((order) => order.fleetId !== fleet.id);
      updateOrders(
        { ...orders, fleets: [...nextFleetOrders, { fleetId: fleet.id, action: 'colonize', targetSystemId: selectedSystem.id }] },
        `${fleet.name} will establish a colony in ${selectedSystem.name} when the turn is processed.`
      );
      planningFleet = null;
      return;
    }

    const fleet = selectedFleet?.systemId === selectedSystem.id && selectedFleet.ownerPlayerId === connection.playerId
      ? selectedFleet
      : selectedSystem.fleets.find((entry) => entry.ownerPlayerId === connection.playerId);
    if (!fleet) {
      localNotice = 'Select one of your fleets first.';
      return;
    }

    beginWaypointForFleet(fleet, selectedSystem);
  }

  function prioritizeResearch(technologyId: string): void {
    if (!demoMode && !editableTurn) {
      localNotice = 'Reopen the turn before changing research.';
      return;
    }

    if (!demoMode) {
      const technology = status?.research_catalog?.find((candidate) => candidate.id === technologyId);
      if (!technology) {
        localNotice = 'The selected research technology is not available.';
        return;
      }
      const completed = new Set(status?.research?.completed ?? []);
      if (completed.has(technologyId) || !technology.prerequisites.every((id) => completed.has(id))) {
        localNotice = `${technology.name} is completed or still locked.`;
        return;
      }
      updateOrders(
        { ...orders, research: [{ technologyId, field: technologyId, allocation: 100 }] },
        `${technology.name} selected for research.`
      );
      return;
    }

    updateOrders({ ...orders, research: [{ technologyId, field: technologyId, allocation: 100 }] }, `${technologyId} research prioritized`);
  }

  function openTechnical(): void {
    technicalText = JSON.stringify(orders, null, 2);
    showTechnical = true;
  }

  function applyTechnical(): void {
    try {
      const parsed = JSON.parse(technicalText) as PlayerOrders;
      if (!Array.isArray(parsed.fleets) || !Array.isArray(parsed.production)) throw new Error('Orders must contain fleets and production arrays.');
      updateOrders(parsed, 'Technical order JSON applied');
      showTechnical = false;
    } catch (error) {
      localNotice = error instanceof Error ? error.message : String(error);
    }
  }
</script>

<section class="game-shell">
  <header class="topbar">
    <button class="mobile-menu" aria-label="Open navigation" onclick={() => (sidebarOpen = !sidebarOpen)}><Icon name="menu" /></button>
    <div class="top-logo"><Logo compact={true} subtitle={false}/></div>
    <div class="game-block"><Icon name="galaxy" size={18}/><span><strong>{game?.label ?? (demoMode ? 'Demonstration universe' : `Game ${connection.gameId}`)}</strong><small>Game {connection.gameId}</small></span></div>
    <div class="turn-block"><Icon name="calendar" size={18}/><span><strong>Year {gameYear}</strong><small>{demoMode ? `Demo turn ${serverTurnNumber}` : `Turn ${serverTurnNumber} · ${serverTurnStatus.toUpperCase()} · ${submittedCount}/${playerCount || '?'} submitted`}</small></span></div>
    <div class="empire-block"><i class="player-color" style={`--player-color:${demoMode ? '#47c8ff' : currentPlayerColor}`}></i><Icon name="shield" size={22}/><span><strong>{game?.playerLabel ?? (demoMode ? 'Demonstration player' : status?.you.name ?? 'Player')}</strong><small>{demoMode ? 'Demonstration player' : `Player ${connection.playerId}`}</small></span></div>
    {#if demoMode}
      <div class="resource-bar">
        {#each topResources as resource}<div><Icon name={resource.icon} size={17}/><span><strong>{resource.value}</strong><small>{resource.income}</small></span></div>{/each}
      </div>
    {:else}
      <div class="turn-progress">
        <span><strong>{submittedCount}/{playerCount || '?'}</strong><small>turns submitted</small></span>
        <span><strong>{pendingCount}</strong><small>players remaining</small></span>
      </div>
    {/if}
    <button class="turn-state" class:waiting={ownSubmitted} onclick={onRefresh}><span class:spinning={busy}></span><strong>{turnStateLabel}</strong></button>
    <button class="top-icon" aria-label="Technical orders" onclick={openTechnical}><Icon name="edit" /></button>
    <button class="top-icon" aria-label="Exit to menu" onclick={onExit}><Icon name="power" /></button>
  </header>

  <div class="game-grid" class:panel-closed={!rightPanelOpen || activeSection === 'players' || activeSection === 'planets' || activeSection === 'fleets' || activeSection === 'designs' || activeSection === 'research' || activeSection === 'report'}>
    <nav class="sidebar" class:open={sidebarOpen}>
      {#each navigation as item}
        <button class:active={activeSection === item.id} onclick={() => { activeSection = item.id; sidebarOpen = false; }}><Icon name={item.icon} size={25}/><span>{item.label}</span></button>
      {/each}
      <div class="sidebar-spacer"></div>
      <button onclick={openTechnical}><Icon name="edit" size={23}/><span>Orders JSON</span></button>
      <button onclick={onExit}><Icon name="power" size={23}/><span>Main menu</span></button>
    </nav>

    <main class="content-area">
      {#if activeSection === 'players'}
        <PlayersView {game} {status} {demoMode}/>
      {:else if activeSection === 'galaxy'}
        <GalaxyMap
          systems={gameSystems}
          routes={gameRoutes}
          {plannedRoutes}
          players={status?.players ?? []}
          currentPlayerId={connection.playerId}
          selectedId={selectedSystem?.id ?? ''}
          selectedFleetId={selectedFleetCurrent?.id ?? ''}
          planningFleetId={planningFleetCurrent?.id ?? ''}
          {validDestinationIds}
          sensorSystemIds={status?.visibility?.sensor_system_ids ?? []}
          liveMode={!demoMode}
          onSelect={selectSystem}
          onSelectFleet={(fleet, system) => beginWaypointForFleet(fleet, system)}
        />
      {:else if activeSection === 'planets' && !demoMode}
        <PlanetsView
          systems={gameSystems}
          currentPlayerId={connection.playerId}
          {orders}
          {editableTurn}
          modelCatalog={status?.model_catalog ?? null}
          ownerColor={currentPlayerColor}
          onLocate={openPlanet}
          onQueueBuild={addProductionForSystem}
          onQueueUpgrade={addInstallationUpgrade}
          onRemoveBuild={removeProduction}
        />
      {:else if activeSection === 'fleets' && !demoMode}
        <FleetsView
          systems={gameSystems}
          players={status?.players ?? []}
          currentPlayerId={connection.playerId}
          sensorSystemCount={status?.visibility?.sensor_system_ids.length ?? 0}
          selectedFleetId={selectedFleetCurrent?.id ?? ''}
          {orders}
          {editableTurn}
          fuelEfficiencyPercent={status?.research?.modifiers.fuelEfficiencyPercent ?? 0}
          onLocate={(fleet, system) => selectFleet(fleet, system, true)}
          onPlanRoute={beginWaypointForFleet}
        />
      {:else if activeSection === 'designs' && !demoMode}
        <DesignsView catalog={status?.model_catalog ?? null}/>
      {:else if activeSection === 'research' && !demoMode}
        <ResearchView
          research={status?.research ?? null}
          catalog={status?.research_catalog ?? []}
          modelCatalog={status?.model_catalog ?? null}
          {orders}
          {editableTurn}
          onResearch={prioritizeResearch}
        />
      {:else if activeSection === 'report' && !demoMode}
        <TurnReportView report={status?.previous_report ?? null} systems={gameSystems} onOpenSystem={openPlanet} onOpenOrders={openTechnical} onOpenResearch={() => (activeSection = 'research')} onOpenDesigns={() => (activeSection = 'designs')}/>
      {:else if !demoMode}
        <section class="live-pending">
          <Icon name={navigation.find((item) => item.id === activeSection)?.icon ?? 'galaxy'} size={38}/>
          <h2>{navigation.find((item) => item.id === activeSection)?.label ?? activeSection}</h2>
          <p>This section is not connected to the live game state yet. Live gameplay currently includes fleet management, versioned ship models, colonization, production, research and sensor intelligence.</p>
        </section>
      {:else}
        <SectionViews section={activeSection} {status} onSelectSystem={selectSystem} onResearch={prioritizeResearch}/>
      {/if}
    </main>

    {#if activeSection !== 'players' && activeSection !== 'planets' && activeSection !== 'fleets' && activeSection !== 'designs' && activeSection !== 'research' && activeSection !== 'report' && selectedSystem}
      <div class="right-wrap" class:open={rightPanelOpen}>
        <button class="panel-toggle" aria-label="Toggle detail panel" onclick={() => (rightPanelOpen = !rightPanelOpen)}><Icon name={rightPanelOpen ? 'chevron-right' : 'chevron-left'} size={17}/></button>
        <DetailPanel
          system={selectedSystem}
          players={status?.players ?? []}
          currentPlayerId={connection.playerId}
          selectedFleetId={selectedFleetCurrent?.id ?? ''}
          canBuild={demoMode || (editableTurn && selectedSystem.ownerPlayerId === connection.playerId)}
          modelCatalog={status?.model_catalog ?? null}
          productionOrders={(orders.production ?? []).filter((order) => order.systemId === selectedSystem?.id)}
          onRemoveBuild={removeProductionFromSelected}
          onBuild={addProduction}
          onSelectFleet={selectFleetFromDetail}
          onWaypointFleet={beginWaypointFromDetail}
        />
      </div>
    {/if}
  </div>

  <footer class="command-bar">
    <button class:planning={planningFleet !== null} onclick={() => addWaypoint('move')} disabled={!selectedSystem || (!demoMode && (!editableTurn || !ownFleetInSelected))}><Icon name="target" size={28}/><span><strong>{planningFleet ? 'Select destination' : 'Set waypoint'}</strong><small>{demoMode ? 'Plan fleet route' : planningFleet ? planningFleet.name : ownFleetInSelected ? ownFleetInSelected.name : 'Select a fleet'}</small></span></button>
    <button onclick={() => addWaypoint('colonize')} disabled={!selectedSystem || (!demoMode && (!editableTurn || selectedSystem.ownerPlayerId !== null || !colonizerInSelected)) || (demoMode && selectedSystem.owner !== 'neutral')}><Icon name="colonize" size={28}/><span><strong>Colonize</strong><small>{demoMode ? 'Establish colony' : selectedSystem?.ownerPlayerId !== null ? 'Select unclaimed system' : colonizerInSelected ? `${colonyCapacity(colonizerInSelected)} colony module` : 'Requires colony module'}</small></span></button>
    <button onclick={() => addProduction('Orbital Factory')} disabled={!selectedSystem || (!demoMode && (!editableTurn || selectedSystem.ownerPlayerId !== connection.playerId || hasInstallation(selectedSystem, 'orbital_factory'))) || (demoMode && selectedSystem.owner !== 'player')}><Icon name="build" size={28}/><span><strong>Build</strong><small>{demoMode ? 'Construct on planet' : hasInstallation(selectedSystem, 'orbital_factory') ? 'Factory installed · manage upgrades in Planets' : selectedSystem?.ownerPlayerId === connection.playerId ? (preferredInstallation('orbital_factory')?.name ?? 'Queue orbital factory') : 'Select your colony'}</small></span></button>
    <button onclick={() => (activeSection = 'research')}><Icon name="research" size={28}/><span><strong>Research</strong><small>{demoMode ? 'Choose new technology' : researchProjectName}</small></span></button>
    {#if ownSubmitted && serverTurnStatus === 'open' && !demoMode}
      <button class="draft-button" disabled={busy} onclick={onReopen}><Icon name="load" size={24}/><span><strong>Reopen turn</strong><small>Continue editing orders</small></span></button>
    {:else}
      <button class="draft-button" disabled={busy || demoMode || !editableTurn} onclick={onSaveDraft}><Icon name="load" size={24}/><span><strong>Save draft</strong><small>{demoMode ? 'Demo is local only' : 'Store current orders'}</small></span></button>
    {/if}
    <button class="submit-button" disabled={busy || demoMode || !editableTurn} onclick={onSubmit}><span class="submit-ring"><Icon name="play" size={22}/></span><span><strong>{ownSubmitted ? 'Turn submitted' : 'Submit turn'}</strong><small>{demoMode ? 'Connect to submit' : ownSubmitted ? (pendingCount > 0 ? `Waiting for ${pendingCount}` : 'Waiting for processing') : 'End turn and proceed'}</small></span></button>
  </footer>

  {#if message || localNotice}<div class="toast" class:error={(message || localNotice).toLowerCase().includes('error')}>{localNotice || message}</div>{/if}

  {#if showTechnical}
    <div class="modal-backdrop" role="presentation" onclick={() => (showTechnical = false)}>
      <section class="technical-modal panel-cut" role="dialog" aria-modal="true" aria-labelledby="technical-title" onclick={(event) => event.stopPropagation()}>
        <header><div><p>Compatibility tools</p><h2 id="technical-title">Order JSON</h2></div><button class="icon-button" aria-label="Close" onclick={() => (showTechnical = false)}><Icon name="close" /></button></header>
        <p>The visual controls write to the same <code>orders</code> object used by the original MVP client. Advanced or future engine orders can still be edited here.</p>
        <textarea bind:value={technicalText} spellcheck="false"></textarea>
        <div class="modal-actions"><button onclick={() => (showTechnical = false)}>Cancel</button><button class="primary-action" onclick={applyTechnical}>Apply JSON</button><button disabled={demoMode || busy || !ownSubmitted || serverTurnStatus !== 'open'} onclick={onReopen}>Reopen submitted turn</button></div>
      </section>
    </div>
  {/if}
</section>

<style>
  .game-shell { height:100svh; min-height:650px; display:grid; grid-template-rows:66px minmax(0,1fr) 82px; overflow:hidden; background:#02070e; color:#b7cad7 }
  .topbar{display:grid;grid-template-columns:minmax(170px,205px) minmax(135px,1.1fr) 118px minmax(150px,.9fr) minmax(180px,1fr) minmax(118px,130px) 40px 40px;align-items:stretch;min-width:0;overflow:hidden;border-bottom:1px solid rgba(58,170,225,.25);background:linear-gradient(180deg,#071421,#030a12);z-index:20}.top-logo,.game-block,.turn-block,.empire-block,.resource-bar,.turn-progress,.turn-state,.top-icon{min-width:0;overflow:hidden;border-right:1px solid rgba(62,143,187,.16)}.top-logo{display:flex;align-items:center;gap:.45rem;padding:0 .7rem}.top-logo :global(.title){white-space:nowrap}.game-block,.turn-block,.empire-block{display:flex;align-items:center;gap:.5rem;padding:0 .65rem;color:#6dcfff;min-width:0}.empire-block>.player-color{width:8px;height:28px;flex:none;border-radius:4px;background:var(--player-color);box-shadow:0 0 9px var(--player-color)}.game-block span,.turn-block span,.empire-block span{min-width:0}.game-block strong,.game-block small,.turn-block strong,.turn-block small,.empire-block strong,.empire-block small{display:block}.game-block strong,.turn-block strong,.empire-block strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#dbeaf3;font-size:.74rem;font-weight:500}.game-block small,.turn-block small,.empire-block small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#70899b;font-size:.61rem;margin-top:.16rem}.resource-bar{display:flex;align-items:center;justify-content:center}.turn-progress{display:flex;align-items:center;justify-content:center;gap:1rem;padding:0 .6rem}.turn-progress span{min-width:0;text-align:center}.turn-progress strong,.turn-progress small{display:block}.turn-progress strong{color:#dcecf4;font-size:.76rem}.turn-progress small{margin-top:.16rem;color:#6f8b9e;font-size:.55rem;text-transform:uppercase;letter-spacing:.05em}.resource-bar>div{display:flex;align-items:center;gap:.35rem;padding:0 .55rem;color:#55caff;border-right:1px solid rgba(62,143,187,.13)}.resource-bar>div:last-child{border:0}.resource-bar strong,.resource-bar small{display:block}.resource-bar strong{color:#e0edf4;font-size:.7rem}.resource-bar small{color:#6d899b;font-size:.56rem}.turn-state,.top-icon,.mobile-menu{border:0;background:transparent;color:#55cdff;cursor:pointer}.turn-state{display:flex;align-items:center;justify-content:center;gap:.5rem;min-width:0;letter-spacing:.08em}.turn-state span{width:22px;height:22px;flex:none;border:3px solid #42c8ff;border-right-color:transparent;border-radius:50%}.turn-state span.spinning{animation:spin 1s linear infinite}.turn-state strong{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.68rem}.top-icon{display:grid;place-items:center}.top-icon:hover{background:rgba(16,57,82,.45);color:#e9faff}.mobile-menu{display:none}
  .game-grid { min-height:0; display:grid; grid-template-columns:196px minmax(0,1fr) 330px; transition:grid-template-columns .2s }.game-grid.panel-closed{grid-template-columns:196px minmax(0,1fr) 0}.sidebar { min-height:0; overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;scrollbar-gutter:stable;scrollbar-color:#225d7d #07121d;display:flex;flex-direction:column;background:linear-gradient(180deg,#06121e,#020911);border-right:1px solid rgba(58,164,219,.25);z-index:12}.sidebar button{min-height:72px;border:0;border-bottom:1px solid rgba(64,143,184,.13);background:transparent;color:#8ba4b5;display:flex;align-items:center;gap:.9rem;padding:0 1.15rem;text-transform:uppercase;letter-spacing:.07em;font:inherit;font-size:.73rem;cursor:pointer;text-align:left}.sidebar button:hover,.sidebar button.active{color:#e2f6ff;background:linear-gradient(90deg,rgba(12,66,101,.78),rgba(4,20,33,.4));box-shadow:inset 3px 0 #43c9ff}.sidebar button.active{border-color:rgba(67,201,255,.4)}.sidebar-spacer{flex:1}.sidebar button:nth-last-child(-n+2){min-height:52px;font-size:.65rem}.content-area{min-width:0;min-height:0;overflow:hidden}.live-pending{height:100%;display:grid;place-content:center;justify-items:center;gap:.65rem;padding:2rem;text-align:center;background:radial-gradient(circle at 50% 42%,rgba(18,91,127,.13),transparent 35%),#030912;color:#53caff}.live-pending h2{margin:0;color:#e4f5fd;font-size:1.2rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em}.live-pending p{max-width:560px;margin:0;color:#7d97a9;font-size:.76rem;line-height:1.6}.right-wrap{position:relative;min-width:0;min-height:0;overflow:visible;transition:.2s}.right-wrap :global(.detail-panel){height:100%;min-height:0;max-height:100%;overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable}.right-wrap:not(.open){overflow:visible}.right-wrap:not(.open) :global(.detail-panel){display:none}.panel-toggle{position:absolute;left:-28px;top:10px;width:28px;height:42px;display:grid;place-items:center;border:1px solid rgba(56,164,218,.35);border-right:0;background:#061522;color:#5dccfa;cursor:pointer;z-index:6}
  .command-bar { display:grid;grid-template-columns:repeat(4,minmax(135px,1fr)) 145px 215px;gap:8px;padding:8px 10px;border-top:1px solid rgba(62,170,225,.28);background:linear-gradient(180deg,#06111c,#02070d);z-index:18}.command-bar button{border:1px solid rgba(58,174,231,.42);background:linear-gradient(180deg,rgba(7,34,52,.8),rgba(3,15,25,.9));color:#5ecbff;display:flex;align-items:center;justify-content:center;gap:.7rem;font:inherit;cursor:pointer}.command-bar button:hover:not(:disabled){border-color:#48c9ff;background:rgba(10,50,75,.9)}.command-bar button.planning{border-color:#ffd05c;box-shadow:inset 0 0 18px rgba(255,196,55,.12);color:#ffd05c}.command-bar button:disabled{opacity:.35;cursor:not-allowed}.command-bar strong,.command-bar small{display:block;text-align:left}.command-bar strong{text-transform:uppercase;letter-spacing:.06em;font-size:.72rem;color:#70d4ff}.command-bar small{color:#71899a;font-size:.6rem;margin-top:.16rem}.command-bar .draft-button{border-color:rgba(105,155,185,.32)}.command-bar .submit-button{border-color:#ffc139;background:linear-gradient(180deg,rgba(104,69,5,.75),rgba(55,34,1,.9));color:#ffcd53;box-shadow:inset 0 0 18px rgba(255,179,25,.1),0 0 12px rgba(255,174,20,.13)}.command-bar .submit-button strong{color:#ffd25b}.submit-ring{width:35px;height:35px;border:3px solid #ffc646;border-left-color:transparent;border-radius:50%;display:grid;place-items:center}
  .toast { position:fixed;right:1rem;bottom:94px;max-width:430px;padding:.8rem 1rem;border:1px solid rgba(70,197,255,.5);background:rgba(5,27,41,.96);color:#bfe9fc;font-size:.76rem;z-index:40;box-shadow:0 10px 35px rgba(0,0,0,.4)}.toast.error{border-color:rgba(255,84,84,.6);color:#ffc2c2;background:rgba(55,8,14,.96)}
  .modal-backdrop{position:fixed;inset:0;display:grid;place-items:center;padding:1rem;background:rgba(0,5,10,.82);backdrop-filter:blur(5px);z-index:50}.technical-modal{width:min(760px,96vw);max-height:90vh;display:grid;grid-template-rows:auto auto minmax(260px,1fr) auto;gap:.8rem;padding:1rem;background:#061522;border:1px solid rgba(66,190,244,.4)}.technical-modal header{display:flex;justify-content:space-between;align-items:center}.technical-modal header p{margin:0;color:#52c9fc;text-transform:uppercase;font-size:.62rem;letter-spacing:.12em}.technical-modal h2{margin:.2rem 0 0;color:#eef9ff;font-size:1.15rem}.technical-modal>p{margin:0;color:#8299aa;font-size:.72rem}.technical-modal code{color:#5dccfa}.technical-modal textarea{width:100%;height:100%;min-height:260px;resize:vertical;box-sizing:border-box;border:1px solid rgba(57,155,205,.3);background:#01070c;color:#a9d6eb;padding:.8rem;font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;outline:0}.technical-modal textarea:focus{border-color:#45caff}.modal-actions{display:flex;justify-content:flex-end;gap:.6rem;flex-wrap:wrap}.modal-actions button{min-height:38px;padding:0 1rem;border:1px solid rgba(68,166,216,.35);background:#081d2c;color:#8ecfea;cursor:pointer}.modal-actions .primary-action{border-color:#44c9ff;color:#e1f7ff}.modal-actions button:disabled{opacity:.4}.icon-button{width:36px;height:36px;display:grid;place-items:center;border:1px solid rgba(61,165,218,.3);background:rgba(4,19,31,.75);color:#72cfff;cursor:pointer}
  @keyframes spin{to{transform:rotate(360deg)}}
  @media(max-width:1280px){.topbar{grid-template-columns:180px minmax(125px,1fr) 110px 150px minmax(145px,.8fr) 118px 40px 40px}.turn-progress{display:none}.command-bar{grid-template-columns:repeat(4,1fr) 120px 180px}.command-bar small{display:none}.game-grid{grid-template-columns:170px minmax(0,1fr) 300px}.game-grid.panel-closed{grid-template-columns:170px minmax(0,1fr) 0}.sidebar button{padding:0 .85rem}}
  @media(max-width:1100px){.game-shell{grid-template-rows:58px minmax(0,1fr) 72px}.topbar{grid-template-columns:48px 64px minmax(125px,1fr) 108px minmax(105px,125px) 40px 40px}.mobile-menu{display:grid;place-items:center}.top-logo{justify-content:center;padding:0 .2rem;border-left:1px solid rgba(62,143,187,.16)}.top-logo :global(.title){display:none!important}.empire-block,.resource-bar,.turn-progress{display:none}.game-grid,.game-grid.panel-closed{grid-template-columns:minmax(0,1fr) 300px}.sidebar{position:fixed;left:0;top:58px;bottom:72px;width:220px;transform:translateX(-102%);transition:.2s;box-shadow:15px 0 30px rgba(0,0,0,.45)}.sidebar.open{transform:translateX(0)}.command-bar{grid-template-columns:repeat(4,1fr) 160px}.draft-button{display:none!important}.command-bar button{gap:.4rem}.command-bar button>svg{width:22px}.command-bar .submit-button{grid-column:auto}.right-wrap:not(.open){display:none}}
  @media(max-width:760px){.topbar{grid-template-columns:46px 1fr 120px 40px}.game-block,.turn-block{display:none}.top-icon:nth-last-child(2){display:none}.game-grid,.game-grid.panel-closed{grid-template-columns:minmax(0,1fr)}.right-wrap{position:fixed;right:0;top:58px;bottom:72px;width:min(330px,88vw);z-index:25;transform:translateX(100%);transition:.2s;box-shadow:-15px 0 35px rgba(0,0,0,.45)}.right-wrap.open{transform:translateX(0)}.right-wrap:not(.open){display:block}.right-wrap:not(.open) :global(.detail-panel){display:block}.panel-toggle{left:-34px;width:34px}.command-bar{grid-template-columns:repeat(4,1fr) 1.2fr;padding:5px;gap:4px}.command-bar button{display:grid;place-items:center}.command-bar button span:not(.submit-ring){display:none}.command-bar button>svg{margin:auto}.submit-button{display:flex!important}.submit-button>span:last-child{display:block!important}.submit-button small{display:none}.sidebar{bottom:72px}}
  @media(max-width:480px){.top-logo :global(.title){font-size:.74rem!important}.command-bar{grid-template-columns:repeat(4,1fr) 1.3fr}.submit-button strong{font-size:.6rem}.game-shell{min-height:560px}}
</style>
