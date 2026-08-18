<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import PlanetSensorVisual from '../components/PlanetSensorVisual.svelte';
  import type { ModelCatalog, PlanetInstallation, PlanetInstallationUpgrade, PlayerOrders, ProductionOrder, StarSystem, TechnologyModel } from '../types';

  export let systems: StarSystem[] = [];
  export let currentPlayerId = 0;
  export let orders: PlayerOrders = { fleets: [], production: [] };
  export let editableTurn = false;
  export let modelCatalog: ModelCatalog | null = null;
  export let ownerColor = '#47c8ff';
  export let onLocate: (system: StarSystem) => void = () => {};
  export let onQueueBuild: (system: StarSystem, item: string, modelId?: string) => void = () => {};
  export let onQueueUpgrade: (system: StarSystem, targetModelId: string, sourceModelId: string) => void = () => {};
  export let onRemoveBuild: (systemId: string, item: string) => void = () => {};

  type Filter = 'all' | 'idle' | 'building';
  type BuildOption = { item: string; modelId: string; cost: number; icon: string; detail: string; family: string | null; kind: 'ship' | 'installation' };

  const installationFamilies = ['defense_grid', 'orbital_factory', 'deep_space_array'] as const;
  let filter: Filter = 'all';
  let expandedSystemIds: string[] = [];

  $: currentScout = modelCatalog?.designs.find((design) => design.current) ?? modelCatalog?.designs[0] ?? null;
  $: buildOptions = createBuildOptions();
  $: colonies = systems
    .filter((system) => system.ownerPlayerId === currentPlayerId)
    .sort((a, b) => Number(b.isCapital === true) - Number(a.isCapital === true) || a.name.localeCompare(b.name));
  $: buildingCount = colonies.filter((system) => isBusy(system)).length;
  $: idleCount = Math.max(colonies.length - buildingCount, 0);
  $: visibleColonies = colonies.filter((system) => filter === 'all' || (filter === 'building' ? isBusy(system) : !isBusy(system)));
  $: totalPopulation = colonies.reduce((sum, system) => sum + system.population, 0);
  $: totalIndustryIncome = colonies.reduce((sum, system) => sum + effectiveIndustryIncome(system), 0);

  function preferredInstallation(family: string): TechnologyModel | null {
    return [...(modelCatalog?.installations ?? [])]
      .filter((model) => model.family === family && model.unlocked)
      .sort((a, b) => b.version - a.version)[0] ?? null;
  }

  function createBuildOptions(): BuildOption[] {
    const result: BuildOption[] = [];
    if (currentScout) {
      result.push({ item: currentScout.name, modelId: currentScout.id, cost: currentScout.industryCost, icon: 'fleet', detail: `${currentScout.batchSize} ships · ${currentScout.stats.movementRange} hop`, family: null, kind: 'ship' });
    } else {
      result.push({ item: 'Scout Wing', modelId: '', cost: 300, icon: 'fleet', detail: '40 ships · Mk I fallback', family: null, kind: 'ship' });
    }
    for (const [family, icon] of [['defense_grid', 'shield'], ['orbital_factory', 'industry'], ['deep_space_array', 'target']] as const) {
      const model = preferredInstallation(family);
      if (!model) continue;
      const stats = model.stats;
      const detail = family === 'defense_grid'
        ? `+${stats.defenseAdd ?? 0} defenses`
        : family === 'orbital_factory'
          ? `+${stats.industryIncome ?? 0} industry/turn`
          : `sensor range ${stats.sensorRange ?? 1}`;
      result.push({ item: model.name, modelId: model.id, cost: stats.industryCost ?? 0, icon, detail, family, kind: 'installation' });
    }
    return result;
  }

  function isExpanded(systemId: string): boolean {
    return expandedSystemIds.includes(systemId);
  }

  function toggleExpanded(systemId: string): void {
    expandedSystemIds = isExpanded(systemId)
      ? expandedSystemIds.filter((id) => id !== systemId)
      : [...expandedSystemIds, systemId];
  }

  function queueFor(systemId: string): ProductionOrder[] {
    return (orders.production ?? []).filter((order) => order.systemId === systemId);
  }

  function activeUpgrades(system: StarSystem): PlanetInstallationUpgrade[] {
    return system.installationUpgrades ?? [];
  }

  function isBusy(system: StarSystem): boolean {
    return queueFor(system.id).length > 0 || activeUpgrades(system).length > 0;
  }

  function statusLabel(system: StarSystem): string {
    const upgrades = activeUpgrades(system);
    const queue = queueFor(system.id);
    if (upgrades.length > 0) return upgrades.length === 1 ? 'UPGRADING' : `UPGRADING ×${upgrades.length}`;
    if (queue.length > 0) return `BUILDING ×${queue.reduce((sum, order) => sum + order.quantity, 0)}`;
    return 'IDLE';
  }

  function resource(system: StarSystem, id: string) {
    return system.resources.find((entry) => entry.id === id);
  }

  function orderCost(order: ProductionOrder): number {
    if (order.modelId) {
      const design = modelCatalog?.designs.find((entry) => entry.id === order.modelId);
      if (design) return design.industryCost;
      const installation = modelCatalog?.installations.find((entry) => entry.id === order.modelId);
      if (installation) return order.productionKind === 'upgrade'
        ? (installation.upgradeCost ?? installation.stats.industryCost ?? 0)
        : (installation.stats.industryCost ?? 0);
    }
    const legacy: Record<string, number> = { 'Scout Wing': 300, 'Defense Grid': 250, 'Orbital Factory': 400, 'Deep Space Array': 350 };
    return buildOptions.find((entry) => entry.item === order.item)?.cost ?? legacy[order.item] ?? 0;
  }

  function reservedIndustry(systemId: string): number {
    return queueFor(systemId).reduce((sum, order) => sum + orderCost(order) * Math.max(1, order.quantity), 0);
  }

  function effectiveIndustryIncome(system: StarSystem): number {
    return resource(system, 'industry')?.income ?? 0;
  }

  function projectedIndustry(system: StarSystem): number {
    const industry = resource(system, 'industry');
    if (!industry) return 0;
    return industry.value + effectiveIndustryIncome(system);
  }

  function remainingIndustry(system: StarSystem): number {
    return Math.max(0, projectedIndustry(system) - reservedIndustry(system.id));
  }

  function sensorRange(system: StarSystem): number {
    return Math.max(1, Math.min(4, Math.round(system.sensorRange ?? 1)));
  }

  function installedForFamily(system: StarSystem, family: string): PlanetInstallation | null {
    return (system.installations ?? []).find((installation) => installation.family === family) ?? null;
  }

  function installedFamily(system: StarSystem, family: string): boolean {
    return installedForFamily(system, family) !== null;
  }

  function queuedFamily(systemId: string, family: string): boolean {
    const modelIds = new Set((modelCatalog?.installations ?? []).filter((model) => model.family === family).map((model) => model.id));
    return queueFor(systemId).some((order) => order.modelId ? modelIds.has(order.modelId) : false);
  }

  function pendingUpgrade(system: StarSystem, family: string): PlanetInstallationUpgrade | null {
    return activeUpgrades(system).find((upgrade) => upgrade.family === family) ?? null;
  }

  function draftUpgrade(systemId: string, family: string): ProductionOrder | null {
    return queueFor(systemId).find((order) => {
      if (order.productionKind !== 'upgrade' || !order.modelId) return false;
      return modelCatalog?.installations.find((model) => model.id === order.modelId)?.family === family;
    }) ?? null;
  }

  function nextUpgrade(installation: PlanetInstallation): TechnologyModel | null {
    return [...(modelCatalog?.installations ?? [])]
      .filter((model) => model.family === installation.family && model.upgradeFrom === installation.modelId)
      .sort((a, b) => a.version - b.version)[0] ?? null;
  }

  function canQueue(system: StarSystem, option: BuildOption): boolean {
    if (!editableTurn || remainingIndustry(system) < option.cost) return false;
    if (option.kind === 'installation' && option.family) {
      if (installedFamily(system, option.family) || queuedFamily(system.id, option.family) || pendingUpgrade(system, option.family)) return false;
    }
    return true;
  }

  function canQueueUpgrade(system: StarSystem, installation: PlanetInstallation, target: TechnologyModel): boolean {
    if (!editableTurn || !target.unlocked || target.upgradeFrom !== installation.modelId) return false;
    if (pendingUpgrade(system, installation.family) || draftUpgrade(system.id, installation.family)) return false;
    return remainingIndustry(system) >= (target.upgradeCost ?? target.stats.industryCost ?? 0);
  }

  function installationIcon(family: string): string {
    return family === 'defense_grid' ? 'shield' : family === 'deep_space_array' ? 'target' : 'industry';
  }

  function upgradeTitle(system: StarSystem, installation: PlanetInstallation, target: TechnologyModel): string {
    if (!editableTurn) return 'Reopen the turn to queue an upgrade';
    if (!target.unlocked) return `${target.name} is still locked by research`;
    if (pendingUpgrade(system, installation.family)) return 'An upgrade for this installation is already in progress';
    if (draftUpgrade(system.id, installation.family)) return 'An upgrade for this installation is already queued';
    const cost = target.upgradeCost ?? target.stats.industryCost ?? 0;
    if (remainingIndustry(system) < cost) return `${cost.toLocaleString('en-US')} industry required`;
    return `${cost.toLocaleString('en-US')} industry · ${target.upgradeTurns ?? 2} turns`;
  }
