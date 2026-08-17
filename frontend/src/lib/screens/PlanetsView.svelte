<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import PlanetSensorVisual from '../components/PlanetSensorVisual.svelte';
  import type { ModelCatalog, PlayerOrders, ProductionOrder, StarSystem, TechnologyModel } from '../types';

  export let systems: StarSystem[] = [];
  export let currentPlayerId = 0;
  export let orders: PlayerOrders = { fleets: [], production: [] };
  export let editableTurn = false;
  export let modelCatalog: ModelCatalog | null = null;
  export let ownerColor = '#47c8ff';
  export let onLocate: (system: StarSystem) => void = () => {};
  export let onQueueBuild: (system: StarSystem, item: string, modelId?: string) => void = () => {};
  export let onRemoveBuild: (systemId: string, item: string) => void = () => {};

  type Filter = 'all' | 'idle' | 'building';
  let filter: Filter = 'all';

  type BuildOption = { item: string; modelId: string; cost: number; icon: string; detail: string; family: string | null; kind: 'ship' | 'installation' };

  $: currentScout = modelCatalog?.designs.find((design) => design.current) ?? modelCatalog?.designs[0] ?? null;
  $: buildOptions = createBuildOptions();

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

  $: colonies = systems
    .filter((system) => system.ownerPlayerId === currentPlayerId)
    .sort((a, b) => Number(b.isCapital === true) - Number(a.isCapital === true) || a.name.localeCompare(b.name));
  $: buildingCount = colonies.filter((system) => queueFor(system.id).length > 0).length;
  $: idleCount = Math.max(colonies.length - buildingCount, 0);
  $: visibleColonies = colonies.filter((system) => {
    const building = queueFor(system.id).length > 0;
    return filter === 'all' || (filter === 'building' ? building : !building);
  });
  $: totalPopulation = colonies.reduce((sum, system) => sum + system.population, 0);
  $: totalIndustryIncome = colonies.reduce((sum, system) => sum + effectiveIndustryIncome(system), 0);

  function queueFor(systemId: string): ProductionOrder[] {
    return (orders.production ?? []).filter((order) => order.systemId === systemId);
  }

  function resource(system: StarSystem, id: string) {
    return system.resources.find((entry) => entry.id === id);
  }

  function buildCost(item: string, modelId = ''): number {
    if (modelId) return buildOptions.find((entry) => entry.modelId === modelId)?.cost ?? 0;
    const legacy: Record<string, number> = { 'Scout Wing': 300, 'Defense Grid': 250, 'Orbital Factory': 400, 'Deep Space Array': 350 };
    return buildOptions.find((entry) => entry.item === item)?.cost ?? legacy[item] ?? 0;
  }

  function reservedIndustry(systemId: string): number {
    return queueFor(systemId).reduce((sum, order) => sum + buildCost(order.item, order.modelId ?? '') * Math.max(1, order.quantity), 0);
  }

  function effectiveIndustryIncome(system: StarSystem): number {
    const industry = resource(system, 'industry');
    return industry?.income ?? 0;
  }

  function projectedIndustry(system: StarSystem): number {
    const industry = resource(system, 'industry');
    if (!industry) return 0;
    return industry.value + effectiveIndustryIncome(system);
  }

  function buildDetail(option: BuildOption): string {
    return option.detail;
  }

  function remainingIndustry(system: StarSystem): number {
    return Math.max(0, projectedIndustry(system) - reservedIndustry(system.id));
  }

  function sensorRange(system: StarSystem): number {
    return Math.max(1, Math.min(4, Math.round(system.sensorRange ?? 1)));
  }

  function effectiveSensorRange(system: StarSystem): number {
    return sensorRange(system);
  }

  function installedFamily(system: StarSystem, family: string): boolean {
    return (system.installations ?? []).some((installation) => installation.family === family);
  }

  function queuedFamily(systemId: string, family: string): boolean {
    const modelIds = new Set((modelCatalog?.installations ?? []).filter((model) => model.family === family).map((model) => model.id));
    return queueFor(systemId).some((order) => order.modelId ? modelIds.has(order.modelId) : false);
  }

  function canQueue(system: StarSystem, option: BuildOption): boolean {
    if (!editableTurn || remainingIndustry(system) < option.cost) return false;
    if (option.kind === 'installation' && option.family) {
      if (installedFamily(system, option.family) || queuedFamily(system.id, option.family)) return false;
    }
    return true;
  }
</script>

