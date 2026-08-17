<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import type { ModelCatalog, PlayerOrders, PlayerResearchState, ResearchField, ResearchTechnology } from '../types';

  export let research: PlayerResearchState | null = null;
  export let catalog: ResearchTechnology[] = [];
  export let modelCatalog: ModelCatalog | null = null;
  export let orders: PlayerOrders;
  export let editableTurn = false;
  export let onResearch: (technologyId: string) => void = () => {};

  const fields: { id: ResearchField; label: string; icon: string; description: string }[] = [
    { id: 'propulsion', label: 'Propulsion', icon: 'fleet', description: 'Engine models and applied fuel technology.' },
    { id: 'sensors', label: 'Sensors', icon: 'target', description: 'New scanner and sensor-array hardware.' },
    { id: 'weapons', label: 'Weapons', icon: 'energy', description: 'Weapon models for future ship generations.' },
    { id: 'defenses', label: 'Defenses', icon: 'shield', description: 'Armor and planetary defense models.' },
    { id: 'industry', label: 'Industry', icon: 'industry', description: 'New generations of orbital factories.' }
  ];

  $: completed = new Set(research?.completed ?? []);
  $: selectedTechnologyId = orders.research?.[0]?.technologyId ?? orders.research?.[0]?.field ?? research?.activeTechnologyId ?? '';
  $: activeTechnology = catalog.find((technology) => technology.id === (research?.activeTechnologyId ?? '')) ?? null;
  $: selectedTechnology = catalog.find((technology) => technology.id === selectedTechnologyId) ?? null;
  $: currentProgress = activeTechnology ? progress(activeTechnology) : 0;
  $: currentPercent = activeTechnology ? Math.min(100, Math.round(currentProgress / activeTechnology.cost * 100)) : 0;
  $: stockpile = research?.stockpile ?? 0;
  $: income = research?.income ?? 0;
  $: completedCount = research?.completed.length ?? 0;

  function technologies(field: ResearchField): ResearchTechnology[] {
    return catalog
      .filter((technology) => technology.field === field)
      .sort((a, b) => a.tier - b.tier || a.cost - b.cost);
  }

  function progress(technology: ResearchTechnology): number {
    if (completed.has(technology.id)) return technology.cost;
    return Math.max(0, research?.progress[technology.id] ?? 0);
  }

  function prerequisiteNames(technology: ResearchTechnology): string {
    return technology.prerequisites
      .map((id) => catalog.find((candidate) => candidate.id === id)?.name ?? id)
      .join(', ');
  }

  function isAvailable(technology: ResearchTechnology): boolean {
    return !completed.has(technology.id)
      && technology.prerequisites.every((id) => completed.has(id));
  }

  function unlockName(id: string): string {
    return modelCatalog?.components.find((model) => model.id === id)?.name
      ?? modelCatalog?.installations.find((model) => model.id === id)?.name
      ?? id;
  }

  function choose(technology: ResearchTechnology): void {
    if (!editableTurn || !isAvailable(technology)) return;
    onResearch(technology.id);
  }
</script>

