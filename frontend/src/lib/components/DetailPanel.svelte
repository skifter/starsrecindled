<script lang="ts">
  import Icon from './Icon.svelte';
  import PlanetSensorVisual from './PlanetSensorVisual.svelte';
  import { OWNER_COLORS, ownerForPlayerId } from '../player-colors';
  import type { AccountTurnStatusPlayer, FleetSummary, ModelCatalog, ProductionOrder, StarSystem, TechnologyModel } from '../types';

  export let system: StarSystem;
  export let players: AccountTurnStatusPlayer[] = [];
  export let currentPlayerId = 0;
  export let selectedFleetId = '';
  export let canBuild = false;
  export let modelCatalog: ModelCatalog | null = null;
  export let productionOrders: ProductionOrder[] = [];
  export let onBuild: (item: string, modelId?: string) => void;
  export let onRemoveBuild: (item: string) => void = () => {};
  export let onSelectFleet: (fleet: FleetSummary) => void = () => {};
  export let onWaypointFleet: (fleet: FleetSummary) => void = () => {};

  const ownerName: Record<string, string> = {
    player: 'Blue player', neutral: 'Unclaimed', crimson: 'Red player', violet: 'Violet player', amber: 'Amber player'
  };

  $: isYours = system.ownerPlayerId === currentPlayerId;
  $: isStale = system.visibilityState === 'explored';
  $: playerIds = players.map((player) => player.id);
  $: systemColor = OWNER_COLORS[system.owner];
  $: sensorRange = system.ownerPlayerId === null ? 0 : Math.max(1, Math.min(4, Math.round(system.sensorRange ?? 1)));
  $: effectiveSensorRange = sensorRange;
  $: currentScout = modelCatalog?.designs.find((design) => design.current) ?? modelCatalog?.designs[0] ?? null;
  $: buildModels = ['defense_grid', 'orbital_factory', 'deep_space_array'].map((family) => preferredInstallation(family)).filter((model): model is TechnologyModel => model !== null);

  function preferredInstallation(family: string): TechnologyModel | null {
    return [...(modelCatalog?.installations ?? [])]
      .filter((model) => model.family === family && model.unlocked)
      .sort((a, b) => b.version - a.version)[0] ?? null;
  }

  function installedFamily(family: string): boolean {
    return (system.installations ?? []).some((installation) => installation.family === family);
  }

  function modelIcon(model: TechnologyModel): string {
    if (model.family === 'defense_grid') return 'shield';
    if (model.family === 'deep_space_array') return 'target';
    return 'industry';
  }

  function fleetColor(fleet: FleetSummary): string {
    return fleet.ownerPlayerId ? OWNER_COLORS[ownerForPlayerId(fleet.ownerPlayerId, playerIds)] : OWNER_COLORS.neutral;
  }

  function colonyCapacity(fleet: FleetSummary): number {
    if (typeof fleet.colonizationCapacity === 'number') return fleet.colonizationCapacity;
    return fleet.role === 'Exploration fleet' ? 1 : 0;
  }
</script>

