<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import type { PreviousTurnReport, StarSystem } from '../types';

  export let report: PreviousTurnReport | null = null;
  export let systems: StarSystem[] = [];
  export let onOpenSystem: (system: StarSystem) => void = () => {};
  export let onOpenOrders: () => void = () => {};
  export let onOpenResearch: () => void = () => {};
  export let onOpenDesigns: () => void = () => {};

  $: data = report?.data ?? null;
  $: movements = data?.movements ?? [];
  $: colonizations = data?.colonizations ?? [];
  $: productions = data?.productions ?? [];
  $: installationUpgradesCompleted = data?.installation_upgrades_completed ?? [];
  $: designsCreated = data?.designs_created ?? [];
  $: researchCompleted = data?.research_completed ?? [];
  $: researchProgress = data?.research_progress ?? null;
  $: sightings = data?.sightings ?? [];
  $: warnings = data?.warnings ?? [];
  $: eventCount = movements.length + colonizations.length + productions.length + installationUpgradesCompleted.length + designsCreated.length + researchCompleted.length + sightings.length;

  function system(id: string): StarSystem | null {
    return systems.find((entry) => entry.id === id) ?? null;
  }

  function systemName(id: string): string {
    return system(id)?.name ?? id;
  }

  function fleetName(id: string): string {
    return systems.flatMap((entry) => entry.fleets).find((fleet) => fleet.id === id)?.name ?? id;
  }

  function warningSystem(warning: string): StarSystem | null {
    const needle = warning.toLocaleLowerCase('en-US');

    // Prefer explicit system id/name references in the engine warning.
    const direct = systems.find((entry) =>
      needle.includes(entry.id.toLocaleLowerCase('en-US'))
      || needle.includes(entry.name.toLocaleLowerCase('en-US'))
    );
    if (direct) return direct;

    // Fleet validation warnings usually mention the fleet id. If the fleet is
    // still known, take the player directly to its current system.
    for (const entry of systems) {
      const fleet = entry.fleets.find((candidate) =>
        needle.includes(candidate.id.toLocaleLowerCase('en-US'))
        || needle.includes(candidate.name.toLocaleLowerCase('en-US'))
      );
      if (fleet) return entry;
    }

    return null;
  }

  function open(id: string): void {
    const entry = system(id);
    if (entry) onOpenSystem(entry);
  }
</script>

