<script lang="ts">
  import DetailPanel from '../components/DetailPanel.svelte';
  import GalaxyMap from '../components/GalaxyMap.svelte';
  import Icon from '../components/Icon.svelte';
  import Logo from '../components/Logo.svelte';
  import SectionViews from '../components/SectionViews.svelte';
  import { systems } from '../demo-data';
  import type { ConnectionSettings, GameSection, PlayerOrders, StarSystem } from '../types';

  export let connection: ConnectionSettings;
  export let orders: PlayerOrders;
  export let status: Record<string, unknown> | null = null;
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

  let activeSection: GameSection = 'galaxy';
  let selectedSystem: StarSystem = systems[0];
  let sidebarOpen = false;
  let rightPanelOpen = true;
  let showTechnical = false;
  let technicalText = '';
  let localNotice = '';

  function selectSystem(system: StarSystem): void {
    selectedSystem = system;
    activeSection = 'galaxy';
    rightPanelOpen = true;
  }

  function updateOrders(next: PlayerOrders, notice: string): void {
    onOrdersChange(next);
    localNotice = notice;
    window.setTimeout(() => { if (localNotice === notice) localNotice = ''; }, 2600);
  }

  function addProduction(item: string): void {
    updateOrders({ ...orders, production: [...(orders.production ?? []), { systemId: selectedSystem.id, item, quantity: 1 }] }, `${item} added to ${selectedSystem.name}`);
  }

  function addWaypoint(action: 'move' | 'colonize' = 'move'): void {
    const fleetId = selectedSystem.fleets[0]?.id ?? 'fleet-1';
    updateOrders({ ...orders, fleets: [...(orders.fleets ?? []), { fleetId, action, targetSystemId: selectedSystem.id }] }, `${action === 'colonize' ? 'Colonization' : 'Waypoint'} order added for ${selectedSystem.name}`);
  }

  function prioritizeResearch(field: string): void {
    updateOrders({ ...orders, research: [{ field, allocation: 100 }] }, `${field} research prioritized`);
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
    <div class="turn-block"><Icon name="calendar" size={18}/><span><strong>Year {2195 + connection.turnNumber}</strong><small>Turn {connection.turnNumber}</small></span></div>
    <div class="empire-block"><Icon name="shield" size={22}/><span><strong>Nova Dominion</strong><small>{demoMode ? 'Demonstration universe' : `Player ${connection.playerId}`}</small></span></div>
    <div class="resource-bar">
      {#each topResources as resource}<div><Icon name={resource.icon} size={17}/><span><strong>{resource.value}</strong><small>{resource.income}</small></span></div>{/each}
    </div>
    <button class="turn-state" onclick={onRefresh}><span class:spinning={busy}></span><strong>{busy ? 'SYNCING' : 'YOUR TURN'}</strong></button>
    <button class="top-icon" aria-label="Technical orders" onclick={openTechnical}><Icon name="edit" /></button>
    <button class="top-icon" aria-label="Exit to menu" onclick={onExit}><Icon name="power" /></button>
  </header>

  <div class="game-grid" class:panel-closed={!rightPanelOpen}>
    <nav class="sidebar" class:open={sidebarOpen}>
      {#each navigation as item}
        <button class:active={activeSection === item.id} onclick={() => { activeSection = item.id; sidebarOpen = false; }}><Icon name={item.icon} size={25}/><span>{item.label}</span></button>
      {/each}
      <div class="sidebar-spacer"></div>
      <button onclick={openTechnical}><Icon name="edit" size={23}/><span>Orders JSON</span></button>
      <button onclick={onExit}><Icon name="power" size={23}/><span>Main menu</span></button>
    </nav>

    <main class="content-area">
      {#if activeSection === 'galaxy'}
        <GalaxyMap selectedId={selectedSystem.id} onSelect={selectSystem}/>
      {:else}
        <SectionViews section={activeSection} {status} onSelectSystem={selectSystem} onResearch={prioritizeResearch}/>
      {/if}
    </main>

    <div class="right-wrap" class:open={rightPanelOpen}>
      <button class="panel-toggle" aria-label="Toggle detail panel" onclick={() => (rightPanelOpen = !rightPanelOpen)}><Icon name={rightPanelOpen ? 'chevron-right' : 'chevron-left'} size={17}/></button>
      <DetailPanel system={selectedSystem} onBuild={addProduction} onWaypoint={() => addWaypoint('move')}/>
    </div>
  </div>

  <footer class="command-bar">
    <button onclick={() => addWaypoint('move')}><Icon name="target" size={28}/><span><strong>Set waypoint</strong><small>Plan fleet route</small></span></button>
    <button onclick={() => addWaypoint('colonize')} disabled={selectedSystem.owner !== 'neutral'}><Icon name="colonize" size={28}/><span><strong>Colonize</strong><small>{selectedSystem.owner === 'neutral' ? 'Establish colony' : 'Select unclaimed system'}</small></span></button>
    <button onclick={() => addProduction('Orbital Factory')} disabled={selectedSystem.owner !== 'player'}><Icon name="build" size={28}/><span><strong>Build</strong><small>Construct on planet</small></span></button>
    <button onclick={() => (activeSection = 'research')}><Icon name="research" size={28}/><span><strong>Research</strong><small>Choose new technology</small></span></button>
    <button class="draft-button" disabled={busy || demoMode} onclick={onSaveDraft}><Icon name="load" size={24}/><span><strong>Save draft</strong><small>{demoMode ? 'Demo is local only' : 'Store current orders'}</small></span></button>
    <button class="submit-button" disabled={busy || demoMode} onclick={onSubmit}><span class="submit-ring"><Icon name="play" size={22}/></span><span><strong>Submit turn</strong><small>{demoMode ? 'Connect to submit' : 'End turn and proceed'}</small></span></button>
  </footer>

  {#if message || localNotice}<div class="toast" class:error={(message || localNotice).toLowerCase().includes('error')}>{localNotice || message}</div>{/if}

  {#if showTechnical}
    <div class="modal-backdrop" role="presentation" onclick={() => (showTechnical = false)}>
      <section class="technical-modal panel-cut" role="dialog" aria-modal="true" aria-labelledby="technical-title" onclick={(event) => event.stopPropagation()}>
        <header><div><p>Compatibility tools</p><h2 id="technical-title">Order JSON</h2></div><button class="icon-button" aria-label="Close" onclick={() => (showTechnical = false)}><Icon name="close" /></button></header>
        <p>The visual controls write to the same <code>orders</code> object used by the original MVP client. Advanced or future engine orders can still be edited here.</p>
        <textarea bind:value={technicalText} spellcheck="false"></textarea>
        <div class="modal-actions"><button onclick={() => (showTechnical = false)}>Cancel</button><button class="primary-action" onclick={applyTechnical}>Apply JSON</button><button disabled={demoMode || busy} onclick={onReopen}>Reopen submitted turn</button></div>
      </section>
    </div>
  {/if}
</section>

<style>
  .game-shell { height:100svh; min-height:650px; display:grid; grid-template-rows:66px minmax(0,1fr) 82px; overflow:hidden; background:#02070e; color:#b7cad7 }
  .topbar { display:grid; grid-template-columns:220px 125px 190px minmax(300px,1fr) 130px 42px 42px; align-items:stretch; border-bottom:1px solid rgba(58,170,225,.25); background:linear-gradient(180deg,#071421,#030a12); z-index:20 }.top-logo,.turn-block,.empire-block,.resource-bar,.turn-state,.top-icon { border-right:1px solid rgba(62,143,187,.16) }.top-logo { display:flex;align-items:center;padding:0 .9rem }.turn-block,.empire-block { display:flex;align-items:center;gap:.6rem;padding:0 .8rem;color:#6dcfff }.turn-block strong,.turn-block small,.empire-block strong,.empire-block small{display:block}.turn-block strong,.empire-block strong{color:#dbeaf3;font-size:.76rem;font-weight:500}.turn-block small,.empire-block small{color:#70899b;font-size:.63rem;margin-top:.18rem}.resource-bar{display:flex;align-items:center;justify-content:center}.resource-bar>div{display:flex;align-items:center;gap:.4rem;padding:0 .8rem;color:#55caff;border-right:1px solid rgba(62,143,187,.13)}.resource-bar>div:last-child{border:0}.resource-bar strong,.resource-bar small{display:block}.resource-bar strong{color:#e0edf4;font-size:.72rem}.resource-bar small{color:#6d899b;font-size:.58rem}.turn-state,.top-icon,.mobile-menu{border:0;background:transparent;color:#55cdff;cursor:pointer}.turn-state{display:flex;align-items:center;justify-content:center;gap:.6rem;letter-spacing:.1em}.turn-state span{width:24px;height:24px;border:3px solid #42c8ff;border-right-color:transparent;border-radius:50%}.turn-state span.spinning{animation:spin 1s linear infinite}.turn-state strong{font-size:.72rem}.top-icon{display:grid;place-items:center}.top-icon:hover{background:rgba(16,57,82,.45);color:#e9faff}.mobile-menu{display:none}
  .game-grid { min-height:0; display:grid; grid-template-columns:196px minmax(0,1fr) 330px; transition:grid-template-columns .2s }.game-grid.panel-closed{grid-template-columns:196px minmax(0,1fr) 0}.sidebar { min-height:0; display:flex;flex-direction:column;background:linear-gradient(180deg,#06121e,#020911);border-right:1px solid rgba(58,164,219,.25);z-index:12}.sidebar button{min-height:72px;border:0;border-bottom:1px solid rgba(64,143,184,.13);background:transparent;color:#8ba4b5;display:flex;align-items:center;gap:.9rem;padding:0 1.15rem;text-transform:uppercase;letter-spacing:.07em;font:inherit;font-size:.73rem;cursor:pointer;text-align:left}.sidebar button:hover,.sidebar button.active{color:#e2f6ff;background:linear-gradient(90deg,rgba(12,66,101,.78),rgba(4,20,33,.4));box-shadow:inset 3px 0 #43c9ff}.sidebar button.active{border-color:rgba(67,201,255,.4)}.sidebar-spacer{flex:1}.sidebar button:nth-last-child(-n+2){min-height:52px;font-size:.65rem}.content-area{min-width:0;min-height:0;overflow:hidden}.right-wrap{position:relative;min-width:0;overflow:visible;transition:.2s}.right-wrap:not(.open){overflow:visible}.right-wrap:not(.open) :global(.detail-panel){display:none}.panel-toggle{position:absolute;left:-28px;top:10px;width:28px;height:42px;display:grid;place-items:center;border:1px solid rgba(56,164,218,.35);border-right:0;background:#061522;color:#5dccfa;cursor:pointer;z-index:6}
  .command-bar { display:grid;grid-template-columns:repeat(4,minmax(135px,1fr)) 145px 215px;gap:8px;padding:8px 10px;border-top:1px solid rgba(62,170,225,.28);background:linear-gradient(180deg,#06111c,#02070d);z-index:18}.command-bar button{border:1px solid rgba(58,174,231,.42);background:linear-gradient(180deg,rgba(7,34,52,.8),rgba(3,15,25,.9));color:#5ecbff;display:flex;align-items:center;justify-content:center;gap:.7rem;font:inherit;cursor:pointer}.command-bar button:hover:not(:disabled){border-color:#48c9ff;background:rgba(10,50,75,.9)}.command-bar button:disabled{opacity:.35;cursor:not-allowed}.command-bar strong,.command-bar small{display:block;text-align:left}.command-bar strong{text-transform:uppercase;letter-spacing:.06em;font-size:.72rem;color:#70d4ff}.command-bar small{color:#71899a;font-size:.6rem;margin-top:.16rem}.command-bar .draft-button{border-color:rgba(105,155,185,.32)}.command-bar .submit-button{border-color:#ffc139;background:linear-gradient(180deg,rgba(104,69,5,.75),rgba(55,34,1,.9));color:#ffcd53;box-shadow:inset 0 0 18px rgba(255,179,25,.1),0 0 12px rgba(255,174,20,.13)}.command-bar .submit-button strong{color:#ffd25b}.submit-ring{width:35px;height:35px;border:3px solid #ffc646;border-left-color:transparent;border-radius:50%;display:grid;place-items:center}
  .toast { position:fixed;right:1rem;bottom:94px;max-width:430px;padding:.8rem 1rem;border:1px solid rgba(70,197,255,.5);background:rgba(5,27,41,.96);color:#bfe9fc;font-size:.76rem;z-index:40;box-shadow:0 10px 35px rgba(0,0,0,.4)}.toast.error{border-color:rgba(255,84,84,.6);color:#ffc2c2;background:rgba(55,8,14,.96)}
  .modal-backdrop{position:fixed;inset:0;display:grid;place-items:center;padding:1rem;background:rgba(0,5,10,.82);backdrop-filter:blur(5px);z-index:50}.technical-modal{width:min(760px,96vw);max-height:90vh;display:grid;grid-template-rows:auto auto minmax(260px,1fr) auto;gap:.8rem;padding:1rem;background:#061522;border:1px solid rgba(66,190,244,.4)}.technical-modal header{display:flex;justify-content:space-between;align-items:center}.technical-modal header p{margin:0;color:#52c9fc;text-transform:uppercase;font-size:.62rem;letter-spacing:.12em}.technical-modal h2{margin:.2rem 0 0;color:#eef9ff;font-size:1.15rem}.technical-modal>p{margin:0;color:#8299aa;font-size:.72rem}.technical-modal code{color:#5dccfa}.technical-modal textarea{width:100%;height:100%;min-height:260px;resize:vertical;box-sizing:border-box;border:1px solid rgba(57,155,205,.3);background:#01070c;color:#a9d6eb;padding:.8rem;font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace;outline:0}.technical-modal textarea:focus{border-color:#45caff}.modal-actions{display:flex;justify-content:flex-end;gap:.6rem;flex-wrap:wrap}.modal-actions button{min-height:38px;padding:0 1rem;border:1px solid rgba(68,166,216,.35);background:#081d2c;color:#8ecfea;cursor:pointer}.modal-actions .primary-action{border-color:#44c9ff;color:#e1f7ff}.modal-actions button:disabled{opacity:.4}.icon-button{width:36px;height:36px;display:grid;place-items:center;border:1px solid rgba(61,165,218,.3);background:rgba(4,19,31,.75);color:#72cfff;cursor:pointer}
  @keyframes spin{to{transform:rotate(360deg)}}
  @media(max-width:1240px){.topbar{grid-template-columns:190px 115px 165px 1fr 115px 40px 40px}.resource-bar>div{padding:0 .45rem}.command-bar{grid-template-columns:repeat(4,1fr) 120px 180px}.command-bar small{display:none}.game-grid{grid-template-columns:170px minmax(0,1fr) 300px}.game-grid.panel-closed{grid-template-columns:170px minmax(0,1fr) 0}.sidebar button{padding:0 .85rem}}
  @media(max-width:980px){.game-shell{grid-template-rows:58px minmax(0,1fr) 72px}.topbar{grid-template-columns:50px 1fr 110px 42px 42px}.mobile-menu{display:grid;place-items:center}.top-logo{border-left:1px solid rgba(62,143,187,.16)}.empire-block,.resource-bar{display:none}.game-grid,.game-grid.panel-closed{grid-template-columns:minmax(0,1fr) 300px}.sidebar{position:fixed;left:0;top:58px;bottom:72px;width:220px;transform:translateX(-102%);transition:.2s;box-shadow:15px 0 30px rgba(0,0,0,.45)}.sidebar.open{transform:translateX(0)}.command-bar{grid-template-columns:repeat(4,1fr) 160px}.draft-button{display:none!important}.command-bar button{gap:.4rem}.command-bar button>svg{width:22px}.command-bar .submit-button{grid-column:auto}.right-wrap:not(.open){display:none}}
  @media(max-width:760px){.topbar{grid-template-columns:46px 1fr 90px 40px}.turn-block{display:none}.top-icon:nth-last-child(2){display:none}.game-grid,.game-grid.panel-closed{grid-template-columns:minmax(0,1fr)}.right-wrap{position:fixed;right:0;top:58px;bottom:72px;width:min(330px,88vw);z-index:25;transform:translateX(100%);transition:.2s;box-shadow:-15px 0 35px rgba(0,0,0,.45)}.right-wrap.open{transform:translateX(0)}.right-wrap:not(.open){display:block}.right-wrap:not(.open) :global(.detail-panel){display:block}.panel-toggle{left:-34px;width:34px}.command-bar{grid-template-columns:repeat(4,1fr) 1.2fr;padding:5px;gap:4px}.command-bar button{display:grid;place-items:center}.command-bar button span:not(.submit-ring){display:none}.command-bar button>svg{margin:auto}.submit-button{display:flex!important}.submit-button>span:last-child{display:block!important}.submit-button small{display:none}.sidebar{bottom:72px}}
  @media(max-width:480px){.top-logo :global(.title){font-size:.74rem!important}.command-bar{grid-template-columns:repeat(4,1fr) 1.3fr}.submit-button strong{font-size:.6rem}.game-shell{min-height:560px}}
</style>
