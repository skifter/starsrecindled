<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import { OWNER_COLORS, ownerForPlayerId } from '../player-colors';
  import type { AccountTurnStatusPlayer, FleetOrder, FleetSummary, PlayerOrders, StarSystem } from '../types';

  export let systems: StarSystem[] = [];
  export let players: AccountTurnStatusPlayer[] = [];
  export let currentPlayerId = 0;
  export let selectedFleetId = '';
  export let orders: PlayerOrders = { fleets: [], production: [] };
  export let editableTurn = false;
  export let onLocate: (fleet: FleetSummary, system: StarSystem) => void = () => {};
  export let onPlanRoute: (fleet: FleetSummary, system: StarSystem) => void = () => {};

  $: playerIds = players.map((player) => player.id);
  $: rows = systems
    .flatMap((system) => system.fleets.map((fleet) => ({ fleet, system })))
    .sort((a, b) => {
      const ownDelta = Number(b.fleet.ownerPlayerId === currentPlayerId) - Number(a.fleet.ownerPlayerId === currentPlayerId);
      return ownDelta || a.fleet.name.localeCompare(b.fleet.name);
    });
  $: ownRows = rows.filter(({ fleet }) => fleet.ownerPlayerId === currentPlayerId);
  $: visibleOtherRows = rows.filter(({ fleet }) => fleet.ownerPlayerId !== currentPlayerId);
  $: totalShips = ownRows.reduce((sum, { fleet }) => sum + fleet.ships, 0);

  function fleetColor(fleet: FleetSummary): string {
    return fleet.ownerPlayerId ? OWNER_COLORS[ownerForPlayerId(fleet.ownerPlayerId, playerIds)] : OWNER_COLORS.neutral;
  }

  function ownerName(fleet: FleetSummary): string {
    if (fleet.ownerPlayerId === currentPlayerId) return 'You';
    return fleet.ownerLabel ?? players.find((player) => player.id === fleet.ownerPlayerId)?.name ?? `Player ${fleet.ownerPlayerId}`;
  }

  function orderFor(fleetId: string): FleetOrder | undefined {
    return (orders.fleets ?? []).find((order) => order.fleetId === fleetId);
  }

  function targetName(order: FleetOrder | undefined): string {
    if (!order?.targetSystemId) return '';
    return systems.find((system) => system.id === order.targetSystemId)?.name ?? order.targetSystemId;
  }
</script>