</script>

<section class="planets-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">Colony management</p>
      <h1>Planets</h1>
      <p class="intro">Colonies start collapsed for a compact empire overview. Expand a colony to manage installations, upgrades and production.</p>
    </div>
    <div class="planet-summary">
      <span><strong>{colonies.length}</strong><small>colonies</small></span>
      <span><strong>{buildingCount}</strong><small>active</small></span>
      <span class:warning={idleCount > 0}><strong>{idleCount}</strong><small>idle</small></span>
      <span><strong>{totalPopulation.toFixed(1)}B</strong><small>population</small></span>
      <span><strong>+{totalIndustryIncome}</strong><small>industry / turn</small></span>
    </div>
  </header>

  <div class="toolbar panel-cut">
    <div class="filters" aria-label="Planet filters">
      <button class:active={filter === 'all'} onclick={() => (filter = 'all')}>All <span>{colonies.length}</span></button>
      <button class:active={filter === 'building'} onclick={() => (filter = 'building')}>Active <span>{buildingCount}</span></button>
      <button class:active={filter === 'idle'} onclick={() => (filter = 'idle')}>Idle <span>{idleCount}</span></button>
    </div>
    <p>Population, happiness, development and defenses stay visible while a colony is collapsed.</p>
  </div>

  {#if visibleColonies.length > 0}
    <div class="planet-list">
      {#each visibleColonies as system}
        {@const queue = queueFor(system.id)}
        {@const upgrades = activeUpgrades(system)}
        {@const industry = resource(system, 'industry')}
        {@const remaining = remainingIndustry(system)}
        {@const expanded = isExpanded(system.id)}
        <article class="planet-card panel-cut" class:building={isBusy(system)} class:idle={!isBusy(system)} class:expanded>
          <button class="planet-summary-row" aria-expanded={expanded} onclick={() => toggleExpanded(system.id)} title={expanded ? `Collapse ${system.name}` : `Expand ${system.name}`}>
            <span class="expand-icon" class:open={expanded}><Icon name="chevron-right" size={16}/></span>
            <span class="planet-identity">
              <span class="planet-icon sensor-icon"><PlanetSensorVisual color={ownerColor} sensorRange={sensorRange(system)} size={36} label={system.name}/></span>
              <strong>{system.name}</strong>
              {#if system.isCapital}<small>CAPITAL</small>{/if}
            </span>
            <span class="header-stat"><small>Population</small><strong>{system.population.toFixed(1)} / {system.capacity.toFixed(1)}B</strong></span>
            <span class="header-stat"><small>Happiness</small><strong>{system.happiness}%</strong></span>
            <span class="header-stat"><small>Development</small><strong>{system.development}%</strong></span>
            <span class="header-stat"><small>Defenses</small><strong>{system.defenses.toLocaleString('en-US')}</strong></span>
            <span class="compact-status" class:idle-status={!isBusy(system)}>{statusLabel(system)}</span>
          </button>

          {#if expanded}
            <div class="expanded-content">
              <div class="metrics">
                <div><small>Industry</small><strong>{industry?.value.toLocaleString('en-US') ?? '0'}</strong><em>+{effectiveIndustryIncome(system)}</em></div>
                <div><small>Available after queue</small><strong>{remaining.toLocaleString('en-US')}</strong></div>
                <div><small>Sensor range</small><strong>{sensorRange(system)} hop{sensorRange(system) === 1 ? '' : 's'}</strong><em>{(system.installations ?? []).find((installation) => installation.family === 'deep_space_array')?.name ?? 'Base colony sensors'}</em></div>
              </div>

              <section class="installations-block">
                <div class="section-title"><span>Installed models</span><small>Existing hardware remains active until an explicit upgrade completes.</small></div>
                <div class="installation-list">
                  {#if (system.installations ?? []).length > 0}
                    {#each (system.installations ?? []) as installation}
                      {@const pending = pendingUpgrade(system, installation.family)}
                      {@const draft = draftUpgrade(system.id, installation.family)}
                      {@const target = nextUpgrade(installation)}
                      <div class="installation-entry" class:upgrade-active={Boolean(pending || draft)}>
                        <span class="installation-model"><Icon name={installationIcon(installation.family)} size={15}/><span><strong>{installation.name}</strong><small>v{installation.version} · active</small></span></span>
                        {#if pending}
                          <span class="upgrade-progress"><strong>→ {pending.toName}</strong><small>{pending.turnsRemaining} turn{pending.turnsRemaining === 1 ? '' : 's'} remaining</small></span>
                        {:else if draft}
                          <span class="upgrade-progress queued"><strong>→ {draft.modelName ?? draft.item}</strong><small>Starts when this turn is processed · {draft.upgradeTurns ?? 2} turns total</small></span>
                        {:else if target}
                          <button class="upgrade-button" disabled={!canQueueUpgrade(system, installation, target)} title={upgradeTitle(system, installation, target)} onclick={() => onQueueUpgrade(system, target.id, installation.modelId)}>
                            <Icon name="build" size={14}/><span><strong>Upgrade → {target.name}</strong><small>{target.unlocked ? `${(target.upgradeCost ?? target.stats.industryCost ?? 0).toLocaleString('en-US')} industry · ${target.upgradeTurns ?? 2} turns` : 'Locked by research'}</small></span>
                          </button>
                        {:else}
                          <span class="max-model">Current technology</span>
                        {/if}
                      </div>
                    {/each}
                  {:else}
                    <div class="installation-entry none"><span class="installation-model"><Icon name="build" size={14}/><span><strong>No registered installations</strong><small>base colony</small></span></span></div>
                  {/if}
                </div>
              </section>

              <section class="queue-block">
                <div class="section-title"><span>Orders this turn</span><button onclick={() => onLocate(system)}>Open in galaxy <Icon name="galaxy" size={14}/></button></div>
                {#if queue.length > 0}
                  <div class="queue-list">
                    {#each queue as order}
                      <div class="queue-item">
                        <span class="queue-icon"><Icon name={order.modelId?.startsWith('scout-') || order.item === 'Scout Wing' ? 'fleet' : order.modelId?.startsWith('defense_grid') || order.item === 'Defense Grid' ? 'shield' : order.modelId?.startsWith('deep_space_array') || order.item === 'Deep Space Array' ? 'target' : 'industry'} size={16}/></span>
                        <span><strong>{order.item}</strong><small>{orderCost(order).toLocaleString('en-US')} industry{order.productionKind === 'upgrade' ? ` · ${order.upgradeTurns ?? 2} turns` : ' each'}</small></span>
                        <em>{order.productionKind === 'upgrade' ? 'UP' : `×${order.quantity}`}</em>
                        <button disabled={!editableTurn} title={`Remove ${order.item}`} onclick={() => onRemoveBuild(system.id, order.item)}>−</button>
                      </div>
                    {/each}
                  </div>
                {:else if upgrades.length > 0}
                  <div class="idle-message active-work"><Icon name="build" size={20}/><span><strong>Installation work continues.</strong><small>The current model remains active until the upgrade completes.</small></span></div>
                {:else}
                  <div class="idle-message"><Icon name="build" size={20}/><span><strong>This system has no orders this turn.</strong><small>Choose a ship build or an installation action below.</small></span></div>
                {/if}
              </section>

              <div class="build-actions">
                {#each buildOptions.filter((option) => option.kind === 'ship') as option}
                  <button disabled={!canQueue(system, option)} onclick={() => onQueueBuild(system, option.item, option.modelId)} title={!editableTurn ? 'Reopen the turn to change production' : `${option.cost} industry`}>
                    <Icon name={option.icon} size={16}/>
                    <span><strong>{option.item}</strong><small>{option.cost} industry · {option.detail}</small></span>
                  </button>
                {/each}

                {#each installationFamilies as family}
                  {@const installed = installedForFamily(system, family)}
                  {@const pending = pendingUpgrade(system, family)}
                  {@const draft = draftUpgrade(system.id, family)}
                  {@const target = installed ? nextUpgrade(installed) : null}
                  {@const buildModel = installed ? null : preferredInstallation(family)}
                  {#if pending}
                    <button disabled class="work-action"><Icon name={installationIcon(family)} size={16}/><span><strong>{pending.toName}</strong><small>Upgrade in progress · {pending.turnsRemaining} turn{pending.turnsRemaining === 1 ? '' : 's'} remaining</small></span></button>
                  {:else if draft}
                    <button disabled class="work-action"><Icon name={installationIcon(family)} size={16}/><span><strong>{draft.modelName ?? draft.item}</strong><small>Upgrade queued · {draft.upgradeTurns ?? 2} turns total</small></span></button>
                  {:else if installed && target}
                    <button disabled={!canQueueUpgrade(system, installed, target)} class:locked={!target.unlocked} onclick={() => onQueueUpgrade(system, target.id, installed.modelId)} title={upgradeTitle(system, installed, target)}>
                      <Icon name={installationIcon(family)} size={16}/>
                      <span><strong>{target.name}</strong><small>{target.unlocked ? `Upgrade ${installed.name} → ${target.name} · ${(target.upgradeCost ?? target.stats.industryCost ?? 0).toLocaleString('en-US')} industry` : `Locked by research · ${installed.name} remains active`}</small></span>
                    </button>
                  {:else if installed}
                    <button disabled class="current-action"><Icon name={installationIcon(family)} size={16}/><span><strong>{installed.name}</strong><small>Installed · current available model</small></span></button>
                  {:else if buildModel}
                    {@const buildOption = buildOptions.find((option) => option.modelId === buildModel.id)}
                    <button disabled={!buildOption || !canQueue(system, buildOption)} onclick={() => onQueueBuild(system, buildModel.name, buildModel.id)} title={!editableTurn ? 'Reopen the turn to change production' : `${buildModel.stats.industryCost ?? 0} industry`}>
                      <Icon name={installationIcon(family)} size={16}/>
                      <span><strong>{buildModel.name}</strong><small>{buildModel.stats.industryCost ?? 0} industry · new installation</small></span>
                    </button>
                  {/if}
                {/each}
              </div>
            </div>
          {/if}
        </article>
      {/each}
    </div>
  {:else}
    <div class="empty panel-cut"><Icon name="planet" size={38}/><h2>No planets in this view</h2><p>{colonies.length === 0 ? 'You do not own any colonies in the current game state.' : 'Change the filter to see your other colonies.'}</p></div>
  {/if}
</section>

<style>
  .planets-view{height:100%;overflow:auto;padding:1.35rem;background:radial-gradient(circle at 44% 12%,rgba(18,93,129,.14),transparent 42%),#030912;color:#91a8b7;box-sizing:border-box}.view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}.eyebrow{margin:0 0 .3rem;color:#43c5ff;text-transform:uppercase;letter-spacing:.14em;font-size:.65rem}h1{margin:0;color:#edf9ff;font-size:1.65rem;font-weight:500;letter-spacing:.08em}.intro{max-width:760px;margin:.5rem 0 0;color:#7f98aa;font-size:.76rem;line-height:1.5}.planet-summary{display:flex;gap:1.15rem;flex-wrap:wrap;justify-content:flex-end}.planet-summary span{text-align:right}.planet-summary strong,.planet-summary small{display:block}.planet-summary strong{color:#e4f4fb;font-size:1rem}.planet-summary small{margin-top:.15rem;color:#648296;text-transform:uppercase;font-size:.55rem;letter-spacing:.08em}.planet-summary .warning strong{color:#e9c25c}
  .toolbar{min-height:48px;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.45rem .6rem;margin-bottom:.75rem;border:1px solid rgba(58,154,207,.2);background:rgba(4,16,28,.76)}.filters{display:flex;gap:.35rem}.filters button{min-height:32px;padding:0 .65rem;border:1px solid rgba(61,148,194,.22);background:#071827;color:#7795a8;font:inherit;font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;cursor:pointer}.filters button span{margin-left:.25rem;color:#c9d9e1}.filters button.active{border-color:#43c8ff;color:#dff6ff;background:rgba(14,67,94,.68)}.toolbar p{margin:0;color:#617e91;font-size:.59rem;text-align:right}
  .planet-list{display:grid;gap:.55rem}.planet-card{border:1px solid rgba(58,154,207,.23);background:linear-gradient(180deg,rgba(5,21,35,.96),rgba(3,13,23,.96));box-shadow:inset 3px 0 #42c8ff}.planet-card.idle{box-shadow:inset 3px 0 #9b7a32}.planet-card.expanded{border-color:rgba(67,194,245,.38)}.planet-summary-row{width:100%;min-height:54px;display:grid;grid-template-columns:22px minmax(180px,1.5fr) repeat(4,minmax(92px,.75fr)) minmax(92px,.7fr);gap:.55rem;align-items:center;padding:.38rem .75rem;border:0;background:transparent;color:inherit;text-align:left;font:inherit;cursor:pointer}.planet-summary-row:hover{background:rgba(11,48,69,.34)}.expand-icon{display:grid;place-items:center;color:#5cccf6;transition:transform .16s}.expand-icon.open{transform:rotate(90deg)}.planet-identity{display:flex;align-items:center;gap:.48rem;min-width:0}.planet-identity>strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#e1eff6;font-size:.82rem;font-weight:500;text-transform:uppercase;letter-spacing:.06em}.planet-identity>small{flex:none;padding:.15rem .26rem;border:1px solid rgba(72,194,241,.35);color:#66cff8;font-size:.46rem;letter-spacing:.06em}.planet-icon{width:38px;height:38px;display:grid;place-items:center;flex:none;color:#5dd1ff}.sensor-icon{overflow:visible}.header-stat{min-width:0;border-left:1px solid rgba(57,132,173,.13);padding-left:.65rem}.header-stat small,.header-stat strong{display:block}.header-stat small{color:#627f92;font-size:.49rem;text-transform:uppercase;letter-spacing:.05em}.header-stat strong{margin-top:.12rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#cddfe8;font-size:.65rem;font-weight:500}.compact-status{justify-self:end;color:#61d0fa;font-size:.58rem;font-weight:700;letter-spacing:.08em;white-space:nowrap}.compact-status.idle-status{color:#d1ad57}.expanded-content{border-top:1px solid rgba(57,132,173,.14)}
  .metrics{display:grid;grid-template-columns:repeat(3,minmax(120px,1fr));border-bottom:1px solid rgba(57,132,173,.14)}.metrics>div{min-height:52px;padding:.5rem .75rem;border-right:1px solid rgba(57,132,173,.12)}.metrics>div:last-child{border:0}.metrics small,.metrics strong,.metrics em{display:block}.metrics small{color:#698496;font-size:.53rem;text-transform:uppercase;letter-spacing:.06em}.metrics strong{margin-top:.16rem;color:#d7e7ef;font-size:.75rem;font-weight:500}.metrics em{margin-top:.05rem;color:#65bfe7;font-size:.52rem;font-style:normal}
  .installations-block{padding:.6rem .8rem;border-bottom:1px solid rgba(57,132,173,.14)}.section-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;color:#53c8f8;text-transform:uppercase;letter-spacing:.08em;font-size:.59rem}.section-title small{color:#637f91;font-size:.5rem;text-transform:none;letter-spacing:0}.section-title button{display:flex;align-items:center;gap:.3rem;border:0;background:transparent;color:#70bad9;font:inherit;font-size:.56rem;cursor:pointer}.installation-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.4rem}.installation-entry{min-height:64px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.55rem;align-items:center;padding:.42rem .5rem;border:1px solid rgba(61,151,195,.18);background:rgba(5,25,39,.55)}.installation-entry.upgrade-active{border-color:rgba(229,190,76,.35);background:rgba(45,35,10,.22)}.installation-model{display:flex;align-items:center;gap:.4rem;color:#59c9f5;min-width:0}.installation-model span,.installation-model strong,.installation-model small{display:block;min-width:0}.installation-model strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#c4d8e2;font-size:.6rem;font-weight:500}.installation-model small{margin-top:.1rem;color:#718c9e;font-size:.49rem}.upgrade-button{min-height:38px;display:flex;align-items:center;gap:.35rem;padding:.3rem .4rem;border:1px solid rgba(72,199,255,.28);background:rgba(8,42,61,.7);color:#57caff;text-align:left;font:inherit;cursor:pointer}.upgrade-button:hover:not(:disabled){border-color:#48caff}.upgrade-button:disabled{opacity:.35;cursor:not-allowed}.upgrade-button span,.upgrade-button strong,.upgrade-button small{display:block}.upgrade-button strong{color:#cbe8f5;font-size:.53rem;font-weight:500}.upgrade-button small{margin-top:.08rem;color:#6e95a9;font-size:.46rem}.upgrade-progress{min-width:120px;padding:.32rem .4rem;border-left:2px solid #e2b94f}.upgrade-progress strong,.upgrade-progress small{display:block}.upgrade-progress strong{color:#e5d38f;font-size:.54rem;font-weight:500}.upgrade-progress small{margin-top:.1rem;color:#9a8d63;font-size:.46rem}.upgrade-progress.queued{border-left-color:#53c9f8}.upgrade-progress.queued strong{color:#92ddfb}.max-model{color:#647f90;font-size:.48rem;text-transform:uppercase;letter-spacing:.06em}.installation-entry.none{grid-template-columns:1fr;opacity:.7}
  .queue-block{padding:.7rem .8rem;border-bottom:1px solid rgba(57,132,173,.14)}.queue-list{display:grid;gap:.35rem}.queue-item{min-height:46px;display:grid;grid-template-columns:30px minmax(0,1fr) 40px 38px;gap:.45rem;align-items:center;padding:.3rem .45rem;border:1px solid rgba(61,151,195,.18);background:rgba(5,25,39,.65)}.queue-icon{width:28px;height:28px;display:grid;place-items:center;color:#61cdf8;border:1px solid rgba(70,189,237,.24)}.queue-item span strong,.queue-item span small{display:block}.queue-item span strong{color:#cbdde6;font-size:.67rem;font-weight:500}.queue-item span small{margin-top:.12rem;color:#677f90;font-size:.54rem}.queue-item em{color:#e5c662;font-size:.6rem;font-style:normal;text-align:right}.queue-item button{height:28px;border:1px solid rgba(198,105,77,.3);background:rgba(68,25,19,.45);color:#e5a38e;font:inherit;font-size:.6rem;cursor:pointer}.queue-item button:disabled{opacity:.35;cursor:not-allowed}.idle-message{min-height:48px;display:flex;align-items:center;gap:.55rem;color:#bd9c4d}.idle-message.active-work{color:#5fcaff}.idle-message strong,.idle-message small{display:block}.idle-message strong{color:#c6b17a;font-size:.65rem;font-weight:500}.idle-message.active-work strong{color:#a9daed}.idle-message small{margin-top:.13rem;color:#6e8190;font-size:.55rem}
  .build-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:.45rem;padding:.65rem .8rem}.build-actions button{min-height:50px;display:grid;grid-template-columns:22px minmax(0,1fr);gap:.45rem;align-items:center;padding:.4rem .55rem;border:1px solid rgba(61,160,209,.25);background:rgba(5,27,42,.72);color:#58caff;text-align:left;font:inherit;cursor:pointer}.build-actions button:hover:not(:disabled){border-color:#48caff;background:rgba(10,49,72,.86)}.build-actions button:disabled{opacity:.36;cursor:not-allowed}.build-actions button.work-action{border-color:rgba(217,181,72,.25);background:rgba(48,37,8,.25);opacity:.72}.build-actions button.current-action{opacity:.55}.build-actions button.locked{opacity:.42}.build-actions span,.build-actions strong,.build-actions small{display:block;min-width:0}.build-actions strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#c7dbe6;font-size:.64rem;font-weight:500}.build-actions small{margin-top:.12rem;color:#69899d;font-size:.53rem;line-height:1.25}.empty{min-height:250px;display:grid;place-content:center;justify-items:center;gap:.45rem;border:1px solid rgba(58,154,207,.2);background:rgba(4,16,28,.75);color:#54c8f7;text-align:center}.empty h2{margin:.3rem 0 0;color:#dcebf3;font-size:.92rem;font-weight:500}.empty p{margin:0;color:#708a9b;font-size:.68rem}
  @media(max-width:1200px){.installation-list{grid-template-columns:1fr}.view-header{display:grid}.planet-summary{justify-content:flex-start}.planet-summary span{text-align:left}.planet-summary-row{grid-template-columns:22px minmax(170px,1.4fr) repeat(2,minmax(90px,.8fr)) minmax(92px,.7fr)}.header-stat:nth-of-type(4),.header-stat:nth-of-type(5){display:none}.build-actions{grid-template-columns:repeat(2,1fr)}.toolbar{display:grid}.toolbar p{text-align:left}}
  @media(max-width:760px){.planets-view{padding:.8rem}.planet-summary-row{grid-template-columns:20px minmax(145px,1fr) minmax(80px,.7fr) minmax(80px,.7fr)}.header-stat{display:none}.header-stat:nth-of-type(2),.header-stat:nth-of-type(3){display:block}.planet-icon{display:none}.compact-status{grid-column:4}.metrics{grid-template-columns:1fr}.metrics>div{border-right:0;border-bottom:1px solid rgba(57,132,173,.12)}.metrics>div:last-child{border-bottom:0}.planet-summary{gap:.8rem}.filters{overflow-x:auto}.toolbar p{display:none}.queue-item{grid-template-columns:28px minmax(0,1fr) 34px 34px}.installation-entry{grid-template-columns:1fr}.upgrade-progress{min-width:0}.build-actions{grid-template-columns:1fr}}
</style>
