<script lang="ts">
  import Icon from './Icon.svelte';
  import type { StarSystem } from '../types';

  export let system: StarSystem;
  export let canBuild = false;
  export let onBuild: (item: string) => void;
  export let onWaypoint: () => void;

  const ownerName: Record<string, string> = {
    player: 'Nova Dominion', neutral: 'Unclaimed', crimson: 'Crimson League', violet: 'Mael Covenant', amber: 'Amber Combine'
  };
</script>

<aside class="detail-panel">
  <header>
    <div class="system-title"><span class="system-star">✦</span><div><h2>{system.name}</h2><p>{system.isCapital ? 'Core world' : system.className}</p></div></div>
    <div class="owner-state" class:yours={system.owner === 'player'} class:unclaimed={system.owner === 'neutral'}>
      {system.owner === 'player' ? (system.isCapital ? 'YOUR CAPITAL' : 'YOUR COLONY') : system.owner === 'neutral' ? 'UNCLAIMED' : `${system.ownerLabel ?? ownerName[system.owner]} COLONY`}
    </div>
  </header>

  <div class="world-art" class:neutral={system.owner === 'neutral'} style={`--world-hue:${system.owner === 'player' ? '198' : system.owner === 'crimson' ? '8' : system.owner === 'violet' ? '280' : '42'}`}>
    <div class="moon"></div><div class="horizon"></div><div class="city"><i></i><i></i><i></i><i></i><i></i></div>
    <span>{system.ownerLabel ?? ownerName[system.owner]}</span>
  </div>

  <div class="summary-grid">
    <div><Icon name="user" size={19}/><span><small>Population</small><strong>{system.population.toFixed(1)} / {system.capacity.toFixed(1)}B</strong></span></div>
    <div><span class="happy">☺</span><span><small>Happiness</small><strong>{system.happiness}%</strong></span></div>
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
    <h3>Production queue</h3>
    {#if system.production.length}
      <div class="queue">
        {#each system.production as item}
          <div class="queue-item">
            <Icon name={item.kind === 'ship' ? 'fleet' : item.kind === 'defense' ? 'shield' : 'industry'} size={17}/>
            <span><strong>{item.label}</strong><i><b style={`width:${item.progress}%`}></b></i></span><em>{item.quantity}</em>
          </div>
        {/each}
      </div>
    {:else}
      <p class="empty">No active production.</p>
    {/if}
    <div class="build-options">
      <button disabled={!canBuild} onclick={() => onBuild('Scout Wing')}><Icon name="fleet" size={15}/><span><strong>Scout Wing</strong><small>300 industry · 40 ships</small></span></button>
      <button disabled={!canBuild} onclick={() => onBuild('Defense Grid')}><Icon name="shield" size={15}/><span><strong>Defense Grid</strong><small>250 industry · +250 defenses</small></span></button>
      <button disabled={!canBuild} onclick={() => onBuild('Orbital Factory')}><Icon name="industry" size={15}/><span><strong>Orbital Factory</strong><small>400 industry · +8 industry/turn</small></span></button>
    </div>
    {#if !canBuild}<p class="build-hint">{system.owner === 'player' ? 'Reopen the turn to add production.' : 'Production is only available in your colonies.'}</p>{/if}
  </section>

  <section class="panel-section split-title">
    <h3>Defenses</h3><span>{system.defenses.toLocaleString('en-US')}</span>
    <div class="defense-row"><Icon name="shield" size={22}/><div>{#each Array(Math.min(7, Math.max(1, Math.ceil(system.defenses / 400)))) as _}<i></i>{/each}</div></div>
  </section>

  <section class="panel-section">
    <h3>Fleets in system</h3>
    {#if system.fleets.length}
      {#each system.fleets as fleet}
        <div class="fleet-row"><Icon name="fleet" size={16}/><span>{fleet.name}{#if (fleet.colonizationCapacity ?? (fleet.role === 'Exploration fleet' ? 1 : 0)) > 0}<small>Colony module ×{fleet.colonizationCapacity ?? 1}</small>{/if}</span><strong>{fleet.ships.toLocaleString('en-US')}</strong></div>
      {/each}
    {:else}<p class="empty">No friendly fleet present.</p>{/if}
    <button class="text-action" onclick={onWaypoint}><Icon name="plus" size={15}/> Plan fleet route</button>
  </section>

  <p class="description">{system.description}</p>
</aside>

<style>
  .detail-panel { height: 100%; overflow-y: auto; background: linear-gradient(180deg, rgba(5,19,33,.98), rgba(2,10,18,.98)); border-left: 1px solid rgba(55,162,218,.3); color: #b8cad7; scrollbar-color: #225d7d #07121d; }
  header { min-height: 64px; display: flex; justify-content: space-between; align-items: center; padding: 0 1rem; border-bottom: 1px solid rgba(64,169,224,.22); }
  .system-title { display:flex; align-items:center; gap:.65rem; }.owner-state{padding:.28rem .45rem;border:1px solid rgba(255,105,95,.45);color:#ff8d85;background:rgba(92,20,18,.28);font-size:.57rem;font-weight:700;letter-spacing:.08em;white-space:nowrap}.owner-state.yours{border-color:rgba(70,202,255,.5);color:#6ed8ff;background:rgba(13,73,101,.3)}.owner-state.unclaimed{border-style:dashed;border-color:rgba(220,236,255,.45);color:#b7cbd8;background:rgba(60,74,84,.18)}.system-star { color:#54cfff; font-size:1.6rem; text-shadow:0 0 12px #42c5ff }.system-title h2 { margin:0; color:#f0f9ff; font-size:1.2rem; letter-spacing:.08em; text-transform:uppercase }.system-title p { margin:.15rem 0 0; color:#8199aa; font-size:.73rem }.capital-badge { color:#f2bf45; font-size:1.4rem; }
  .world-art { height: 146px; position:relative; overflow:hidden; background: radial-gradient(circle at 72% 28%, hsla(var(--world-hue),75%,72%,.6) 0 4%, transparent 18%), linear-gradient(180deg, hsl(var(--world-hue),55%,31%), hsl(var(--world-hue),55%,10%) 70%); border-bottom:1px solid rgba(62,164,218,.22) }.world-art::before { content:''; position:absolute; inset:0; background:radial-gradient(circle at 28% 20%, rgba(255,255,255,.7) 0 .6px, transparent .9px),radial-gradient(circle at 58% 33%, rgba(255,255,255,.6) 0 .5px, transparent .8px); background-size:39px 31px,53px 47px; opacity:.6 }.world-art.neutral { filter:saturate(.35) }.moon { position:absolute; width:72px; height:72px; border-radius:50%; right:35px; top:14px; background:radial-gradient(circle at 35% 30%, #dbe5ea, #708493 50%, #21303b 75%); box-shadow:0 0 18px hsla(var(--world-hue),80%,70%,.35) }.horizon { position:absolute; left:-10%; right:-10%; height:72px; bottom:-38px; border-radius:50% 50% 0 0; background:linear-gradient(180deg,hsl(var(--world-hue),42%,32%),#071019); box-shadow:0 -4px 20px hsla(var(--world-hue),70%,60%,.35) }.city { position:absolute; bottom:26px; left:28px; right:100px; display:flex; gap:9px; align-items:end }.city i { width:14px; height:38px; background:linear-gradient(90deg,#102b3c,#32637a,#0b1f2e); clip-path:polygon(35% 0,65% 0,75% 25%,100% 30%,100% 100%,0 100%,0 30%,25% 25%); box-shadow:0 0 8px hsla(var(--world-hue),90%,65%,.4) }.city i:nth-child(2){height:72px}.city i:nth-child(3){height:52px}.city i:nth-child(4){height:82px}.city i:nth-child(5){height:45px}.world-art>span { position:absolute; left:10px; bottom:8px; font-size:.65rem; color:#d5efff; text-transform:uppercase; letter-spacing:.12em }
  .summary-grid { display:grid; grid-template-columns:1fr 1fr; border-bottom:1px solid rgba(62,164,218,.2) }.summary-grid>div { min-height:54px; display:flex; align-items:center; gap:.6rem; padding:0 .8rem; border-right:1px solid rgba(62,164,218,.16); color:#75cfff }.summary-grid small,.summary-grid strong { display:block }.summary-grid small { color:#7991a2; font-size:.65rem }.summary-grid strong { color:#dcebf4; font-size:.8rem; margin-top:.1rem }.happy { color:#8cdd60; font-size:1.45rem }
  .panel-section { padding:.8rem 1rem; border-bottom:1px solid rgba(62,164,218,.18) }.panel-section h3 { margin:0 0 .65rem; color:#49c5ff; text-transform:uppercase; letter-spacing:.08em; font-size:.68rem }.resources { display:flex; justify-content:space-between; gap:.4rem }.resources div { display:flex; align-items:center; gap:.35rem; color:#74d0fb }.resources div span{display:block}.resources strong,.resources small{display:block}.resources strong { color:#dae9f2; font-size:.72rem }.resources small{margin-top:.05rem;color:#6da6c2;font-size:.56rem}
  .queue { display:grid; gap:.55rem }.queue-item { display:grid; grid-template-columns:20px 1fr 20px; gap:.45rem; align-items:center; color:#5fcaff }.queue-item span strong { display:block; color:#bcd0dc; font-size:.72rem; font-weight:500 }.queue-item span i { display:block; height:3px; background:#152b39; margin-top:.3rem }.queue-item span b { display:block; height:100%; background:#42ccff; box-shadow:0 0 8px rgba(66,204,255,.4) }.queue-item em { color:#dcecf5; font-style:normal; font-size:.72rem; text-align:right }
  .text-action { margin-top:.65rem; border:0; background:transparent; color:#4bc7ff; font:inherit; font-size:.7rem; display:flex; align-items:center; gap:.3rem; cursor:pointer; padding:0 }.text-action:hover { color:#dff7ff }.build-options{display:grid;gap:.38rem;margin-top:.65rem}.build-options button{min-height:42px;display:grid;grid-template-columns:20px 1fr;gap:.45rem;align-items:center;padding:.4rem .55rem;border:1px solid rgba(61,160,209,.25);background:rgba(5,27,42,.72);color:#58caff;text-align:left;cursor:pointer}.build-options button:hover:not(:disabled){border-color:#48caff;background:rgba(10,49,72,.86)}.build-options button:disabled{opacity:.35;cursor:not-allowed}.build-options span,.build-options strong,.build-options small{display:block}.build-options strong{color:#c7dbe6;font-size:.67rem;font-weight:500}.build-options small{margin-top:.12rem;color:#69899d;font-size:.56rem}.build-hint{margin:.5rem 0 0;color:#71899b;font-size:.62rem;line-height:1.4}.split-title { display:grid; grid-template-columns:1fr auto }.split-title h3 { grid-column:1 }.split-title>span { grid-column:2; color:#dcebf3; font-size:.75rem }.defense-row { grid-column:1/-1; display:flex; gap:.7rem; align-items:center; color:#8dcdf0 }.defense-row div { display:flex; gap:5px }.defense-row i { width:19px; height:12px; border:1px solid #568eb0; background:linear-gradient(180deg,#2d6689,#102334); clip-path:polygon(40% 0,60% 0,70% 35%,100% 50%,85% 100%,15% 100%,0 50%,30% 35%) }
  .fleet-row { display:grid; grid-template-columns:20px 1fr auto; gap:.45rem; align-items:center; padding:.45rem 0; color:#62caff; border-bottom:1px solid rgba(65,132,170,.09) }.fleet-row span { color:#adc2cf; font-size:.72rem }.fleet-row span small{display:block;margin-top:.12rem;color:#d6b25a;font-size:.58rem;text-transform:uppercase;letter-spacing:.05em}.fleet-row strong { color:#e1edf4; font-size:.72rem }.empty { color:#71899b; font-size:.72rem; margin:.4rem 0 }.description { margin:0; padding:1rem; color:#738b9d; font-size:.7rem; line-height:1.5 }
</style>
