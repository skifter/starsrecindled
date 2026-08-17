<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import type { ModelCatalog, ShipDesign } from '../types';

  export let catalog: ModelCatalog | null = null;

  let mode: 'ships' | 'components' | 'installations' = 'ships';
  $: designs = [...(catalog?.designs ?? [])].sort((a, b) => b.generation - a.generation);
  $: currentDesign = designs.find((design) => design.current) ?? designs[0] ?? null;
  $: components = catalog?.components ?? [];
  $: installations = catalog?.installations ?? [];
  $: unlockedComponents = components.filter((model) => model.unlocked).length;
  $: unlockedInstallations = installations.filter((model) => model.unlocked).length;

  function componentIcon(category: string): string {
    if (category === 'engine') return 'fleet';
    if (category === 'scanner') return 'target';
    if (category === 'weapon') return 'energy';
    if (category === 'armor') return 'shield';
    if (category === 'installation') return 'build';
    return 'fleet';
  }

  function designComponent(design: ShipDesign, category: string) {
    return design.components.find((component) => component.category === category);
  }

  function installationIcon(family: string): string {
    if (family === 'defense_grid') return 'shield';
    if (family === 'deep_space_array') return 'target';
    return 'industry';
  }
</script>

<section class="designs-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">Engineering registry</p>
      <h1>Designs & Models</h1>
      <p class="intro">Research unlocks new hardware models. Ships and installations already in service keep the version they were built with; only new construction uses newer hardware. Applied research such as fuel chemistry can affect existing fleets.</p>
    </div>
    <div class="summary">
      <span><strong>{designs.length}</strong><small>ship generations</small></span>
      <span><strong>{unlockedComponents}/{components.length}</strong><small>components</small></span>
      <span><strong>{unlockedInstallations}/{installations.length}</strong><small>installations</small></span>
    </div>
  </header>

  <div class="tabs panel-cut">
    <button class:active={mode === 'ships'} onclick={() => (mode = 'ships')}><Icon name="fleet" size={16}/>Ship designs</button>
    <button class:active={mode === 'components'} onclick={() => (mode = 'components')}><Icon name="research" size={16}/>Components</button>
    <button class:active={mode === 'installations'} onclick={() => (mode = 'installations')}><Icon name="build" size={16}/>Planet models</button>
  </div>

  {#if mode === 'ships'}
    {#if currentDesign}
      <section class="current-design panel-cut">
        <div class="current-title">
          <span class="design-icon"><Icon name="fleet" size={29}/></span>
          <div><small>CURRENT NEW-BUILD DESIGN</small><h2>{currentDesign.name}</h2><p>New Scout production uses this exact component set. Older fleets keep their own design snapshot.</p></div>
          <span class="generation">GEN {currentDesign.generation}</span>
        </div>
        <div class="stats">
          <span><small>Movement</small><strong>{currentDesign.stats.movementRange} hop{currentDesign.stats.movementRange === 1 ? '' : 's'}</strong></span>
          <span><small>Sensors</small><strong>{currentDesign.stats.sensorRange}</strong></span>
          <span><small>Attack</small><strong>{currentDesign.stats.attack}</strong></span>
          <span><small>Defense</small><strong>{currentDesign.stats.defense}</strong></span>
          <span><small>Fuel</small><strong>{currentDesign.stats.fuelCapacity}</strong></span>
          <span><small>Fuel / hop</small><strong>{currentDesign.stats.fuelUsePerHop}</strong></span>
          <span><small>Build batch</small><strong>{currentDesign.batchSize}</strong></span>
          <span><small>Industry</small><strong>{currentDesign.industryCost}</strong></span>
        </div>
        <div class="component-strip">
          {#each ['hull', 'engine', 'scanner', 'weapon', 'armor'] as category}
            {@const component = designComponent(currentDesign, category)}
            <div>
              <Icon name={componentIcon(component?.category ?? category)} size={17}/>
              <span><small>{category}</small><strong>{component?.name ?? '—'}</strong></span>
            </div>
          {/each}
        </div>
      </section>
    {/if}

    <div class="design-history">
      {#each designs as design}
        <article class="design-card panel-cut" class:current={design.current} class:obsolete={design.obsolete}>
          <header><span><Icon name="fleet" size={20}/></span><div><strong>{design.name}</strong><small>{design.current ? 'CURRENT' : design.obsolete ? 'OBSOLETE' : 'IN SERVICE / HISTORICAL'}</small></div><em>G{design.generation}</em></header>
          <div class="mini-stats"><span>SPD {design.stats.movementRange}</span><span>SEN {design.stats.sensorRange}</span><span>ATK {design.stats.attack}</span><span>DEF {design.stats.defense}</span></div>
          <ul>{#each design.components as component}<li><span>{component.category}</span><strong>{component.name}</strong></li>{/each}</ul>
        </article>
      {/each}
    </div>
    <div class="future panel-cut"><Icon name="build" size={19}/><span><strong>Design lineage is now persistent.</strong><small>0.7.2 can add clone/edit/refit without changing the data model: old fleets already carry their exact generation and component snapshot.</small></span></div>

  {:else if mode === 'components'}
    <div class="model-grid">
      {#each components as model}
        <article class="model-card panel-cut" class:locked={!model.unlocked}>
          <header><span><Icon name={componentIcon(model.category)} size={19}/></span><div><strong>{model.name}</strong><small>{model.category.toUpperCase()} · VERSION {model.version}</small></div><em>{model.unlocked ? 'UNLOCKED' : 'LOCKED'}</em></header>
          <p>{model.description}</p>
          <div class="model-stats">{#each Object.entries(model.stats) as [key, value]}<span><small>{key}</small><strong>{value}</strong></span>{/each}</div>
          {#if model.requires.length > 0}<footer>Research: {model.requires.join(', ')}</footer>{/if}
        </article>
      {/each}
    </div>

  {:else}
    <div class="installation-groups">
      {#each ['orbital_factory', 'defense_grid', 'deep_space_array'] as family}
        <section class="installation-family panel-cut">
          <header><Icon name={installationIcon(family)} size={21}/><div><strong>{family.replaceAll('_', ' ')}</strong><small>VERSIONED PLANET HARDWARE</small></div></header>
          <div class="installation-line">
            {#each installations.filter((model) => model.family === family).sort((a, b) => a.version - b.version) as model}
              <article class:locked={!model.unlocked}>
                <div><strong>{model.name}</strong><small>{model.unlocked ? 'AVAILABLE' : 'LOCKED'}</small></div>
                <p>{model.description}</p>
                <span>{model.upgradeFrom ? `Upgrade path from ${model.upgradeFrom.replaceAll('_', ' ')}` : 'Base model'}</span>
              </article>
            {/each}
          </div>
        </section>
      {/each}
    </div>
    <div class="future panel-cut"><Icon name="build" size={19}/><span><strong>Upgrade paths are registered but not automatic.</strong><small>Researching Mk II does not alter Mk I installations. The explicit industry/time upgrade action is reserved for 0.7.2.</small></span></div>
  {/if}
</section>

<style>
  .designs-view{height:100%;overflow:auto;box-sizing:border-box;padding:1.35rem;background:radial-gradient(circle at 42% 12%,rgba(27,93,130,.14),transparent 42%),#030912;color:#88a2b3}.panel-cut{border:1px solid rgba(58,154,207,.22);background:linear-gradient(180deg,rgba(5,21,35,.97),rgba(3,13,23,.97))}.view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:.85rem}.eyebrow{margin:0 0 .3rem;color:#43c5ff;text-transform:uppercase;letter-spacing:.14em;font-size:.65rem}h1{margin:0;color:#edf9ff;font-size:1.65rem;font-weight:500;letter-spacing:.08em}.intro{max-width:780px;margin:.5rem 0 0;color:#7f98aa;font-size:.75rem;line-height:1.5}.summary{display:flex;gap:1.15rem;flex-wrap:wrap;justify-content:flex-end}.summary span{text-align:right}.summary strong,.summary small{display:block}.summary strong{color:#e5f5fc;font-size:1rem}.summary small{color:#648296;font-size:.53rem;text-transform:uppercase}.tabs{display:flex;gap:.35rem;padding:.4rem;margin-bottom:.75rem}.tabs button{min-height:34px;display:flex;align-items:center;gap:.4rem;padding:0 .65rem;border:1px solid transparent;background:transparent;color:#7594a8;font:inherit;font-size:.59rem;text-transform:uppercase;letter-spacing:.06em;cursor:pointer}.tabs button.active{border-color:rgba(73,195,241,.34);background:rgba(12,61,86,.55);color:#65d1fb}.current-design{padding:.85rem;margin-bottom:.7rem;box-shadow:inset 3px 0 #55ccfb}.current-title{display:grid;grid-template-columns:48px minmax(0,1fr) auto;align-items:center;gap:.7rem}.design-icon{width:46px;height:46px;display:grid;place-items:center;border:1px solid rgba(71,192,239,.3);background:rgba(10,51,72,.5);color:#61d0fb}.current-title small{color:#56bedf;font-size:.51rem;letter-spacing:.11em}.current-title h2{margin:.15rem 0;color:#e4f4fb;font-size:1rem;font-weight:500}.current-title p{margin:0;color:#728da0;font-size:.59rem}.generation{color:#e4c668;font-size:.58rem;letter-spacing:.09em}.stats{display:grid;grid-template-columns:repeat(8,1fr);margin-top:.75rem;border:1px solid rgba(55,137,180,.15)}.stats span{padding:.45rem;border-right:1px solid rgba(55,137,180,.13)}.stats span:last-child{border:0}.stats small,.stats strong{display:block}.stats small{color:#657f91;font-size:.49rem;text-transform:uppercase}.stats strong{margin-top:.12rem;color:#d6e8f0;font-size:.68rem}.component-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:.4rem;margin-top:.6rem}.component-strip>div{min-height:46px;display:flex;align-items:center;gap:.45rem;padding:.35rem .5rem;border:1px solid rgba(60,146,190,.16);color:#55c9f8}.component-strip small,.component-strip strong{display:block}.component-strip small{color:#617d90;font-size:.47rem;text-transform:uppercase}.component-strip strong{margin-top:.12rem;color:#bcd1dc;font-size:.58rem;font-weight:500}.design-history{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.6rem}.design-card{padding:.65rem}.design-card.current{border-color:rgba(80,203,249,.42)}.design-card.obsolete{opacity:.52}.design-card header,.model-card header{display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:.45rem;align-items:center}.design-card header>span,.model-card header>span{width:32px;height:32px;display:grid;place-items:center;border:1px solid rgba(65,164,211,.22);color:#5dcdf9}.design-card header strong,.design-card header small,.model-card header strong,.model-card header small{display:block}.design-card header strong,.model-card header strong{color:#d2e4ec;font-size:.67rem;font-weight:500}.design-card header small,.model-card header small{margin-top:.12rem;color:#688496;font-size:.49rem}.design-card header em,.model-card header em{font-style:normal;color:#65c9ee;font-size:.5rem}.mini-stats{display:flex;gap:.4rem;margin:.5rem 0}.mini-stats span{padding:.2rem .3rem;border:1px solid rgba(60,145,188,.15);color:#769caf;font-size:.49rem}.design-card ul{list-style:none;padding:0;margin:.4rem 0 0;display:grid;gap:.2rem}.design-card li{display:flex;justify-content:space-between;gap:.5rem;color:#657f90;font-size:.52rem}.design-card li strong{color:#99b2c0;font-weight:500}.future{display:flex;align-items:center;gap:.55rem;margin-top:.7rem;padding:.6rem;color:#e0bd5b}.future strong,.future small{display:block}.future strong{font-size:.62rem}.future small{margin-top:.12rem;color:#71899a;font-size:.54rem}.model-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.55rem}.model-card{padding:.65rem}.model-card.locked{opacity:.43}.model-card p{min-height:34px;margin:.45rem 0;color:#718b9c;font-size:.56rem;line-height:1.4}.model-stats{display:flex;gap:.35rem;flex-wrap:wrap}.model-stats span{padding:.28rem .35rem;border:1px solid rgba(58,137,178,.16)}.model-stats small,.model-stats strong{display:block}.model-stats small{color:#607b8d;font-size:.44rem}.model-stats strong{color:#bdd4df;font-size:.57rem}.model-card footer{margin-top:.45rem;color:#846f51;font-size:.49rem}.installation-groups{display:grid;gap:.6rem}.installation-family>header{display:flex;align-items:center;gap:.5rem;padding:.55rem .7rem;border-bottom:1px solid rgba(57,132,173,.14);color:#61cdf7}.installation-family header strong,.installation-family header small{display:block}.installation-family header strong{text-transform:uppercase;color:#d0e3ec;font-size:.66rem}.installation-family header small{margin-top:.1rem;color:#657f90;font-size:.48rem}.installation-line{display:grid;grid-template-columns:repeat(3,1fr)}.installation-line article{padding:.65rem;border-right:1px solid rgba(57,132,173,.13)}.installation-line article:last-child{border:0}.installation-line article.locked{opacity:.4}.installation-line article strong,.installation-line article small{display:block}.installation-line article strong{color:#c9dce5;font-size:.65rem}.installation-line article small{margin-top:.1rem;color:#62bddd;font-size:.48rem}.installation-line p{min-height:34px;margin:.4rem 0;color:#718b9c;font-size:.55rem;line-height:1.4}.installation-line article>span{color:#947d55;font-size:.49rem}
  @media(max-width:1000px){.view-header{display:grid}.summary{justify-content:flex-start}.summary span{text-align:left}.stats{grid-template-columns:repeat(4,1fr)}.component-strip{grid-template-columns:1fr 1fr}.installation-line{grid-template-columns:1fr}}
  @media(max-width:700px){.designs-view{padding:.8rem}.tabs{overflow-x:auto}.current-title{grid-template-columns:42px minmax(0,1fr)}.generation{grid-column:2}.stats{grid-template-columns:repeat(2,1fr)}.component-strip{grid-template-columns:1fr}}
</style>