<section class="fleets-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">Live fleet command</p>
      <h1>Fleets</h1>
      <p class="intro">Select a fleet to locate it on the galaxy map, or set a waypoint directly from this overview.</p>
    </div>
    <div class="fleet-summary"><span><strong>{ownRows.length}</strong><small>your fleets</small></span><span><strong>{totalShips.toLocaleString('en-US')}</strong><small>ships</small></span></div>
  </header>

  <div class="fleet-table panel-cut">
    <div class="fleet-row table-head">
      <span>Fleet</span><span>Location</span><span>Ships</span><span>Order</span><span>Actions</span>
    </div>

    {#if ownRows.length > 0}
      {#each ownRows as row}
        {@const order = orderFor(row.fleet.id)}
        <article class="fleet-row own" class:selected={row.fleet.id === selectedFleetId} style={`--fleet-color:${fleetColor(row.fleet)}`}>
          <button class="fleet-name" onclick={() => onLocate(row.fleet, row.system)}>
            <span class="fleet-icon"><Icon name="fleet" size={20}/></span>
            <span><strong>{row.fleet.name}</strong><small>{row.fleet.role}</small></span>
          </button>
          <button class="location" onclick={() => onLocate(row.fleet, row.system)}><strong>{row.system.name}</strong><small>{row.system.ownerPlayerId === currentPlayerId ? 'Your colony' : row.system.ownerPlayerId === null ? 'Unclaimed' : row.system.ownerLabel}</small></button>
          <span class="ships"><strong>{row.fleet.ships.toLocaleString('en-US')}</strong><small>{(row.fleet.colonizationCapacity ?? 0) > 0 ? `Colony module ×${row.fleet.colonizationCapacity}` : 'ships'}</small></span>
          <span class="order-state">
            {#if order?.action === 'move'}<strong>Waypoint</strong><small>→ {targetName(order)}</small>
            {:else if order?.action === 'colonize'}<strong>Colonize</strong><small>{targetName(order)}</small>
            {:else}<strong>Idle</strong><small>No draft order</small>{/if}
          </span>
          <span class="actions"><button onclick={() => onLocate(row.fleet, row.system)}><Icon name="galaxy" size={15}/>Locate</button><button class="route" disabled={!editableTurn} onclick={() => onPlanRoute(row.fleet, row.system)}><Icon name="target" size={15}/>Waypoint</button></span>
        </article>
      {/each}
    {:else}
      <div class="empty"><Icon name="fleet" size={36}/><p>You have no fleets in the current game state.</p></div>
    {/if}
  </div>

  {#if visibleOtherRows.length > 0}
    <section class="other-fleets">
      <h2>Other visible fleets</h2>
      <div class="other-grid">
        {#each visibleOtherRows as row}
          <button class="other-card" style={`--fleet-color:${fleetColor(row.fleet)}`} onclick={() => onLocate(row.fleet, row.system)}>
            <span class="fleet-icon"><Icon name="fleet" size={18}/></span>
            <span><strong>{row.fleet.name}</strong><small>{ownerName(row.fleet)} · {row.system.name} · {row.fleet.ships.toLocaleString('en-US')} ships</small></span>
          </button>
        {/each}
      </div>
    </section>
  {/if}
</section>

<style>
  .fleets-view{height:100%;overflow:auto;padding:1.35rem;background:radial-gradient(circle at 46% 16%,rgba(15,89,128,.14),transparent 42%),#030912;color:#91a8b7}
  .view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}.eyebrow{margin:0 0 .3rem;color:#43c5ff;text-transform:uppercase;letter-spacing:.14em;font-size:.65rem}h1{margin:0;color:#edf9ff;font-size:1.65rem;font-weight:500;letter-spacing:.08em}.intro{max-width:680px;margin:.5rem 0 0;color:#7f98aa;font-size:.76rem;line-height:1.5}.fleet-summary{display:flex;gap:1.2rem}.fleet-summary span{text-align:right}.fleet-summary strong,.fleet-summary small{display:block}.fleet-summary strong{color:#e4f4fb;font-size:1rem}.fleet-summary small{margin-top:.15rem;color:#648296;text-transform:uppercase;font-size:.55rem;letter-spacing:.08em}
  .fleet-table{border:1px solid rgba(58,154,207,.24);background:rgba(4,16,28,.92);overflow:hidden}.fleet-row{display:grid;grid-template-columns:minmax(230px,1.5fr) minmax(130px,.8fr) 90px minmax(130px,.8fr) 190px;gap:.75rem;align-items:center;min-height:68px;padding:0 .85rem;border-bottom:1px solid rgba(57,132,173,.13);font-size:.72rem}.fleet-row:last-child{border-bottom:0}.table-head{min-height:38px;color:#55c8f8;text-transform:uppercase;letter-spacing:.09em;font-size:.59rem;background:rgba(9,38,57,.45)}.fleet-row.own{box-shadow:inset 3px 0 var(--fleet-color)}.fleet-row.selected{background:linear-gradient(90deg,rgba(255,208,92,.11),rgba(4,16,28,.2));box-shadow:inset 3px 0 #ffd05c}.fleet-name,.location{display:flex;align-items:center;gap:.55rem;min-width:0;border:0;background:transparent;color:inherit;text-align:left;cursor:pointer;font:inherit}.fleet-name>span:last-child,.location{min-width:0}.fleet-name strong,.fleet-name small,.location strong,.location small,.ships strong,.ships small,.order-state strong,.order-state small{display:block}.fleet-name strong,.location strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#d9eaf2;font-weight:500}.fleet-name small,.location small,.ships small,.order-state small{margin-top:.14rem;color:#6d899b;font-size:.56rem}.fleet-icon{width:34px;height:34px;flex:none;display:grid;place-items:center;border:1px solid color-mix(in srgb,var(--fleet-color) 55%,transparent);background:color-mix(in srgb,var(--fleet-color) 8%,transparent);color:var(--fleet-color)}.ships strong{color:#ecf7fb;font-size:.8rem}.order-state strong{color:#8bdcff;font-weight:500}.order-state small{color:#d3b969}.actions{display:flex;justify-content:flex-end;gap:.4rem}.actions button{min-height:34px;display:flex;align-items:center;gap:.3rem;padding:0 .55rem;border:1px solid rgba(65,159,207,.28);background:rgba(7,31,47,.8);color:#76ccef;font:inherit;font-size:.59rem;cursor:pointer}.actions .route{border-color:rgba(255,208,92,.35);color:#e5c461}.actions button:hover:not(:disabled){background:rgba(12,55,79,.95)}.actions button:disabled{opacity:.35;cursor:not-allowed}
  .empty{min-height:210px;display:grid;place-items:center;align-content:center;gap:.45rem;color:#56caff}.empty p{margin:0;color:#738fa2}.other-fleets{margin-top:1rem}.other-fleets h2{margin:0 0 .55rem;color:#7d9bad;text-transform:uppercase;letter-spacing:.08em;font-size:.68rem}.other-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:.5rem}.other-card{min-height:58px;display:flex;align-items:center;gap:.55rem;padding:.5rem .65rem;border:1px solid rgba(60,137,178,.2);border-left:3px solid var(--fleet-color);background:rgba(4,17,29,.85);color:#899fae;text-align:left;cursor:pointer}.other-card span:last-child{min-width:0}.other-card strong,.other-card small{display:block}.other-card strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#cadbe4;font-size:.69rem;font-weight:500}.other-card small{margin-top:.15rem;color:#667f90;font-size:.56rem}
  @media(max-width:1050px){.fleet-row{grid-template-columns:minmax(210px,1.4fr) minmax(120px,.8fr) 75px minmax(120px,.8fr)}.fleet-row>span:last-child{grid-column:4}.table-head>span:last-child{display:none}.actions{grid-column:auto}.actions button:first-child{display:none}}
  @media(max-width:720px){.fleets-view{padding:.8rem}.view-header{display:grid}.fleet-summary{justify-content:flex-start}.fleet-summary span{text-align:left}.fleet-row{grid-template-columns:1fr 80px;gap:.5rem;padding:.55rem .65rem}.table-head{display:none}.fleet-row>.location,.fleet-row>.order-state{grid-column:1}.fleet-row>.ships{grid-column:2;grid-row:1;text-align:right}.fleet-row>.actions{grid-column:2;grid-row:2/4;display:grid}.actions button{justify-content:center}.actions .route{display:flex!important}}
</style>
