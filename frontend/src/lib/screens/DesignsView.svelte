<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import type {
    ModelCatalog,
    ShipComponentCategory,
    ShipDesign,
    ShipDesignOrder,
    TechnologyModel
  } from '../types';

  export let catalog: ModelCatalog | null = null;
  export let orders: ShipDesignOrder[] = [];
  export let editableTurn = true;
  export let onQueueDesign: (order: ShipDesignOrder) => void = () => {};
  export let onRemoveDesign: (index: number) => void = () => {};

  const componentCategories: ShipComponentCategory[] = ['hull', 'engine', 'scanner', 'weapon', 'armor'];

  let mode: 'ships' | 'components' | 'installations' = 'ships';
  let editorBaseId = '';
  let designName = '';
  let selectedComponents: Record<ShipComponentCategory, string> = {
    hull: '',
    engine: '',
    scanner: '',
    weapon: '',
    armor: ''
  };

  $: designs = [...(catalog?.designs ?? [])].sort((a, b) => b.generation - a.generation);
  $: currentDesign = designs.find((design) => design.current) ?? designs[0] ?? null;
  $: components = catalog?.components ?? [];
  $: installations = catalog?.installations ?? [];
  $: unlockedComponents = components.filter((model) => model.unlocked).length;
  $: unlockedInstallations = installations.filter((model) => model.unlocked).length;
  $: editorBase = designs.find((design) => design.id === editorBaseId) ?? null;
  $: persistedGeneration = Math.max(0, ...designs.map((design) => design.generation));
  $: queuedGeneration = Math.max(0, ...orders.map((order) => order.generation ?? 0));
  $: nextGeneration = Math.max(persistedGeneration + orders.length, queuedGeneration) + 1;
  $: preview = editorBase ? designPreview(editorBase, selectedComponents) : null;
  $: changedFromBase = editorBase ? componentCategories.some((category) => selectedComponents[category] !== componentId(editorBase, category)) : false;
  $: canSave = editableTurn && editorBase !== null && designName.trim().length > 0 && designName.trim().length <= 48 && preview !== null && changedFromBase;

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

  function componentId(design: ShipDesign, category: ShipComponentCategory): string {
    return designComponent(design, category)?.modelId ?? '';
  }

  function installationIcon(family: string): string {
    if (family === 'defense_grid') return 'shield';
    if (family === 'deep_space_array') return 'target';
    return 'industry';
  }

  function roman(value: number): string {
    const values: Record<number, string> = { 1: 'I', 2: 'II', 3: 'III', 4: 'IV', 5: 'V', 6: 'VI', 7: 'VII', 8: 'VIII', 9: 'IX', 10: 'X' };
    return values[value] ?? String(value);
  }

  function componentOptions(category: ShipComponentCategory): TechnologyModel[] {
    return components
      .filter((model) => model.category === category && model.unlocked)
      .sort((a, b) => a.version - b.version);
  }

  function startClone(design: ShipDesign): void {
    editorBaseId = design.id;
    selectedComponents = {
      hull: componentId(design, 'hull'),
      engine: componentId(design, 'engine'),
      scanner: componentId(design, 'scanner'),
      weapon: componentId(design, 'weapon'),
      armor: componentId(design, 'armor')
    };
    designName = `${design.family.charAt(0).toUpperCase()}${design.family.slice(1)} Mk ${roman(nextGeneration)}`;
  }

  function closeEditor(): void {
    editorBaseId = '';
    designName = '';
  }

  function chooseComponent(category: ShipComponentCategory, event: Event): void {
    const select = event.currentTarget as HTMLSelectElement;
    selectedComponents = { ...selectedComponents, [category]: select.value };
  }

  function updateDesignName(event: Event): void {
    const input = event.currentTarget as HTMLInputElement;
    designName = input.value;
  }

  function useNewestUnlocked(): void {
    if (!editorBase) return;
    const next = { ...selectedComponents };
    for (const category of componentCategories) {
      const newest = componentOptions(category).sort((a, b) => b.version - a.version)[0];
      if (newest) next[category] = newest.id;
    }
    selectedComponents = next;
  }

  function designPreview(base: ShipDesign, selection: Record<ShipComponentCategory, string>) {
    const selected = new Map<ShipComponentCategory, TechnologyModel>();
    for (const category of componentCategories) {
      const model = components.find((candidate) => candidate.id === selection[category] && candidate.category === category && candidate.unlocked);
      if (!model) return null;
      selected.set(category, model);
    }

    const hull = selected.get('hull');
    const engine = selected.get('engine');
    const scanner = selected.get('scanner');
    const weapon = selected.get('weapon');
    const armor = selected.get('armor');
    if (!hull || !engine || !scanner || !weapon || !armor) return null;

    return {
      generation: nextGeneration,
      movementRange: Math.max(1, engine.stats.movementRange ?? 1),
      sensorRange: Math.max(0, scanner.stats.sensorRange ?? 0),
      attack: Math.max(0, weapon.stats.attack ?? 0),
      defense: Math.max(0, armor.stats.defense ?? 0),
      fuelCapacity: Math.max(1, hull.stats.fuelCapacity ?? 100),
      fuelUsePerHop: Math.max(1, engine.stats.fuelUsePerHop ?? 35),
      industryCost: Math.max(300, componentCategories.reduce((sum, category) => sum + (selected.get(category)?.stats.industryCost ?? 0), 0)),
      batchSize: base.batchSize
    };
  }

  function saveDesign(): void {
    if (!canSave || !editorBase) return;
    onQueueDesign({
      action: 'create',
      baseDesignId: editorBase.id,
      name: designName.trim(),
      componentModelIds: { ...selectedComponents }
    });
    closeEditor();
  }

  function pendingComponentName(order: ShipDesignOrder, category: ShipComponentCategory): string {
    const modelId = order.componentModelIds?.[category] ?? '';
    return components.find((component) => component.id === modelId)?.name ?? modelId;
  }