<section class="report-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">After-action intelligence</p>
      <h1>Turn report</h1>
      <p class="intro">What happened when the previous turn was processed. This report is player-specific: it contains your resolved orders, warnings and information your empire is allowed to know.</p>
    </div>
    {#if report}
      <div class="summary">
        <span><strong>Turn {report.turn_number}</strong><small>{report.year ? `year ${report.year}` : 'resolved'}</small></span>
        <span class:warning={warnings.length > 0}><strong>{warnings.length}</strong><small>warnings</small></span>
        <span><strong>{eventCount}</strong><small>events</small></span>
      </div>
    {/if}
  </header>

  {#if !report}
    <div class="empty panel-cut"><Icon name="report" size={42}/><h2>No previous turn report</h2><p>The first report appears after a turn has been processed.</p></div>
  {:else}
    {#if eventCount === 0 && warnings.length === 0 && !researchProgress}
      <section class="quiet-turn panel-cut"><Icon name="report" size={22}/><div><strong>Quiet turn</strong><p>No movement, production, ship design, installation upgrade, colonization, research completion or sensor-contact changes were recorded for your empire.</p></div></section>
    {/if}

    {#if warnings.length > 0}
      <section class="attention panel-cut">
        <header><Icon name="report" size={21}/><span><strong>Requires attention</strong><small>Orders that could not be completed or need review.</small></span></header>
        <div class="events">
          {#each warnings as warning}
            {@const target = warningSystem(warning)}
            <article class="event warning-event">
              <Icon name="report" size={17}/>
              <div><strong>Action required</strong><p>{warning}</p></div>
              {#if target}
                <button class="attention-action" onclick={() => open(target.id)}>Open {target.name}</button>
              {:else}
                <button class="attention-action" onclick={onOpenOrders}>Review orders</button>
              {/if}
            </article>
          {/each}
        </div>
      </section>
    {/if}

    <section class="report-section panel-cut">
      <div class="section-title"><span><Icon name="target" size={18}/><strong>Sensor contacts</strong></span><em>{sightings.length}</em></div>
      {#if sightings.length}
        <div class="events">
          {#each sightings as sighting}
            <article class="event" class:contact-lost={sighting.type === 'lost'}>
              <Icon name="target" size={18}/>
              <div>
                <strong>{sighting.type === 'detected' ? 'Enemy contact detected' : 'Sensor contact lost'} · {sighting.fleetName ?? sighting.fleetId}</strong>
                <p>{sighting.systemId ? systemName(sighting.systemId) : 'Unknown location'}{typeof sighting.ships === 'number' && sighting.ships > 0 ? ` · ${sighting.ships.toLocaleString('en-US')} ships at last contact` : ''}</p>
              </div>
              {#if sighting.systemId && system(sighting.systemId)}<button onclick={() => open(sighting.systemId ?? '')}>Open system</button>{/if}
            </article>
          {/each}
        </div>
      {:else}<p class="none">No enemy contacts changed.</p>{/if}
    </section>

    <section class="report-section panel-cut">
      <div class="section-title"><span><Icon name="fleet" size={18}/><strong>Fleet movement</strong></span><em>{movements.length}</em></div>
      {#if movements.length}
        <div class="events">
          {#each movements as movement}
            <article class="event">
              <Icon name="fleet" size={18}/>
              <div><strong>{fleetName(movement.fleetId)}</strong><p>{systemName(movement.fromSystemId)} → {systemName(movement.toSystemId)}</p></div>
              <button onclick={() => open(movement.toSystemId)}>Open system</button>
            </article>
          {/each}
        </div>
      {:else}<p class="none">No fleets moved.</p>{/if}
    </section>

    <section class="report-section panel-cut">
      <div class="section-title"><span><Icon name="planet" size={18}/><strong>Colonization</strong></span><em>{colonizations.length}</em></div>
      {#if colonizations.length}
        <div class="events">
          {#each colonizations as colony}
            <article class="event success-event">
              <Icon name="planet" size={18}/>
              <div><strong>{systemName(colony.systemId)} colonized</strong><p>{colony.population ? `${colony.population.toFixed(2)}B initial population` : 'New colony established'} · {fleetName(colony.fleetId)}</p></div>
              <button onclick={() => open(colony.systemId)}>Open colony</button>
            </article>
          {/each}
        </div>
      {:else}<p class="none">No colonies were established.</p>{/if}
    </section>

    <section class="report-section panel-cut design-section">
      <div class="section-title"><span><Icon name="build" size={18}/><strong>Ship designs created</strong></span><em>{designsCreated.length}</em></div>
      {#if designsCreated.length}
        <div class="events">
          {#each designsCreated as design}
            <article class="event design-event">
              <Icon name="fleet" size={18}/>
              <div><strong>{design.name} · generation {design.generation}</strong><p>SPD {design.stats.movementRange} · SEN {design.stats.sensorRange} · ATK {design.stats.attack} · DEF {design.stats.defense} · {design.industryCost.toLocaleString('en-US')} industry per batch</p></div>
              <button onclick={onOpenDesigns}>Open designs</button>
            </article>
          {/each}
        </div>
      {:else}<p class="none">No new ship generations were created.</p>{/if}
    </section>

    <section class="report-section panel-cut research-section">
      <div class="section-title"><span><Icon name="research" size={18}/><strong>Research</strong></span><em>{researchCompleted.length}</em></div>
      {#if researchCompleted.length}
        <div class="events">
          {#each researchCompleted as technology}
            <article class="event research-event">
              <Icon name="research" size={18}/>
              <div><strong>{technology.name} completed</strong><p>{technology.effect} · Tier {technology.tier} · {technology.cost.toLocaleString('en-US')} RP</p></div>
              <button onclick={technology.kind === 'hardware' ? onOpenDesigns : onOpenResearch}>{technology.kind === 'hardware' ? 'Open designs' : 'Open research'}</button>
            </article>
          {/each}
        </div>
      {:else if researchProgress}
        <div class="events">
          <article class="event research-progress-event">
            <Icon name="research" size={18}/>
            <div><strong>{researchProgress.name}</strong><p>{researchProgress.progress.toLocaleString('en-US')} / {researchProgress.cost.toLocaleString('en-US')} RP · +{researchProgress.income.toLocaleString('en-US')} RP generated this turn</p></div>
            <button onclick={onOpenResearch}>Open research</button>
          </article>
        </div>
      {:else}<p class="none">No active research project was advanced.</p>{/if}
    </section>

    <section class="report-section panel-cut">
      <div class="section-title"><span><Icon name="build" size={18}/><strong>Installation upgrades completed</strong></span><em>{installationUpgradesCompleted.length}</em></div>
      {#if installationUpgradesCompleted.length}
        <div class="events">
          {#each installationUpgradesCompleted as upgrade}
            <article class="event upgrade-event">
              <Icon name={upgrade.family === 'defense_grid' ? 'shield' : upgrade.family === 'deep_space_array' ? 'target' : 'industry'} size={18}/>
              <div><strong>{upgrade.fromName} → {upgrade.toName}</strong><p>{systemName(upgrade.systemId)} · {upgrade.industryCost.toLocaleString('en-US')} industry · completed turn {upgrade.completedTurn}</p></div>
              <button onclick={() => open(upgrade.systemId)}>Open colony</button>
            </article>
          {/each}
        </div>
      {:else}<p class="none">No installation upgrades completed.</p>{/if}
    </section>

    <section class="report-section panel-cut">
      <div class="section-title"><span><Icon name="build" size={18}/><strong>Production completed</strong></span><em>{productions.length}</em></div>
      {#if productions.length}
        <div class="events">
          {#each productions as production}
            <article class="event success-event">
              <Icon name={production.productionKind === 'ship' || production.modelId?.startsWith('scout-') ? 'fleet' : production.modelId?.startsWith('defense_grid') ? 'shield' : production.modelId?.startsWith('deep_space_array') ? 'target' : 'industry'} size={18}/>
              <div><strong>{production.item}</strong><p>{systemName(production.systemId)} · {production.industryCost.toLocaleString('en-US')} industry</p></div>
              <button onclick={() => open(production.systemId)}>Open system</button>
            </article>
          {/each}
        </div>
      {:else}<p class="none">No production completed.</p>{/if}
    </section>

    <section class="intel-note panel-cut">
      <Icon name="target" size={19}/><p><strong>Fog of war applies to reports.</strong> Future combat, diplomacy and enemy-contact events will only appear when your sensors, fleets or colonies could observe them.</p>
    </section>
  {/if}
</section>

<style>
  .report-view{height:100%;overflow:auto;box-sizing:border-box;padding:1.35rem;background:radial-gradient(circle at 45% 12%,rgba(18,93,129,.13),transparent 44%),#030912;color:#8ea5b5}.view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}.eyebrow{margin:0 0 .3rem;color:#43c5ff;text-transform:uppercase;letter-spacing:.14em;font-size:.65rem}h1{margin:0;color:#edf9ff;font-size:1.65rem;font-weight:500;letter-spacing:.08em}.intro{max-width:760px;margin:.5rem 0 0;color:#7f98aa;font-size:.76rem;line-height:1.5}.summary{display:flex;gap:1.1rem;flex-wrap:wrap;justify-content:flex-end}.summary span{text-align:right}.summary strong,.summary small{display:block}.summary strong{color:#e4f4fb;font-size:.95rem}.summary small{margin-top:.12rem;color:#648296;text-transform:uppercase;font-size:.54rem;letter-spacing:.08em}.summary .warning strong{color:#efb55a}
  .panel-cut{border:1px solid rgba(58,154,207,.22);background:linear-gradient(180deg,rgba(5,21,35,.96),rgba(3,13,23,.96))}.attention{margin-bottom:.75rem;border-color:rgba(229,155,71,.42);box-shadow:inset 3px 0 #df9b48}.attention>header{display:flex;gap:.65rem;align-items:center;padding:.75rem .85rem;color:#efb55a;border-bottom:1px solid rgba(229,155,71,.18)}.attention header span,.attention header strong,.attention header small{display:block}.attention header strong{font-size:.72rem;text-transform:uppercase;letter-spacing:.07em}.attention header small{margin-top:.15rem;color:#90785e;font-size:.58rem;font-weight:400;text-transform:none;letter-spacing:0}.report-section{margin-bottom:.7rem}.section-title{min-height:46px;display:flex;align-items:center;justify-content:space-between;padding:0 .8rem;border-bottom:1px solid rgba(58,154,207,.16)}.section-title>span{display:flex;align-items:center;gap:.5rem;color:#55caff}.section-title strong{color:#cfe3ed;font-size:.69rem;font-weight:500;text-transform:uppercase;letter-spacing:.07em}.section-title em{min-width:24px;padding:.12rem .35rem;border:1px solid rgba(70,181,231,.24);color:#6fcff5;font-size:.6rem;font-style:normal;text-align:center}.events{display:grid}.event{min-height:58px;display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:.55rem;align-items:center;padding:.55rem .75rem;border-bottom:1px solid rgba(55,126,165,.12);color:#58caff}.event:last-child{border-bottom:0}.event div,.event strong,.event p{display:block}.event strong{color:#d6e7ef;font-size:.69rem;font-weight:500}.event p{margin:.16rem 0 0;color:#708a9b;font-size:.6rem;line-height:1.35}.event button{min-height:30px;padding:0 .55rem;border:1px solid rgba(64,169,221,.28);background:rgba(8,39,58,.7);color:#64caf4;font:inherit;font-size:.57rem;cursor:pointer}.event button:hover{border-color:#4dcaff;color:#e5f8ff}.warning-event{color:#e7a857}.warning-event strong{color:#efbd78}.warning-event p{color:#d1a06c}.warning-event .attention-action{border-color:rgba(229,155,71,.42);background:rgba(66,40,12,.62);color:#efbd78}.success-event{color:#71cf96}.design-event{color:#74d6ff;box-shadow:inset 2px 0 rgba(92,202,244,.5)}.upgrade-event{color:#e4c35e;box-shadow:inset 2px 0 rgba(228,195,94,.48)}.upgrade-event strong{color:#e7dcad}.research-event{color:#75d9ff;box-shadow:inset 2px 0 rgba(86,205,255,.55)}.research-progress-event{color:#6cb7d7}.contact-lost{opacity:.7}.contact-lost strong{color:#9baab3}.quiet-turn{display:flex;align-items:center;gap:.65rem;margin-bottom:.75rem;padding:.8rem;color:#6dcdf4}.quiet-turn strong{color:#c9dce6;font-size:.72rem}.quiet-turn p{margin:.14rem 0 0;color:#718a9b;font-size:.61rem}.none{margin:0;padding:.85rem;color:#687f90;font-size:.65rem}.intel-note{display:flex;gap:.6rem;align-items:flex-start;padding:.7rem .8rem;color:#63c9f3}.intel-note p{margin:0;color:#718b9c;font-size:.62rem;line-height:1.5}.intel-note strong{color:#a9d8ec;font-weight:500}.empty{min-height:280px;display:grid;place-content:center;justify-items:center;gap:.45rem;color:#58caff;text-align:center}.empty h2{margin:.35rem 0 0;color:#dcebf3;font-size:.95rem;font-weight:500}.empty p{margin:0;color:#708a9b;font-size:.68rem}
  @media(max-width:800px){.view-header{display:grid}.summary{justify-content:flex-start}.summary span{text-align:left}.event{grid-template-columns:26px minmax(0,1fr)}.event button{grid-column:2;justify-self:start}.report-view{padding:.8rem}}
</style>