<aside class="detail-panel" class:stale={isStale} style={`--system-owner-color:${systemColor}`}>
  <header>
    <div class="system-title"><span class="system-star">✦</span><div><h2>{system.name}</h2><p>{system.isCapital ? 'Core world' : system.className}</p></div></div>
    <div class="owner-state" class:yours={isYours} class:unclaimed={system.ownerPlayerId === null}>
      {isYours ? (system.isCapital ? 'YOUR CAPITAL' : 'YOUR COLONY') : system.ownerPlayerId === null ? 'UNCLAIMED' : `${system.ownerLabel ?? ownerName[system.owner]} COLONY`}
    </div>
  </header>

  <div class="intel-status" class:stale={isStale}>
    <Icon name="target" size={16}/>
    <span>
      <strong>{isStale ? 'LAST KNOWN INTELLIGENCE' : 'LIVE SENSOR CONTACT'}</strong>
      <small>
        {isStale
          ? `Last seen on turn ${system.lastSeenTurn ?? '?'}. Values below may have changed.`
          : `Visible now · confirmed on turn ${system.lastSeenTurn ?? '?'}.`}
      </small>
    </span>
  </div>

  <div class="world-art" class:neutral={system.ownerPlayerId === null} style={`--world-hue:${system.owner === 'player' ? '198' : system.owner === 'crimson' ? '8' : system.owner === 'violet' ? '280' : '42'}`}>
    <div class="sensor-world"><PlanetSensorVisual color={systemColor} sensorRange={effectiveSensorRange} size={116} neutral={system.ownerPlayerId === null} label={system.name}/></div><div class="horizon"></div><div class="city"><i></i><i></i><i></i><i></i><i></i></div>
    <span>{system.ownerLabel ?? ownerName[system.owner]}</span>
  </div>

  <div class="summary-grid">
    <div><Icon name="user" size={19}/><span><small>Population</small><strong>{system.population.toFixed(1)} / {system.capacity.toFixed(1)}B</strong></span></div>
    <div><span class="happy">☺</span><span><small>Happiness</small><strong>{system.happiness}%</strong></span></div>
    <div><Icon name="target" size={19}/><span><small>Sensor range</small><strong>{effectiveSensorRange} hop{effectiveSensorRange === 1 ? '' : 's'}</strong></span></div>
  </div>

  <section class="panel-section">
    <h3>Resources per turn</h3>
    <div class="resources">
      {#each system.resources as resource}
        <div title={resource.label}>
          <Icon name={resource.icon} size={17}/>
          <span><strong>{resource.value.toLocaleString('en-US')}</strong><small>+{resource.income}</small></span>
        </div>
      {/each}
    </div>
  </section>

  <section class="panel-section">
    <div class="production-heading"><h3>Build queue · this turn</h3><span>{productionOrders.reduce((sum, order) => sum + order.quantity, 0)} queued</span></div>
    {#if productionOrders.length}
      <div class="queue">
        {#each productionOrders as item}
          <div class="queue-item draft">
            <Icon name={item.productionKind === 'ship' || item.modelId?.startsWith('scout-') ? 'fleet' : item.modelId?.startsWith('defense_grid') ? 'shield' : item.modelId?.startsWith('deep_space_array') ? 'target' : 'industry'} size={17}/>
            <span><strong>{item.item}</strong><small>Completes when the turn is processed</small></span><em>×{item.quantity}</em>
            <button disabled={!canBuild} title={`Remove one ${item.item}`} onclick={() => onRemoveBuild(item.item)}>−1</button>
          </div>
        {/each}
      </div>
    {:else}
      <p class="empty idle-production">This system is not building anything this turn.</p>
    {/if}
    <div class="installed-models">
      {#each (system.installations ?? []) as installation}
        <span><strong>{installation.name}</strong><small>v{installation.version}</small></span>
      {/each}
      {#if (system.installations ?? []).length === 0}<span><strong>Base colony</strong><small>No registered installations</small></span>{/if}
    </div>
    <div class="build-options">
      {#if currentScout}
        <button disabled={!canBuild} onclick={() => onBuild(currentScout.name, currentScout.id)}><Icon name="fleet" size={15}/><span><strong>{currentScout.name}</strong><small>{currentScout.industryCost} industry · {currentScout.batchSize} ships · {currentScout.stats.movementRange} hop</small></span></button>
      {/if}
      {#each buildModels as model}
        <button disabled={!canBuild || installedFamily(model.family)} onclick={() => onBuild(model.name, model.id)} title={installedFamily(model.family) ? 'Existing model remains installed; explicit upgrade arrives in 0.7.2' : `${model.stats.industryCost ?? 0} industry`}>
          <Icon name={modelIcon(model)} size={15}/><span><strong>{model.name}</strong><small>{installedFamily(model.family) ? 'Upgrade path registered · 0.7.2' : `${model.stats.industryCost ?? 0} industry · new installation`}</small></span>
        </button>
      {/each}
    </div>
    {#if !canBuild}<p class="build-hint">{isYours ? 'Reopen the turn to add production.' : 'Production is only available in your colonies.'}</p>{/if}
  </section>

  <section class="panel-section split-title">
    <h3>Defenses</h3><span>{system.defenses.toLocaleString('en-US')}</span>
    <div class="defense-row"><Icon name="shield" size={22}/><div>{#each Array(Math.min(7, Math.max(1, Math.ceil(system.defenses / 400)))) as _}<i></i>{/each}</div></div>
  </section>

  <section class="panel-section fleets-section">
    <div class="section-heading"><h3>Fleets in system</h3><span>{system.fleets.length}</span></div>
    {#if system.fleets.length}
      <div class="fleet-list">
        {#each system.fleets as fleet}
          {@const ownFleet = fleet.ownerPlayerId === currentPlayerId}
          <article class="fleet-card" class:selected={fleet.id === selectedFleetId} class:opponent={!ownFleet} style={`--fleet-color:${fleetColor(fleet)}`}>
            <button class="fleet-main" onclick={() => onSelectFleet(fleet)} disabled={!ownFleet} title={ownFleet ? `Select ${fleet.name}` : `${fleet.ownerLabel ?? 'Other player'} fleet`}>
              <span class="fleet-icon"><Icon name="fleet" size={19}/></span>
              <span class="fleet-copy">
                <strong>{fleet.name}</strong>
                <small>{fleet.ownerLabel ?? (ownFleet ? 'You' : 'Other player')} · {fleet.role}</small>
                {#if fleet.composition?.length}<small class="design-line">{fleet.composition.map((entry) => `${entry.designName} ×${entry.quantity}`).join(' · ')}</small>{/if}
                {#if fleet.destination}<small class="destination">→ {fleet.destination}</small>{/if}
                {#if colonyCapacity(fleet) > 0}<small class="colony">Colony module ×{colonyCapacity(fleet)}</small>{/if}
              </span>
              <span class="ships"><strong>{fleet.ships.toLocaleString('en-US')}</strong><small>ships</small></span>
            </button>
            {#if ownFleet}
              <button class="route-action" onclick={() => onWaypointFleet(fleet)} title={`Set waypoint for ${fleet.name}`}><Icon name="target" size={15}/><span>Set waypoint</span></button>
            {/if}
          </article>
        {/each}
      </div>
    {:else}<p class="empty">No fleets present.</p>{/if}
  </section>

  <p class="description">{system.description}</p>
</aside>

<style>
  .detail-panel{height:100%;overflow-y:auto;background:linear-gradient(180deg,rgba(5,19,33,.98),rgba(2,10,18,.98));border-left:1px solid rgba(55,162,218,.3);color:#b8cad7;scrollbar-color:#225d7d #07121d}
  header{min-height:64px;display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:0 1rem;border-bottom:1px solid rgba(64,169,224,.22)}
  .intel-status{display:flex;align-items:center;gap:.55rem;padding:.55rem .8rem;border-bottom:1px solid rgba(69,178,232,.18);background:rgba(7,37,55,.36);color:#63cfff}.intel-status span,.intel-status strong,.intel-status small{display:block}.intel-status strong{color:#85dcff;font-size:.58rem;letter-spacing:.08em}.intel-status small{margin-top:.12rem;color:#708fa2;font-size:.55rem;line-height:1.35}.intel-status.stale{border-bottom-color:rgba(112,137,153,.2);background:rgba(31,42,50,.38);color:#8295a2}.intel-status.stale strong{color:#a4afb6}.intel-status.stale small{color:#778691}.detail-panel.stale .world-art{filter:saturate(.45) brightness(.68)}
  .system-title{display:flex;align-items:center;gap:.65rem;min-width:0}.system-title>div{min-width:0}.system-title h2{margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#f0f9ff;font-size:1.2rem;letter-spacing:.08em;text-transform:uppercase}.system-title p{margin:.15rem 0 0;color:#8199aa;font-size:.73rem}.system-star{color:var(--system-owner-color);font-size:1.6rem;text-shadow:0 0 12px var(--system-owner-color)}
  .owner-state{flex:none;padding:.28rem .45rem;border:1px solid var(--system-owner-color);color:var(--system-owner-color);background:rgba(92,20,18,.18);font-size:.54rem;font-weight:700;letter-spacing:.06em;white-space:nowrap}.owner-state.yours{background:rgba(13,73,101,.3)}.owner-state.unclaimed{border-style:dashed;color:#b7cbd8;background:rgba(60,74,84,.18)}
  .world-art{height:146px;position:relative;overflow:hidden;background:radial-gradient(circle at 72% 28%,hsla(var(--world-hue),75%,72%,.6) 0 4%,transparent 18%),linear-gradient(180deg,hsl(var(--world-hue),55%,31%),hsl(var(--world-hue),55%,10%) 70%);border-bottom:1px solid rgba(62,164,218,.22)}.world-art::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 28% 20%,rgba(255,255,255,.7) 0 .6px,transparent .9px),radial-gradient(circle at 58% 33%,rgba(255,255,255,.6) 0 .5px,transparent .8px);background-size:39px 31px,53px 47px;opacity:.6}.world-art.neutral{filter:saturate(.35)}.sensor-world{position:absolute;right:12px;top:4px;width:122px;height:122px;display:grid;place-items:center;z-index:2}.horizon{position:absolute;left:-10%;right:-10%;height:72px;bottom:-38px;border-radius:50% 50% 0 0;background:linear-gradient(180deg,hsl(var(--world-hue),42%,32%),#071019);box-shadow:0 -4px 20px hsla(var(--world-hue),70%,60%,.35)}.city{position:absolute;bottom:26px;left:28px;right:100px;display:flex;gap:9px;align-items:end}.city i{width:14px;height:38px;background:linear-gradient(90deg,#102b3c,#32637a,#0b1f2e);clip-path:polygon(35% 0,65% 0,75% 25%,100% 30%,100% 100%,0 100%,0 30%,25% 25%);box-shadow:0 0 8px hsla(var(--world-hue),90%,65%,.4)}.city i:nth-child(2){height:72px}.city i:nth-child(3){height:52px}.city i:nth-child(4){height:82px}.city i:nth-child(5){height:45px}.world-art>span{position:absolute;left:10px;bottom:8px;font-size:.65rem;color:#d5efff;text-transform:uppercase;letter-spacing:.12em}
  .summary-grid{display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid rgba(62,164,218,.2)}.summary-grid>div{min-height:54px;display:flex;align-items:center;gap:.6rem;padding:0 .8rem;border-right:1px solid rgba(62,164,218,.16);color:#75cfff}.summary-grid small,.summary-grid strong{display:block}.summary-grid small{color:#7991a2;font-size:.65rem}.summary-grid strong{color:#dcebf4;font-size:.8rem;margin-top:.1rem}.happy{color:#8cdd60;font-size:1.45rem}
  .panel-section{padding:.8rem 1rem;border-bottom:1px solid rgba(62,164,218,.18)}.panel-section h3{margin:0 0 .65rem;color:#49c5ff;text-transform:uppercase;letter-spacing:.08em;font-size:.68rem}.resources{display:flex;justify-content:space-between;gap:.4rem}.resources div{display:flex;align-items:center;gap:.35rem;color:#74d0fb}.resources div span{display:block}.resources strong,.resources small{display:block}.resources strong{color:#dae9f2;font-size:.72rem}.resources small{margin-top:.05rem;color:#6da6c2;font-size:.56rem}
  .production-heading{display:flex;align-items:center;justify-content:space-between;gap:.5rem}.production-heading h3{margin-bottom:.65rem}.production-heading span{margin-bottom:.65rem;color:#6d899b;font-size:.55rem;text-transform:uppercase;letter-spacing:.06em}.queue{display:grid;gap:.45rem}.queue-item{display:grid;grid-template-columns:20px minmax(0,1fr) 28px 30px;gap:.45rem;align-items:center;color:#5fcaff}.queue-item span strong,.queue-item span small{display:block}.queue-item span strong{color:#bcd0dc;font-size:.68rem;font-weight:500}.queue-item span small{margin-top:.12rem;color:#657f90;font-size:.53rem}.queue-item em{color:#e3c466;font-style:normal;font-size:.68rem;text-align:right}.queue-item button{height:27px;border:1px solid rgba(196,104,78,.3);background:rgba(67,24,18,.45);color:#e2a18d;font:inherit;font-size:.58rem;cursor:pointer}.queue-item button:disabled{opacity:.35;cursor:not-allowed}.idle-production{color:#b59b61}
  .installed-models{display:flex;gap:.3rem;flex-wrap:wrap;margin:.55rem 0}.installed-models span{padding:.28rem .36rem;border:1px solid rgba(59,145,188,.18);background:rgba(5,24,38,.55)}.installed-models strong,.installed-models small{display:block}.installed-models strong{color:#afc6d2;font-size:.54rem;font-weight:500}.installed-models small{margin-top:.08rem;color:#677f90;font-size:.45rem}.build-options{display:grid;gap:.38rem;margin-top:.65rem}.build-options button{min-height:42px;display:grid;grid-template-columns:20px 1fr;gap:.45rem;align-items:center;padding:.4rem .55rem;border:1px solid rgba(61,160,209,.25);background:rgba(5,27,42,.72);color:#58caff;text-align:left;cursor:pointer}.build-options button:hover:not(:disabled){border-color:#48caff;background:rgba(10,49,72,.86)}.build-options button:disabled{opacity:.35;cursor:not-allowed}.build-options span,.build-options strong,.build-options small{display:block}.build-options strong{color:#c7dbe6;font-size:.67rem;font-weight:500}.build-options small{margin-top:.12rem;color:#69899d;font-size:.56rem}.build-hint{margin:.5rem 0 0;color:#71899b;font-size:.62rem;line-height:1.4}
  .split-title{display:grid;grid-template-columns:1fr auto}.split-title h3{grid-column:1}.split-title>span{grid-column:2;color:#dcebf3;font-size:.75rem}.defense-row{grid-column:1/-1;display:flex;gap:.7rem;align-items:center;color:#8dcdf0}.defense-row div{display:flex;gap:5px}.defense-row i{width:19px;height:12px;border:1px solid #568eb0;background:linear-gradient(180deg,#2d6689,#102334);clip-path:polygon(40% 0,60% 0,70% 35%,100% 50%,85% 100%,15% 100%,0 50%,30% 35%)}
  .fleets-section{padding-left:.75rem;padding-right:.75rem;background:linear-gradient(180deg,rgba(8,31,47,.4),rgba(3,14,24,.1))}.section-heading{display:flex;align-items:center;justify-content:space-between;padding:0 .25rem}.section-heading h3{margin-bottom:.5rem}.section-heading span{min-width:22px;padding:.12rem .35rem;border:1px solid rgba(76,195,244,.25);color:#67d1fb;text-align:center;font-size:.58rem}.fleet-list{display:grid;gap:.45rem}.fleet-card{border:1px solid rgba(66,156,202,.26);border-left:3px solid var(--fleet-color);background:rgba(4,20,32,.82);transition:.15s}.fleet-card.selected{border-color:#ffd05c;border-left-color:#ffd05c;box-shadow:inset 0 0 14px rgba(255,208,92,.06)}.fleet-card.opponent{opacity:.82}.fleet-main{width:100%;min-height:61px;display:grid;grid-template-columns:30px minmax(0,1fr) 52px;gap:.45rem;align-items:center;padding:.45rem .5rem;border:0;background:transparent;color:inherit;text-align:left}.fleet-main:not(:disabled){cursor:pointer}.fleet-main:disabled{cursor:default}.fleet-icon{width:28px;height:28px;display:grid;place-items:center;border:1px solid color-mix(in srgb,var(--fleet-color) 55%,transparent);color:var(--fleet-color);background:color-mix(in srgb,var(--fleet-color) 8%,transparent)}.fleet-copy,.fleet-copy strong,.fleet-copy small,.ships strong,.ships small{display:block;min-width:0}.fleet-copy strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#d8e9f2;font-size:.72rem;font-weight:600}.fleet-copy small{margin-top:.14rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#718fa1;font-size:.57rem}.fleet-copy .destination{color:#7fd7ff}.fleet-copy .colony{color:#d6b25a;text-transform:uppercase;letter-spacing:.04em}.ships{text-align:right}.ships strong{color:#f1f8fb;font-size:.75rem}.ships small{margin-top:.1rem;color:#678496;font-size:.52rem;text-transform:uppercase}.route-action{width:100%;min-height:31px;display:flex;align-items:center;justify-content:center;gap:.35rem;border:0;border-top:1px solid rgba(65,147,189,.18);background:rgba(7,36,53,.72);color:#58caff;font:inherit;font-size:.61rem;cursor:pointer}.route-action:hover{background:rgba(12,57,81,.9);color:#dff7ff}
  .empty{color:#71899b;font-size:.72rem;margin:.4rem 0}.description{margin:0;padding:1rem;color:#738b9d;font-size:.7rem;line-height:1.5}
</style>
