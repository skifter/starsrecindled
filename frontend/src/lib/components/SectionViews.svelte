<script lang="ts">
  import Icon from './Icon.svelte';
  import { allFleets, factions, researchFields, systems, turnEvents } from '../demo-data';
  import type { GameSection, StarSystem } from '../types';

  export let section: GameSection;
  export let status: Record<string, unknown> | null = null;
  export let onSelectSystem: (system: StarSystem) => void;
  export let onResearch: (field: string) => void;

  let planetQuery = '';
  let fleetQuery = '';
  let ownedSystems = systems.filter((system) => system.owner === 'player');
  let filteredPlanets = ownedSystems;
  let filteredFleets = allFleets;
  $: ownedSystems = systems.filter((system) => system.owner === 'player');
  $: filteredPlanets = ownedSystems.filter((system) => system.name.toLowerCase().includes(planetQuery.toLowerCase()));
  $: filteredFleets = allFleets.filter((fleet) => fleet.name.toLowerCase().includes(fleetQuery.toLowerCase()));
</script>

{#if section === 'planets'}
  <div class="data-view">
    <header class="view-header"><div><p class="eyebrow">Empire overview</p><h1>Planets</h1></div><label class="search"><span>Search</span><input bind:value={planetQuery} placeholder="Planet name" /></label></header>
    <div class="planet-grid">
      {#each filteredPlanets as planet}
        <button class="planet-card panel-cut" onclick={() => onSelectSystem(planet)}>
          <div class="planet-orb" style={`--orb:${planet.isCapital ? '#52c8ff' : '#6a89a0'}`}><i></i></div>
          <div class="planet-info"><h2>{planet.name}{#if planet.isCapital}<small>Capital</small>{/if}</h2><p>{planet.className}</p><div class="stats"><span>Population <strong>{planet.population.toFixed(1)}B</strong></span><span>Development <strong>{planet.development}%</strong></span><span>Defense <strong>{planet.defenses}</strong></span></div><div class="meter"><i style={`width:${planet.development}%`}></i></div></div>
        </button>
      {/each}
    </div>
  </div>
{:else if section === 'fleets'}
  <div class="data-view">
    <header class="view-header"><div><p class="eyebrow">Command structure</p><h1>Fleets</h1></div><label class="search"><span>Search</span><input bind:value={fleetQuery} placeholder="Fleet name" /></label></header>
    <div class="table-shell panel-cut">
      <div class="table-row table-head"><span>Fleet</span><span>Role</span><span>Location</span><span>Destination</span><span>Ships</span></div>
      {#each filteredFleets as fleet}
        <div class="table-row"><span class="fleet-name"><Icon name="fleet" size={18}/><strong>{fleet.name}</strong></span><span>{fleet.role}</span><span>{fleet.location}</span><span>{fleet.destination ?? 'Holding'}{#if fleet.eta}<small>ETA {fleet.eta}</small>{/if}</span><span class="number">{fleet.ships.toLocaleString('en-US')}</span></div>
      {/each}
    </div>
    <div class="fleet-summary"><article><strong>{allFleets.length}</strong><span>Active fleets</span></article><article><strong>{allFleets.reduce((sum, fleet) => sum + fleet.ships, 0).toLocaleString('en-US')}</strong><span>Total ships</span></article><article><strong>{allFleets.filter((fleet) => fleet.destination).length}</strong><span>In transit</span></article></div>
  </div>
{:else if section === 'research'}
  <div class="data-view">
    <header class="view-header"><div><p class="eyebrow">Scientific directorate</p><h1>Research</h1></div><div class="allocation">Available allocation <strong>100%</strong></div></header>
    <div class="research-grid">
      {#each researchFields as field}
        <article class="research-card panel-cut">
          <div class="tech-icon"><Icon name={field.id === 'propulsion' ? 'fleet' : field.id === 'energy' ? 'energy' : field.id === 'biotech' ? 'planet' : field.id === 'construction' ? 'build' : 'research'} size={27}/></div>
          <div><p>Level {field.level}</p><h2>{field.name}</h2><span>{field.bonus}</span><div class="meter"><i style={`width:${field.progress}%`}></i></div><small>{field.progress}% to next level</small></div>
          <button onclick={() => onResearch(field.id)}>Prioritize</button>
        </article>
      {/each}
    </div>
  </div>
{:else if section === 'diplomacy'}
  <div class="data-view">
    <header class="view-header"><div><p class="eyebrow">Foreign relations</p><h1>Diplomacy</h1></div><div class="allocation">Known civilizations <strong>{factions.length}</strong></div></header>
    <div class="diplomacy-grid">
      {#each factions as faction}
        <article class="faction-card panel-cut" style={`--faction:${faction.color === 'cyan' ? '#47c8ff' : faction.color === 'red' ? '#ff615a' : faction.color === 'violet' ? '#ca6df1' : '#e9ad3b'}`}>
          <div class="crest"><Icon name="shield" size={34}/></div><div><p>{faction.relation}</p><h2>{faction.name}</h2><div class="relation"><i style={`width:${Math.abs(faction.score)}%`}></i></div><small>Relation score {faction.score > 0 ? '+' : ''}{faction.score}</small></div><button disabled={faction.id === 'nova'}>{faction.id === 'nova' ? 'Your empire' : 'Open channel'}</button>
        </article>
      {/each}
    </div>
  </div>
{:else if section === 'report'}
  <div class="data-view report-view">
    <header class="view-header"><div><p class="eyebrow">Turn intelligence</p><h1>Turn report</h1></div><div class="allocation">Year <strong>2347</strong></div></header>
    <div class="report-layout">
      <div class="timeline panel-cut">
        {#each turnEvents as event}
          <article class={event.tone}><span class="event-dot"></span><div><small>{event.time}</small><h2>{event.title}</h2><p>{event.text}</p></div></article>
        {/each}
      </div>
      <aside class="api-status panel-cut"><p class="eyebrow">Live API response</p>{#if status}<pre>{JSON.stringify(status, null, 2)}</pre>{:else}<p class="empty-state">The demonstration universe is active. Connect with a player token to display the live turn response here.</p>{/if}</aside>
    </div>
  </div>
{/if}

<style>
  .data-view { height:100%; overflow:auto; padding:1.35rem; background:radial-gradient(circle at 50% 30%,rgba(17,83,118,.12),transparent 50%),#030912; }
  .view-header { display:flex; justify-content:space-between; align-items:end; gap:1rem; margin-bottom:1.2rem }.eyebrow { margin:0 0 .3rem; color:#43c5ff; text-transform:uppercase; letter-spacing:.14em; font-size:.65rem }.view-header h1 { margin:0; color:#edf9ff; font-size:1.65rem; font-weight:500; letter-spacing:.08em }.search { display:grid; gap:.3rem; color:#7590a2; font-size:.65rem; text-transform:uppercase; letter-spacing:.08em }.search input { width:220px; height:38px; padding:0 .7rem; border:1px solid rgba(67,172,226,.3); background:#06121e; color:#dbeef8; outline:0 }.search input:focus { border-color:#45c7ff }.allocation { color:#718b9e; font-size:.75rem }.allocation strong { color:#ddecf5; margin-left:.35rem }
  .planet-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:.8rem }.planet-card { display:grid; grid-template-columns:90px 1fr; gap:1rem; text-align:left; padding:1rem; border:1px solid rgba(55,155,209,.22); background:rgba(5,18,31,.88); color:inherit; cursor:pointer }.planet-card:hover { border-color:#47c8ff; background:rgba(7,31,49,.95); transform:translateY(-2px) }.planet-orb { width:76px; height:76px; border-radius:50%; background:radial-gradient(circle at 32% 28%,#d8f5ff,var(--orb) 28%,#182936 68%,#03070c 72%); box-shadow:inset -12px -10px 25px #000,0 0 20px color-mix(in srgb,var(--orb) 40%,transparent); position:relative }.planet-orb i { position:absolute; width:94px; height:24px; border:2px solid color-mix(in srgb,var(--orb) 55%,transparent); border-radius:50%; left:-10px; top:27px; transform:rotate(-12deg) }.planet-info h2 { margin:0; color:#e4f3fb; font-size:1rem }.planet-info h2 small { margin-left:.5rem; color:#e6b550; font-size:.6rem; text-transform:uppercase }.planet-info p { margin:.25rem 0 .65rem; color:#758fa1; font-size:.7rem }.stats { display:grid; grid-template-columns:repeat(3,1fr); gap:.4rem }.stats span { color:#688397; font-size:.62rem }.stats strong { display:block; color:#bdd0db; margin-top:.18rem }.meter { height:3px; background:#102532; margin-top:.7rem }.meter i { display:block; height:100%; background:#43caff; box-shadow:0 0 9px rgba(67,202,255,.45) }
  .table-shell { background:rgba(4,16,28,.91); border:1px solid rgba(62,162,216,.2) }.table-row { display:grid; grid-template-columns:1.6fr .8fr .8fr 1fr .5fr; gap:1rem; align-items:center; min-height:58px; padding:0 1rem; border-bottom:1px solid rgba(57,132,173,.12); color:#8ba2b2; font-size:.75rem }.table-head { min-height:38px; color:#55c8f8; text-transform:uppercase; letter-spacing:.09em; font-size:.62rem; background:rgba(9,38,57,.45) }.fleet-name { display:flex; align-items:center; gap:.55rem; color:#58caff }.fleet-name strong { color:#c9dce6 }.table-row small { display:block;color:#c5a34f;margin-top:.15rem}.number { color:#e4f0f6;text-align:right}.fleet-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:.8rem; margin-top:.8rem}.fleet-summary article { padding:1rem; border:1px solid rgba(58,151,201,.18);background:rgba(5,18,30,.8)}.fleet-summary strong,.fleet-summary span{display:block}.fleet-summary strong{color:#e8f6fc;font-size:1.35rem}.fleet-summary span{color:#718b9d;font-size:.68rem;margin-top:.25rem;text-transform:uppercase;letter-spacing:.08em}
  .research-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:.8rem }.research-card { position:relative; display:grid; grid-template-columns:58px 1fr; gap:.8rem; padding:1rem; border:1px solid rgba(58,154,207,.2); background:rgba(5,18,31,.9) }.tech-icon { width:52px;height:52px;display:grid;place-items:center;color:#5acaff;border:1px solid rgba(68,184,242,.33);background:rgba(10,48,70,.45);clip-path:polygon(20% 0,80% 0,100% 20%,100% 80%,80% 100%,20% 100%,0 80%,0 20%)}.research-card p{margin:0;color:#c8a650;font-size:.65rem;text-transform:uppercase}.research-card h2{margin:.2rem 0;color:#e4f2f9;font-size:1rem}.research-card span{color:#7791a3;font-size:.7rem}.research-card small{color:#668093;font-size:.62rem}.research-card button{grid-column:1/-1;min-height:34px;border:1px solid rgba(65,186,242,.35);background:rgba(7,34,51,.65);color:#58caff;cursor:pointer}.research-card button:hover{border-color:#4bc9ff;color:#e9f9ff}
  .diplomacy-grid { display:grid;gap:.8rem}.faction-card { display:grid;grid-template-columns:72px 1fr auto;gap:1rem;align-items:center;padding:1rem;border:1px solid color-mix(in srgb,var(--faction) 30%,transparent);background:linear-gradient(90deg,color-mix(in srgb,var(--faction) 7%,#04101b),rgba(4,16,27,.9))}.crest{width:58px;height:58px;display:grid;place-items:center;color:var(--faction);border:1px solid color-mix(in srgb,var(--faction) 48%,transparent);clip-path:polygon(50% 0,92% 25%,92% 75%,50% 100%,8% 75%,8% 25%)}.faction-card p{margin:0;color:var(--faction);font-size:.65rem;text-transform:uppercase;letter-spacing:.08em}.faction-card h2{margin:.25rem 0;color:#e4f2f8;font-size:1rem}.relation{width:180px;height:3px;background:#152835}.relation i{display:block;height:100%;background:var(--faction)}.faction-card small{color:#6e8798;font-size:.62rem}.faction-card button{min-width:110px;min-height:38px;border:1px solid color-mix(in srgb,var(--faction) 48%,transparent);background:transparent;color:var(--faction);cursor:pointer}.faction-card button:disabled{opacity:.45;cursor:default}
  .report-layout { display:grid;grid-template-columns:minmax(420px,1.2fr) minmax(320px,.8fr);gap:.8rem;height:calc(100% - 75px)}.timeline,.api-status{border:1px solid rgba(58,151,203,.18);background:rgba(4,16,27,.9);padding:1rem;overflow:auto}.timeline article{position:relative;display:grid;grid-template-columns:18px 1fr;gap:.75rem;padding:0 0 1.25rem}.timeline article::before{content:'';position:absolute;left:6px;top:13px;bottom:0;width:1px;background:rgba(75,164,211,.22)}.event-dot{width:12px;height:12px;border-radius:50%;background:#43c9ff;box-shadow:0 0 10px rgba(67,201,255,.5);margin-top:3px;z-index:2}.timeline article.warning .event-dot{background:#ffb445;box-shadow:0 0 10px rgba(255,180,69,.5)}.timeline article.good .event-dot{background:#66d79a;box-shadow:0 0 10px rgba(102,215,154,.5)}.timeline small{color:#5fcaff;font-size:.62rem;text-transform:uppercase}.timeline h2{margin:.2rem 0;color:#d9e9f1;font-size:.88rem}.timeline p{margin:0;color:#778fa0;font-size:.7rem;line-height:1.5}.api-status pre{margin:0;color:#91b8ce;font:11px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap;word-break:break-word}.empty-state{color:#758d9f;font-size:.75rem;line-height:1.6}
  @media(max-width:850px){.report-layout{grid-template-columns:1fr;height:auto}.table-row{grid-template-columns:1.4fr .8fr .8fr .5fr}.table-row span:nth-child(4){display:none}.data-view{padding:.85rem}.view-header{align-items:start}.planet-grid,.research-grid{grid-template-columns:1fr}.faction-card{grid-template-columns:58px 1fr}.faction-card button{grid-column:1/-1}.fleet-summary{grid-template-columns:1fr}}
  @media(max-width:520px){.view-header{display:grid}.search input{width:100%}.table-row{grid-template-columns:1fr .65fr .5fr}.table-row span:nth-child(2),.table-row span:nth-child(4){display:none}.planet-card{grid-template-columns:64px 1fr}.planet-orb{width:58px;height:58px}.planet-orb i{width:70px;left:-6px;top:20px}.stats{grid-template-columns:1fr 1fr}.stats span:last-child{display:none}}
</style>