</script>

<section class="designs-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">Engineering registry</p>
      <h1>Designs & Models</h1>
      <p class="intro">Research unlocks hardware models, but it never changes a ship already in service and no longer creates a new design automatically. Clone an existing design, choose researched components and queue a new immutable generation.</p>
    </div>
    <div class="summary">
      <span><strong>{designs.length}</strong><small>ship generations</small></span>
      <span class:pending={orders.length > 0}><strong>{orders.length}</strong><small>designs queued</small></span>
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
          <div><small>CURRENT NEW-BUILD DESIGN</small><h2>{currentDesign.name}</h2><p>Production orders store this exact generation. Later research or designs cannot morph an existing fleet or queued build.</p></div>
          <span class="generation">GEN {currentDesign.generation}</span>
          <button class="primary-action" disabled={!editableTurn} onclick={() => startClone(currentDesign)}>New generation</button>
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
          {#each componentCategories as category}
            {@const component = designComponent(currentDesign, category)}
            <div>
              <Icon name={componentIcon(component?.category ?? category)} size={17}/>
              <span><small>{category}</small><strong>{component?.name ?? '—'}</strong></span>
            </div>
          {/each}
        </div>
      </section>
    {/if}

    {#if orders.length > 0}
      <section class="pending-designs panel-cut">
        <header><div><small>TURN DRAFT</small><strong>New generations in queue</strong></div><em>{orders.length} IN QUEUE</em></header>
        <p class="pending-note">These designs become persistent when the turn is processed. Ship production already queued this turn keeps the design version it currently references.</p>
        <div class="pending-list">
          {#each orders as order, index}
            <article>
              <span class="queue-icon"><Icon name="fleet" size={18}/></span>
              <div class="queue-copy"><strong>{order.name}</strong><small>{order.generation ? `GEN ${order.generation} · ` : ''}IN QUEUE · available next turn</small></div>
              <div class="queue-components">
                {#each componentCategories as category}<span><small>{category}</small>{pendingComponentName(order, category)}</span>{/each}
              </div>
              <button disabled={!editableTurn} onclick={() => onRemoveDesign(index)}>Remove</button>
            </article>
          {/each}
        </div>
      </section>
    {/if}

    {#if editorBase}
      <section class="design-editor panel-cut">
        <header>
          <div><small>NEW IMMUTABLE GENERATION</small><h2>Clone {editorBase.name}</h2><p>Choose only unlocked component models. The base design and every existing fleet remain unchanged.</p></div>
          <span>GEN {nextGeneration}</span>
        </header>

        <div class="editor-toolbar">
          <label><span>Design name</span><input maxlength="48" value={designName} oninput={updateDesignName} /></label>
          <button class="secondary-action" type="button" onclick={useNewestUnlocked}>Use newest unlocked</button>
        </div>

        <div class="component-editor">
          {#each componentCategories as category}
            {@const selected = components.find((model) => model.id === selectedComponents[category])}
            <label>
              <span><Icon name={componentIcon(category)} size={17}/><strong>{category}</strong></span>
              <select value={selectedComponents[category]} onchange={(event) => chooseComponent(category, event)}>
                {#each componentOptions(category) as model}
                  <option value={model.id}>{model.name} · v{model.version}</option>
                {/each}
              </select>
              <small>{selected?.description ?? 'No unlocked model.'}</small>
            </label>
          {/each}
        </div>

        {#if preview}
          <div class="preview">
            <span><small>Movement</small><strong>{preview.movementRange}</strong></span>
            <span><small>Sensors</small><strong>{preview.sensorRange}</strong></span>
            <span><small>Attack</small><strong>{preview.attack}</strong></span>
            <span><small>Defense</small><strong>{preview.defense}</strong></span>
            <span><small>Fuel</small><strong>{preview.fuelCapacity}</strong></span>
            <span><small>Fuel / hop</small><strong>{preview.fuelUsePerHop}</strong></span>
            <span><small>Batch</small><strong>{preview.batchSize}</strong></span>
            <span><small>Industry</small><strong>{preview.industryCost}</strong></span>
          </div>
        {/if}

        <footer>
          <p>{changedFromBase ? 'Ready to queue. The server recalculates all stats and validates research before saving the draft.' : 'Change at least one component before creating a new generation.'}</p>
          <div><button class="secondary-action" onclick={closeEditor}>Cancel</button><button class="primary-action" disabled={!canSave} onclick={saveDesign}>Queue new generation</button></div>
        </footer>
      </section>
    {/if}

    <div class="design-history">
      {#each designs as design}
        <article class="design-card panel-cut" class:current={design.current} class:obsolete={design.obsolete}>
          <header><span><Icon name="fleet" size={20}/></span><div><strong>{design.name}</strong><small>{design.current ? 'CURRENT' : design.obsolete ? 'OBSOLETE' : 'IN SERVICE / HISTORICAL'}</small></div><em>G{design.generation}</em></header>
          <div class="mini-stats"><span>SPD {design.stats.movementRange}</span><span>SEN {design.stats.sensorRange}</span><span>ATK {design.stats.attack}</span><span>DEF {design.stats.defense}</span></div>
          <ul>{#each design.components as component}<li><span>{component.category}</span><strong>{component.name}</strong></li>{/each}</ul>
          <footer><small>{design.basedOnDesignId ? `Based on ${designs.find((candidate) => candidate.id === design.basedOnDesignId)?.name ?? design.basedOnDesignId}` : 'Baseline design'}</small><button disabled={!editableTurn} onclick={() => startClone(design)}>Clone as new generation</button></footer>
        </article>
      {/each}
    </div>
    <div class="future panel-cut"><Icon name="build" size={19}/><span><strong>Design generations are now player-created.</strong><small>The next 0.7.2 slice can add fleet refit because old and new generations now have explicit immutable component snapshots.</small></span></div>

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
    <div class="future panel-cut"><Icon name="build" size={19}/><span><strong>Planet hardware also stays explicitly versioned.</strong><small>Researching Mk II does not alter Mk I installations; upgrades remain explicit industry/time orders.</small></span></div>
  {/if}
</section>

<style>
  .designs-view{height:100%;overflow:auto;box-sizing:border-box;padding:1.35rem;background:radial-gradient(circle at 42% 12%,rgba(27,93,130,.14),transparent 42%),#030912;color:#88a2b3}.panel-cut{border:1px solid rgba(58,154,207,.22);background:linear-gradient(180deg,rgba(5,21,35,.97),rgba(3,13,23,.97))}.view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:.85rem}.eyebrow{margin:0 0 .3rem;color:#43c5ff;text-transform:uppercase;letter-spacing:.14em;font-size:.65rem}h1{margin:0;color:#edf9ff;font-size:1.65rem;font-weight:500;letter-spacing:.08em}.intro{max-width:800px;margin:.5rem 0 0;color:#7f98aa;font-size:.75rem;line-height:1.5}.summary{display:flex;gap:1.15rem;flex-wrap:wrap;justify-content:flex-end}.summary span{text-align:right}.summary strong,.summary small{display:block}.summary strong{color:#e5f5fc;font-size:1rem}.summary small{color:#648296;font-size:.53rem;text-transform:uppercase}.summary .pending strong{color:#e5c96f}.tabs{display:flex;gap:.35rem;padding:.4rem;margin-bottom:.75rem}.tabs button{min-height:34px;display:flex;align-items:center;gap:.4rem;padding:0 .65rem;border:1px solid transparent;background:transparent;color:#7594a8;font:inherit;font-size:.59rem;text-transform:uppercase;letter-spacing:.06em;cursor:pointer}.tabs button.active{border-color:rgba(73,195,241,.34);background:rgba(12,61,86,.55);color:#65d1fb}.current-design{padding:.85rem;margin-bottom:.7rem;box-shadow:inset 3px 0 #55ccfb}.current-title{display:grid;grid-template-columns:48px minmax(0,1fr) auto auto;align-items:center;gap:.7rem}.design-icon{width:46px;height:46px;display:grid;place-items:center;border:1px solid rgba(71,192,239,.3);background:rgba(10,51,72,.5);color:#61d0fb}.current-title small{color:#56bedf;font-size:.51rem;letter-spacing:.11em}.current-title h2{margin:.15rem 0;color:#e4f4fb;font-size:1rem;font-weight:500}.current-title p{margin:0;color:#728da0;font-size:.59rem}.generation{color:#e4c668;font-size:.58rem;letter-spacing:.09em}.primary-action,.secondary-action,.design-card footer button,.pending-list button{min-height:32px;padding:0 .65rem;border:1px solid rgba(70,188,236,.35);background:rgba(9,54,78,.8);color:#68d3fb;font:inherit;font-size:.57rem;text-transform:uppercase;letter-spacing:.05em;cursor:pointer}.primary-action:hover,.design-card footer button:hover{border-color:#58d2ff;color:#e6f9ff}.secondary-action{background:rgba(8,27,41,.8);color:#86a9bb}.primary-action:disabled,.secondary-action:disabled,.design-card footer button:disabled,.pending-list button:disabled{opacity:.35;cursor:not-allowed}.stats,.preview{display:grid;grid-template-columns:repeat(8,1fr);margin-top:.75rem;border:1px solid rgba(55,137,180,.15)}.stats span,.preview span{padding:.45rem;border-right:1px solid rgba(55,137,180,.13)}.stats span:last-child,.preview span:last-child{border:0}.stats small,.stats strong,.preview small,.preview strong{display:block}.stats small,.preview small{color:#657f91;font-size:.49rem;text-transform:uppercase}.stats strong,.preview strong{margin-top:.12rem;color:#d6e8f0;font-size:.68rem}.component-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:.4rem;margin-top:.6rem}.component-strip>div{display:flex;align-items:center;gap:.45rem;padding:.45rem .5rem;border:1px solid rgba(58,139,181,.13);color:#57c8f2}.component-strip span,.component-strip small,.component-strip strong{display:block}.component-strip small{color:#657d8d;font-size:.47rem;text-transform:uppercase}.component-strip strong{margin-top:.1rem;color:#abc4d0;font-size:.57rem;font-weight:500}.pending-designs{margin-bottom:.7rem;border-color:rgba(205,177,87,.35);box-shadow:inset 3px 0 #c7a94c}.pending-designs>header{display:flex;align-items:center;justify-content:space-between;padding:.65rem .8rem;border-bottom:1px solid rgba(205,177,87,.17)}.pending-designs header small,.pending-designs header strong{display:block}.pending-designs header small{color:#a58d49;font-size:.49rem;letter-spacing:.1em}.pending-designs header strong{margin-top:.12rem;color:#ddd0a3;font-size:.72rem}.pending-designs header em{color:#e5c96f;font-size:.55rem;font-style:normal}.pending-note{margin:0;padding:.55rem .8rem;color:#7b8c91;font-size:.57rem;border-bottom:1px solid rgba(58,139,181,.11)}.pending-list article{display:grid;grid-template-columns:30px 170px minmax(0,1fr) auto;align-items:center;gap:.6rem;padding:.55rem .75rem;border-bottom:1px solid rgba(58,139,181,.11)}.pending-list article:last-child{border:0}.queue-icon{color:#e3c55e}.queue-copy strong,.queue-copy small{display:block}.queue-copy strong{color:#d8e6ec;font-size:.66rem}.queue-copy small{margin-top:.12rem;color:#c5a94d;font-size:.5rem}.queue-components{display:flex;gap:.65rem;flex-wrap:wrap}.queue-components span{color:#9fb3bd;font-size:.52rem}.queue-components small{margin-right:.2rem;color:#5d7888;text-transform:uppercase}.design-editor{margin-bottom:.75rem;padding:.85rem;border-color:rgba(77,197,239,.45);box-shadow:inset 3px 0 #4dc9f6}.design-editor>header{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.design-editor header small{color:#54c6ef;font-size:.5rem;letter-spacing:.1em}.design-editor header h2{margin:.15rem 0;color:#e4f5fc;font-size:1rem;font-weight:500}.design-editor header p{margin:0;color:#708b9c;font-size:.59rem}.design-editor header>span{color:#e4c668;font-size:.6rem}.editor-toolbar{display:grid;grid-template-columns:minmax(240px,1fr) auto;gap:.6rem;align-items:end;margin-top:.75rem}.editor-toolbar label>span{display:block;margin-bottom:.25rem;color:#7892a3;font-size:.51rem;text-transform:uppercase}.editor-toolbar input,.component-editor select{width:100%;box-sizing:border-box;height:35px;border:1px solid rgba(62,159,205,.3);background:#06131f;color:#dcecf3;padding:0 .55rem;font:inherit;font-size:.64rem;outline:none}.editor-toolbar input:focus,.component-editor select:focus{border-color:#4acbfb}.component-editor{display:grid;grid-template-columns:repeat(5,1fr);gap:.5rem;margin-top:.65rem}.component-editor label{padding:.55rem;border:1px solid rgba(56,139,180,.16);background:rgba(3,16,27,.65)}.component-editor label>span{display:flex;align-items:center;gap:.35rem;color:#5ecaf3}.component-editor label>span strong{color:#9eb6c2;font-size:.55rem;text-transform:uppercase}.component-editor label>small{display:block;margin-top:.35rem;min-height:30px;color:#617c8d;font-size:.5rem;line-height:1.35}.component-editor select{margin-top:.4rem;height:32px;font-size:.57rem}.design-editor>footer{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-top:.7rem}.design-editor>footer p{margin:0;color:#738c9b;font-size:.56rem}.design-editor>footer div{display:flex;gap:.4rem}.design-history{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem}.design-card{padding:.7rem}.design-card.current{border-color:rgba(75,196,239,.45)}.design-card.obsolete{opacity:.56}.design-card>header{display:grid;grid-template-columns:30px minmax(0,1fr) auto;gap:.45rem;align-items:center}.design-card>header>span{color:#58caf5}.design-card header strong,.design-card header small{display:block}.design-card header strong{color:#d7e7ef;font-size:.67rem}.design-card header small{margin-top:.12rem;color:#5f8ba2;font-size:.48rem}.design-card header em{color:#d3bd6b;font-size:.54rem;font-style:normal}.mini-stats{display:flex;gap:.35rem;flex-wrap:wrap;margin:.55rem 0}.mini-stats span{padding:.2rem .35rem;border:1px solid rgba(55,141,181,.15);color:#7899ab;font-size:.51rem}.design-card ul{list-style:none;margin:0;padding:0}.design-card li{display:flex;justify-content:space-between;gap:.5rem;padding:.27rem 0;border-bottom:1px solid rgba(50,118,153,.1);font-size:.54rem}.design-card li span{color:#597789;text-transform:uppercase}.design-card li strong{color:#9eb5c1;font-weight:500;text-align:right}.design-card>footer{display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-top:.55rem}.design-card>footer small{color:#607d8e;font-size:.48rem}.design-card>footer button{min-height:28px;font-size:.5rem}.future{display:flex;align-items:center;gap:.55rem;margin-top:.7rem;padding:.65rem;color:#57c7f0}.future span,.future strong,.future small{display:block}.future strong{color:#bcd5e1;font-size:.62rem}.future small{margin-top:.13rem;color:#6d8798;font-size:.54rem;line-height:1.4}.model-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem}.model-card{padding:.7rem}.model-card.locked{opacity:.5}.model-card header{display:grid;grid-template-columns:28px minmax(0,1fr) auto;gap:.4rem;align-items:center;color:#5cccf5}.model-card header strong,.model-card header small{display:block}.model-card header strong{color:#d5e6ee;font-size:.64rem}.model-card header small{color:#638297;font-size:.48rem}.model-card header em{color:#6ac996;font-size:.5rem;font-style:normal}.model-card.locked header em{color:#a07878}.model-card>p{min-height:34px;margin:.55rem 0;color:#708a9b;font-size:.55rem;line-height:1.4}.model-stats{display:flex;gap:.35rem;flex-wrap:wrap}.model-stats span{min-width:58px;padding:.28rem .35rem;border:1px solid rgba(55,141,181,.13)}.model-stats small,.model-stats strong{display:block}.model-stats small{color:#577384;font-size:.44rem}.model-stats strong{color:#b5cbd6;font-size:.58rem}.model-card>footer{margin-top:.5rem;color:#657e8e;font-size:.49rem}.installation-groups{display:grid;gap:.65rem}.installation-family>header{display:flex;gap:.45rem;align-items:center;padding:.6rem .7rem;border-bottom:1px solid rgba(56,139,180,.14);color:#5ccbf4}.installation-family header strong,.installation-family header small{display:block}.installation-family header strong{color:#d2e3eb;font-size:.66rem;text-transform:capitalize}.installation-family header small{color:#5e7d90;font-size:.47rem}.installation-line{display:grid;grid-template-columns:repeat(3,1fr)}.installation-line article{min-height:100px;padding:.65rem;border-right:1px solid rgba(56,139,180,.12)}.installation-line article:last-child{border:0}.installation-line article.locked{opacity:.45}.installation-line article div{display:flex;justify-content:space-between;gap:.4rem}.installation-line strong{color:#c6dbe5;font-size:.62rem}.installation-line small{color:#68c993;font-size:.48rem}.installation-line .locked small{color:#a47d7d}.installation-line p{margin:.45rem 0;color:#6e8798;font-size:.54rem;line-height:1.4}.installation-line article>span{color:#5b7c8e;font-size:.49rem}.locked{filter:saturate(.45)}
  @media(max-width:1100px){.design-history,.model-grid{grid-template-columns:repeat(2,1fr)}.stats,.preview{grid-template-columns:repeat(4,1fr)}.component-editor{grid-template-columns:repeat(2,1fr)}.pending-list article{grid-template-columns:30px minmax(0,1fr) auto}.queue-components{grid-column:2/4}}
  @media(max-width:760px){.designs-view{padding:.8rem}.view-header{display:grid}.summary{justify-content:flex-start}.summary span{text-align:left}.current-title{grid-template-columns:44px minmax(0,1fr)}.current-title .generation,.current-title .primary-action{grid-column:2;justify-self:start}.component-strip,.design-history,.model-grid,.installation-line,.component-editor{grid-template-columns:1fr}.stats,.preview{grid-template-columns:repeat(2,1fr)}.editor-toolbar{grid-template-columns:1fr}.design-editor>footer{display:grid}.pending-list article{grid-template-columns:26px minmax(0,1fr)}.pending-list button{grid-column:2;justify-self:start}.queue-components{grid-column:2}.tabs{overflow:auto}.tabs button{white-space:nowrap}}
</style>