<section class="research-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">Scientific development</p>
      <h1>Research</h1>
      <p class="intro">Research unlocks new hardware generations; it does not magically retrofit existing ships or installations. Applied research is explicitly marked and may affect equipment already in service.</p>
    </div>
    <div class="research-summary">
      <span><strong>+{income.toLocaleString('en-US')}</strong><small>RP / turn</small></span>
      <span><strong>{stockpile.toLocaleString('en-US')}</strong><small>unspent RP</small></span>
      <span><strong>{completedCount}/{catalog.length}</strong><small>completed</small></span>
    </div>
  </header>

  <section class="active-project panel-cut">
    <div class="active-copy">
      <span class="active-icon"><Icon name="research" size={27}/></span>
      <div>
        <small>ACTIVE PROJECT</small>
        {#if activeTechnology}
          <strong>{activeTechnology.name}</strong>
          <p>{activeTechnology.effect}</p>
        {:else if selectedTechnology}
          <strong>{selectedTechnology.name}</strong>
          <p>Selected for this turn. Research begins when the turn is processed.</p>
        {:else}
          <strong>No active research</strong>
          <p>Research points will accumulate until a technology is selected.</p>
        {/if}
      </div>
    </div>
    {#if activeTechnology}
      <div class="active-progress">
        <div><span style={`width:${currentPercent}%`}></span></div>
        <small>{currentProgress.toLocaleString('en-US')} / {activeTechnology.cost.toLocaleString('en-US')} RP · {currentPercent}%</small>
      </div>
    {/if}
  </section>

  <section class="effects panel-cut">
    <div><Icon name="fleet" size={18}/><span><strong>{modelCatalog?.designs.find((design) => design.current)?.name ?? 'Scout Mk I'}</strong><small>current new-build design</small></span></div>
    <div><Icon name="fleet" size={18}/><span><strong>{modelCatalog?.designs.find((design) => design.current)?.stats.movementRange ?? 1} hop</strong><small>current design speed</small></span></div>
    <div><Icon name="target" size={18}/><span><strong>Range {modelCatalog?.designs.find((design) => design.current)?.stats.sensorRange ?? 1}</strong><small>current ship scanner</small></span></div>
    <div><Icon name="energy" size={18}/><span><strong>{research?.modifiers.fuelEfficiencyPercent ?? 0}%</strong><small>fleet fuel saving · applied</small></span></div>
    <div><Icon name="build" size={18}/><span><strong>Versioned</strong><small>existing hardware retained</small></span></div>
  </section>

  <div class="tree">
    {#each fields as field}
      <section class="field panel-cut">
        <header>
          <span><Icon name={field.icon} size={20}/></span>
          <div><strong>{field.label}</strong><small>Level {research?.levels[field.id] ?? 0} · {field.description}</small></div>
        </header>
        <div class="technologies">
          {#each technologies(field.id) as technology}
            {@const done = completed.has(technology.id)}
            {@const available = isAvailable(technology)}
            {@const selected = selectedTechnologyId === technology.id}
            {@const active = research?.activeTechnologyId === technology.id}
            {@const technologyProgress = progress(technology)}
            {@const percent = Math.min(100, Math.round(technologyProgress / technology.cost * 100))}
            <article class="technology" class:completed={done} class:locked={!done && !available} class:selected class:active>
              <div class="tech-main">
                <span class="tier">T{technology.tier}</span>
                <div class="tech-copy">
                  <strong>{technology.name}</strong>
                  <p>{technology.effect}</p>
                  <div class="tech-tags"><span class:applied={technology.kind === 'applied'}>{technology.kind === 'applied' ? 'APPLIED / RETROACTIVE' : 'HARDWARE / NEW BUILD'}</span>{#each (technology.unlocks ?? []) as unlock}<em>UNLOCKS → {unlockName(unlock)}</em>{/each}</div>
                  {#if !done && technology.prerequisites.length > 0}
                    <small class:requirement-met={available}>Requires {prerequisiteNames(technology)}</small>
                  {/if}
                </div>
                <div class="tech-cost"><strong>{technology.cost.toLocaleString('en-US')}</strong><small>RP</small></div>
              </div>

              <div class="tech-progress"><span style={`width:${percent}%`}></span></div>
              <div class="tech-footer">
                <small>{done ? 'COMPLETED' : active ? `${technologyProgress.toLocaleString('en-US')} / ${technology.cost.toLocaleString('en-US')} RP` : selected ? 'SELECTED FOR THIS TURN' : available ? 'AVAILABLE' : 'LOCKED'}</small>
                <button disabled={!editableTurn || done || !available || selected} onclick={() => choose(technology)}>
                  {done ? 'Researched' : selected ? 'Selected' : active ? 'Continue' : 'Research'}
                </button>
              </div>
            </article>
          {/each}
        </div>
      </section>
    {/each}
  </div>

  {#if !editableTurn}
    <div class="read-only"><Icon name="report" size={16}/><span>Research orders are read-only after you submit the turn. Reopen the turn to change the active project.</span></div>
  {/if}
</section>

<style>
  .research-view{height:100%;overflow:auto;box-sizing:border-box;padding:1.35rem;background:radial-gradient(circle at 45% 10%,rgba(30,91,130,.15),transparent 42%),#030912;color:#8ca5b5}.panel-cut{border:1px solid rgba(58,154,207,.22);background:linear-gradient(180deg,rgba(5,21,35,.97),rgba(3,13,23,.97))}.view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}.eyebrow{margin:0 0 .3rem;color:#43c5ff;text-transform:uppercase;letter-spacing:.14em;font-size:.65rem}h1{margin:0;color:#edf9ff;font-size:1.65rem;font-weight:500;letter-spacing:.08em}.intro{max-width:720px;margin:.5rem 0 0;color:#7f98aa;font-size:.76rem;line-height:1.5}.research-summary{display:flex;gap:1.25rem;justify-content:flex-end;flex-wrap:wrap}.research-summary span{text-align:right}.research-summary strong,.research-summary small{display:block}.research-summary strong{color:#e3f4fb;font-size:1rem}.research-summary small{margin-top:.14rem;color:#658499;text-transform:uppercase;font-size:.54rem;letter-spacing:.08em}
  .active-project{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,360px);gap:1rem;align-items:center;padding:.8rem .9rem;margin-bottom:.7rem;box-shadow:inset 3px 0 #56cfff}.active-copy{display:flex;align-items:center;gap:.75rem}.active-icon{width:45px;height:45px;display:grid;place-items:center;flex:none;border:1px solid rgba(74,196,244,.32);background:rgba(9,52,76,.52);color:#64d1ff}.active-copy small,.active-copy strong{display:block}.active-copy small{color:#5fbfdf;font-size:.53rem;letter-spacing:.12em}.active-copy strong{margin-top:.2rem;color:#e4f5fc;font-size:.84rem;font-weight:500}.active-copy p{margin:.22rem 0 0;color:#7895a8;font-size:.62rem}.active-progress>div{height:8px;border:1px solid rgba(71,166,211,.22);background:#020910}.active-progress>div span{display:block;height:100%;background:linear-gradient(90deg,#37aee4,#76ddff);box-shadow:0 0 8px rgba(77,204,255,.25)}.active-progress small{display:block;margin-top:.3rem;color:#6f8da0;font-size:.57rem;text-align:right}
  .effects{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));margin-bottom:.85rem}.effects>div{min-height:62px;display:flex;align-items:center;justify-content:center;gap:.55rem;border-right:1px solid rgba(57,137,179,.15);color:#5dcbf8}.effects>div:last-child{border:0}.effects strong,.effects small{display:block}.effects strong{color:#dcecf4;font-size:.76rem}.effects small{margin-top:.13rem;color:#647f91;font-size:.53rem;text-transform:uppercase;letter-spacing:.05em}
  .tree{display:grid;grid-template-columns:repeat(5,minmax(190px,1fr));gap:.65rem;align-items:start}.field>header{min-height:62px;display:flex;align-items:center;gap:.6rem;padding:.55rem .7rem;border-bottom:1px solid rgba(59,139,179,.16)}.field>header>span{width:34px;height:34px;display:grid;place-items:center;flex:none;border:1px solid rgba(67,176,224,.25);background:rgba(9,43,63,.58);color:#5ccfff}.field header strong,.field header small{display:block}.field header strong{color:#d8eaf3;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em}.field header small{margin-top:.15rem;color:#657f91;font-size:.54rem;line-height:1.35}.technologies{display:grid}.technology{padding:.65rem;border-bottom:1px solid rgba(52,123,159,.13);transition:.15s}.technology:last-child{border-bottom:0}.technology.active{background:rgba(14,67,94,.25);box-shadow:inset 2px 0 #55ccfb}.technology.selected:not(.active){background:rgba(93,76,19,.17);box-shadow:inset 2px 0 #e1ba52}.technology.completed{background:rgba(26,82,55,.16)}.technology.locked{opacity:.48}.tech-main{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:.45rem}.tier{width:25px;height:25px;display:grid;place-items:center;border:1px solid rgba(67,175,222,.27);color:#58c8f5;font-size:.55rem}.tech-copy strong{display:block;color:#d7e8f0;font-size:.67rem;font-weight:500}.tech-copy p{margin:.2rem 0 0;color:#718b9c;font-size:.56rem;line-height:1.4}.tech-copy small{display:block;margin-top:.28rem;color:#b27a58;font-size:.51rem}.tech-copy small.requirement-met{color:#668c78}.tech-tags{display:flex;flex-wrap:wrap;gap:.2rem;margin-top:.3rem}.tech-tags span,.tech-tags em{padding:.15rem .24rem;border:1px solid rgba(75,171,215,.2);color:#6ebbdc;font-size:.44rem;font-style:normal;letter-spacing:.03em}.tech-tags span.applied{border-color:rgba(225,187,84,.28);color:#d1b35f}.tech-tags em{color:#8aa7b6}.tech-cost{text-align:right}.tech-cost strong,.tech-cost small{display:block}.tech-cost strong{color:#9acfe4;font-size:.66rem}.tech-cost small{color:#5d7c8e;font-size:.48rem}.tech-progress{height:3px;margin-top:.55rem;background:#02080e}.tech-progress span{display:block;height:100%;background:#4ec9f7}.technology.completed .tech-progress span{background:#63ca8b}.tech-footer{display:flex;align-items:center;justify-content:space-between;gap:.4rem;margin-top:.45rem}.tech-footer small{color:#668496;font-size:.49rem;letter-spacing:.05em}.tech-footer button{min-height:27px;padding:0 .5rem;border:1px solid rgba(67,174,222,.3);background:#081d2c;color:#61caf5;font:inherit;font-size:.52rem;cursor:pointer}.tech-footer button:hover:not(:disabled){border-color:#51cfff;color:#e7f9ff}.tech-footer button:disabled{opacity:.42;cursor:not-allowed}.technology.completed .tech-footer small{color:#68b183}.read-only{display:flex;align-items:center;gap:.5rem;margin-top:.7rem;padding:.6rem .7rem;border:1px solid rgba(221,172,78,.25);background:rgba(50,37,10,.45);color:#bf9a56;font-size:.59rem}
  @media(max-width:1300px){.tree{grid-template-columns:repeat(3,minmax(220px,1fr))}.effects{grid-template-columns:repeat(3,1fr)}.effects>div:nth-child(3){border-right:0}}
  @media(max-width:850px){.view-header{display:grid}.research-summary{justify-content:flex-start}.research-summary span{text-align:left}.active-project{grid-template-columns:1fr}.active-progress small{text-align:left}.tree{grid-template-columns:1fr}.effects{grid-template-columns:1fr 1fr}.effects>div{border-bottom:1px solid rgba(57,137,179,.15)}.research-view{padding:.8rem}}
</style>