<section class="planets-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">Colony management</p>
      <h1>Planets</h1>
      <p class="intro">Manage every colony from one screen. The build queue below is the draft for the current turn; production completes when the turn is processed.</p>
    </div>
    <div class="planet-summary">
      <span><strong>{colonies.length}</strong><small>colonies</small></span>
      <span><strong>{buildingCount}</strong><small>building</small></span>
      <span class:warning={idleCount > 0}><strong>{idleCount}</strong><small>idle</small></span>
      <span><strong>{totalPopulation.toFixed(1)}B</strong><small>population</small></span>
      <span><strong>+{totalIndustryIncome}</strong><small>industry / turn</small></span>
    </div>
  </header>

  <div class="toolbar panel-cut">
    <div class="filters" aria-label="Planet filters">
      <button class:active={filter === 'all'} onclick={() => (filter = 'all')}>All <span>{colonies.length}</span></button>
      <button class:active={filter === 'building'} onclick={() => (filter = 'building')}>Building <span>{buildingCount}</span></button>
      <button class:active={filter === 'idle'} onclick={() => (filter = 'idle')}>Idle <span>{idleCount}</span></button>
    </div>
    <p>Projected industry includes this turn's income. Queued build costs are reserved immediately in this overview.</p>
  </div>

  {#if visibleColonies.length > 0}
    <div class="planet-list">
      {#each visibleColonies as system}
        {@const queue = queueFor(system.id)}
        {@const industry = resource(system, 'industry')}
        {@const remaining = remainingIndustry(system)}
        <article class="planet-card panel-cut" class:building={queue.length > 0} class:idle={queue.length === 0}>
          <div class="planet-main">
            <button class="planet-title" onclick={() => onLocate(system)}>
              <span class="planet-icon sensor-icon"><PlanetSensorVisual color={ownerColor} sensorRange={effectiveSensorRange(system)} size={48} label={system.name}/></span>
              <span><strong>{system.name}</strong><small>{system.isCapital ? 'Capital' : system.className} · {system.population.toFixed(1)} / {system.capacity.toFixed(1)}B</small></span>
            </button>
            <div class="status" class:idle-status={queue.length === 0}>
              {#if queue.length > 0}<strong>BUILDING</strong><small>{queue.reduce((sum, order) => sum + order.quantity, 0)} queued</small>
              {:else}<strong>IDLE</strong><small>No build order this turn</small>{/if}
            </div>
          </div>

          <div class="metrics">
            <div><small>Happiness</small><strong>{system.happiness}%</strong></div>
            <div><small>Development</small><strong>{system.development}%</strong></div>
            <div><small>Defenses</small><strong>{system.defenses.toLocaleString('en-US')}</strong></div>
            <div><small>Industry</small><strong>{industry?.value.toLocaleString('en-US') ?? '0'}</strong><em>+{effectiveIndustryIncome(system)}</em></div>
            <div><small>Available after queue</small><strong>{remaining.toLocaleString('en-US')}</strong></div>
            <div><small>Sensor range</small><strong>{effectiveSensorRange(system)} hop{effectiveSensorRange(system) === 1 ? '' : 's'}</strong><em>{(system.installations ?? []).find((installation) => installation.family === 'deep_space_array')?.name ?? 'Base colony sensors'}</em></div>
          </div>

          <section class="installations-block">
            <div class="section-title"><span>Installed models</span><small>Research unlocks newer models; installed versions do not change automatically.</small></div>
            <div class="installation-list">
              {#if (system.installations ?? []).length > 0}
                {#each (system.installations ?? []) as installation}
                  <span><Icon name={installation.family === 'defense_grid' ? 'shield' : installation.family === 'deep_space_array' ? 'target' : 'industry'} size={14}/><strong>{installation.name}</strong><em>v{installation.version}</em></span>
                {/each}
              {:else}
                <span class="none"><Icon name="build" size={14}/><strong>No registered installations</strong><em>legacy/base colony</em></span>
              {/if}
            </div>
          </section>

          <section class="queue-block">
            <div class="section-title"><span>Build queue</span><button onclick={() => onLocate(system)}>Open planet <Icon name="galaxy" size={14}/></button></div>
            {#if queue.length > 0}
              <div class="queue-list">
                {#each queue as order}
                  <div class="queue-item">
                    <span class="queue-icon"><Icon name={order.modelId?.startsWith('scout-') || order.item === 'Scout Wing' ? 'fleet' : order.modelId?.startsWith('defense_grid') || order.item === 'Defense Grid' ? 'shield' : order.modelId?.startsWith('deep_space_array') || order.item === 'Deep Space Array' ? 'target' : 'industry'} size={16}/></span>
                    <span><strong>{order.item}</strong><small>{buildCost(order.item, order.modelId ?? '').toLocaleString('en-US')} industry each</small></span>
                    <em>×{order.quantity}</em>
                    <button disabled={!editableTurn} title={`Remove one ${order.item}`} onclick={() => onRemoveBuild(system.id, order.item)}>−1</button>
                  </div>
                {/each}
              </div>
            {:else}
              <div class="idle-message"><Icon name="build" size={20}/><span><strong>This system is not building anything.</strong><small>Choose a build below or open the planet on the galaxy map.</small></span></div>
            {/if}
          </section>

          <div class="build-actions">
            {#each buildOptions as option}
              <button disabled={!canQueue(system, option)} onclick={() => onQueueBuild(system, option.item, option.modelId)} title={!editableTurn ? 'Reopen the turn to change production' : option.family && installedFamily(system, option.family) ? 'A model of this installation is already installed; explicit upgrade arrives in 0.7.2' : `${option.cost} industry`}>
                <Icon name={option.icon} size={16}/>
                <span><strong>{option.item}</strong><small>{option.cost} industry · {buildDetail(option)}</small></span>
              </button>
            {/each}
          </div>
        </article>
      {/each}
    </div>
  {:else}
    <div class="empty panel-cut"><Icon name="planet" size={38}/><h2>No planets in this view</h2><p>{colonies.length === 0 ? 'You do not own any colonies in the current game state.' : 'Change the filter to see your other colonies.'}</p></div>
  {/if}
</section>

<style>
  .planets-view{height:100%;overflow:auto;padding:1.35rem;background:radial-gradient(circle at 44% 12%,rgba(18,93,129,.14),transparent 42%),#030912;color:#91a8b7;box-sizing:border-box}.view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}.eyebrow{margin:0 0 .3rem;color:#43c5ff;text-transform:uppercase;letter-spacing:.14em;font-size:.65rem}h1{margin:0;color:#edf9ff;font-size:1.65rem;font-weight:500;letter-spacing:.08em}.intro{max-width:700px;margin:.5rem 0 0;color:#7f98aa;font-size:.76rem;line-height:1.5}.planet-summary{display:flex;gap:1.15rem;flex-wrap:wrap;justify-content:flex-end}.planet-summary span{text-align:right}.planet-summary strong,.planet-summary small{display:block}.planet-summary strong{color:#e4f4fb;font-size:1rem}.planet-summary small{margin-top:.15rem;color:#648296;text-transform:uppercase;font-size:.55rem;letter-spacing:.08em}.planet-summary .warning strong{color:#e9c25c}
  .toolbar{min-height:48px;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.45rem .6rem;margin-bottom:.75rem;border:1px solid rgba(58,154,207,.2);background:rgba(4,16,28,.76)}.filters{display:flex;gap:.35rem}.filters button{min-height:32px;padding:0 .65rem;border:1px solid rgba(61,148,194,.22);background:#071827;color:#7795a8;font:inherit;font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;cursor:pointer}.filters button span{margin-left:.25rem;color:#c9d9e1}.filters button.active{border-color:#43c8ff;color:#dff6ff;background:rgba(14,67,94,.68)}.toolbar p{margin:0;color:#617e91;font-size:.59rem;text-align:right}
  .planet-list{display:grid;gap:.7rem}.planet-card{border:1px solid rgba(58,154,207,.23);background:linear-gradient(180deg,rgba(5,21,35,.96),rgba(3,13,23,.96));box-shadow:inset 3px 0 #42c8ff}.planet-card.idle{box-shadow:inset 3px 0 #9b7a32}.planet-main{min-height:68px;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.55rem .8rem;border-bottom:1px solid rgba(57,132,173,.14)}.planet-title{display:flex;align-items:center;gap:.65rem;min-width:0;border:0;background:transparent;color:inherit;text-align:left;font:inherit;cursor:pointer}.planet-title>span:last-child{min-width:0}.planet-title strong,.planet-title small{display:block}.planet-title strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#e1eff6;font-size:.84rem;font-weight:500;text-transform:uppercase;letter-spacing:.06em}.planet-title small{margin-top:.18rem;color:#718da0;font-size:.59rem}.planet-icon{width:52px;height:52px;display:grid;place-items:center;flex:none;border:1px solid rgba(68,190,239,.26);background:rgba(7,31,47,.55);color:#5dd1ff}.sensor-icon{overflow:visible}.status{min-width:88px;text-align:right}.status strong,.status small{display:block}.status strong{color:#62d5ff;font-size:.61rem;letter-spacing:.1em}.status small{margin-top:.18rem;color:#6d8798;font-size:.55rem}.status.idle-status strong{color:#d1ad57}
  .metrics{display:grid;grid-template-columns:repeat(6,minmax(90px,1fr));border-bottom:1px solid rgba(57,132,173,.14)}.metrics>div{min-height:58px;padding:.55rem .7rem;border-right:1px solid rgba(57,132,173,.12)}.metrics>div:last-child{border:0}.metrics small,.metrics strong,.metrics em{display:block}.metrics small{color:#698496;font-size:.55rem;text-transform:uppercase;letter-spacing:.06em}.metrics strong{margin-top:.18rem;color:#d7e7ef;font-size:.78rem;font-weight:500}.metrics em{margin-top:.05rem;color:#65bfe7;font-size:.54rem;font-style:normal}
  .installations-block{padding:.6rem .8rem;border-bottom:1px solid rgba(57,132,173,.14)}.installations-block .section-title{margin-bottom:.45rem}.installations-block .section-title small{color:#637f91;font-size:.5rem;text-transform:none;letter-spacing:0}.installation-list{display:flex;gap:.35rem;flex-wrap:wrap}.installation-list>span{display:flex;align-items:center;gap:.35rem;padding:.35rem .45rem;border:1px solid rgba(61,151,195,.18);background:rgba(5,25,39,.55);color:#59c9f5}.installation-list strong{color:#bcd1dc;font-size:.56rem;font-weight:500}.installation-list em{color:#718c9e;font-size:.48rem;font-style:normal}.installation-list .none{opacity:.65}.queue-block{padding:.7rem .8rem;border-bottom:1px solid rgba(57,132,173,.14)}.section-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;color:#53c8f8;text-transform:uppercase;letter-spacing:.08em;font-size:.59rem}.section-title button{display:flex;align-items:center;gap:.3rem;border:0;background:transparent;color:#70bad9;font:inherit;font-size:.56rem;cursor:pointer}.queue-list{display:grid;gap:.35rem}.queue-item{min-height:46px;display:grid;grid-template-columns:30px minmax(0,1fr) 40px 38px;gap:.45rem;align-items:center;padding:.3rem .45rem;border:1px solid rgba(61,151,195,.18);background:rgba(5,25,39,.65)}.queue-icon{width:28px;height:28px;display:grid;place-items:center;color:#61cdf8;border:1px solid rgba(70,189,237,.24)}.queue-item span strong,.queue-item span small{display:block}.queue-item span strong{color:#cbdde6;font-size:.67rem;font-weight:500}.queue-item span small{margin-top:.12rem;color:#677f90;font-size:.54rem}.queue-item em{color:#e5c662;font-size:.67rem;font-style:normal;text-align:right}.queue-item button{height:28px;border:1px solid rgba(198,105,77,.3);background:rgba(68,25,19,.45);color:#e5a38e;font:inherit;font-size:.6rem;cursor:pointer}.queue-item button:disabled{opacity:.35;cursor:not-allowed}.idle-message{min-height:48px;display:flex;align-items:center;gap:.55rem;color:#bd9c4d}.idle-message strong,.idle-message small{display:block}.idle-message strong{color:#c6b17a;font-size:.65rem;font-weight:500}.idle-message small{margin-top:.13rem;color:#6e8190;font-size:.55rem}
  .build-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:.45rem;padding:.65rem .8rem}.build-actions button{min-height:48px;display:grid;grid-template-columns:22px minmax(0,1fr);gap:.45rem;align-items:center;padding:.4rem .55rem;border:1px solid rgba(61,160,209,.25);background:rgba(5,27,42,.72);color:#58caff;text-align:left;font:inherit;cursor:pointer}.build-actions button:hover:not(:disabled){border-color:#48caff;background:rgba(10,49,72,.86)}.build-actions button:disabled{opacity:.3;cursor:not-allowed}.build-actions span,.build-actions strong,.build-actions small{display:block;min-width:0}.build-actions strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#c7dbe6;font-size:.64rem;font-weight:500}.build-actions small{margin-top:.12rem;color:#69899d;font-size:.53rem;line-height:1.25}.empty{min-height:250px;display:grid;place-content:center;justify-items:center;gap:.45rem;border:1px solid rgba(58,154,207,.2);background:rgba(4,16,28,.75);color:#54c8f7;text-align:center}.empty h2{margin:.3rem 0 0;color:#dcebf3;font-size:.92rem;font-weight:500}.empty p{margin:0;color:#708a9b;font-size:.68rem}
  @media(max-width:1100px){.view-header{display:grid}.planet-summary{justify-content:flex-start}.planet-summary span{text-align:left}.metrics{grid-template-columns:repeat(3,1fr)}.metrics>div:nth-child(3){border-right:0}.metrics>div:nth-child(-n+3){border-bottom:1px solid rgba(57,132,173,.12)}.build-actions{grid-template-columns:1fr}.toolbar{display:grid}.toolbar p{text-align:left}}
  @media(max-width:720px){.planets-view{padding:.8rem}.planet-main{align-items:flex-start}.metrics{grid-template-columns:repeat(2,1fr)}.metrics>div{border-bottom:1px solid rgba(57,132,173,.12)}.metrics>div:nth-child(odd){border-right:1px solid rgba(57,132,173,.12)}.metrics>div:nth-child(even){border-right:0}.planet-summary{gap:.8rem}.filters{overflow-x:auto}.toolbar p{display:none}.queue-item{grid-template-columns:28px minmax(0,1fr) 34px 34px}}
</style>
