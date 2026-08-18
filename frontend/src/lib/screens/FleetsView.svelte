<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import { OWNER_COLORS, ownerForPlayerId } from '../player-colors';
  import type {
    AccountTurnStatusPlayer,
    FleetCompositionEntry,
    FleetOrder,
    FleetSummary,
    ModelCatalog,
    PlayerOrders,
    ShipDesign,
    StarSystem
  } from '../types';

  export let systems: StarSystem[] = [];
  export let players: AccountTurnStatusPlayer[] = [];
  export let currentPlayerId = 0;
  export let sensorSystemCount = 0;
  export let selectedFleetId = '';
  export let orders: PlayerOrders = { fleets: [], production: [] };
  export let editableTurn = false;
  export let modelCatalog: ModelCatalog | null = null;
  export let fuelEfficiencyPercent = 0;
  export let onLocate: (fleet: FleetSummary, system: StarSystem) => void = () => {};
  export let onPlanRoute: (fleet: FleetSummary, system: StarSystem) => void = () => {};
  export let onOrdersChange: (orders: PlayerOrders, notice: string) => void = () => {};

  let managedFleetId = '';
  let selectedDesignId = '';
  let targetFleetId = '';
  let targetDesignId = '';
  let quantity = 1;
  let fleetName = '';

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
  $: managedRow = ownRows.find(({ fleet }) => fleet.id === managedFleetId) ?? null;
  $: selectedEntry = managedRow?.fleet.composition?.find((entry) => entry.designId === selectedDesignId) ?? managedRow?.fleet.composition?.[0] ?? null;
  $: sameSystemTargets = managedRow
    ? ownRows.filter(({ fleet, system }) =>
        fleet.id !== managedRow?.fleet.id
        && system.id === managedRow?.system.id
        && !fleet.refit
      )
    : [];
  $: sourceDesign = selectedEntry ? designById(selectedEntry.designId) : null;
  $: refitTargets = sourceDesign
    ? (modelCatalog?.designs ?? []).filter((design) =>
        design.family === sourceDesign?.family
        && design.generation > (sourceDesign?.generation ?? 0)
        && design.unlocked
        && !design.obsolete
      )
    : [];
  $: selectedTargetDesign = refitTargets.find((design) => design.id === targetDesignId) ?? refitTargets[0] ?? null;
  $: maxQuantity = selectedEntry?.quantity ?? 1;
  $: safeQuantity = Math.max(1, Math.min(quantity || 1, maxQuantity || 1));
  $: refitEstimate = sourceDesign && selectedTargetDesign
    ? estimateRefitCost(sourceDesign, selectedTargetDesign, safeQuantity)
    : 0;

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

  function targetingOrderFor(fleetId: string): FleetOrder | undefined {
    return (orders.fleets ?? []).find((order) => order.targetFleetId === fleetId);
  }

  function targetName(order: FleetOrder | undefined): string {
    if (!order?.targetSystemId) return '';
    return systems.find((system) => system.id === order.targetSystemId)?.name ?? order.targetSystemId;
  }

  function fleetNameById(fleetId: string | undefined): string {
    if (!fleetId) return '';
    return ownRows.find(({ fleet }) => fleet.id === fleetId)?.fleet.name ?? fleetId;
  }

  function designById(designId: string): ShipDesign | null {
    return modelCatalog?.designs.find((design) => design.id === designId) ?? null;
  }

  function openManager(fleet: FleetSummary): void {
    managedFleetId = fleet.id;
    fleetName = fleet.name;
    selectedDesignId = fleet.composition?.[0]?.designId ?? '';
    targetFleetId = '';
    targetDesignId = '';
    quantity = 1;
  }

  function setSelectedDesign(designId: string): void {
    selectedDesignId = designId;
    targetDesignId = '';
    quantity = 1;
  }

  function queueFleetOrder(order: FleetOrder, notice: string, involvedFleetIds: string[] = [order.fleetId]): void {
    const involved = new Set(involvedFleetIds);
    const next = (orders.fleets ?? []).filter((existing) => {
      if (involved.has(existing.fleetId)) return false;
      return !existing.targetFleetId || !involved.has(existing.targetFleetId);
    });
    onOrdersChange({ ...orders, fleets: [...next, order] }, notice);
  }

  function clearFleetOrder(fleetId: string): void {
    const next = (orders.fleets ?? []).filter((order) => order.fleetId !== fleetId);
    onOrdersChange({ ...orders, fleets: next }, 'Fleet draft order removed.');
  }

  function queueRename(): void {
    if (!managedRow) return;
    const name = fleetName.trim();
    if (!name) return;
    queueFleetOrder(
      { fleetId: managedRow.fleet.id, action: 'rename', name },
      `${managedRow.fleet.name} will be renamed to ${name}.`
    );
  }

  function queueSplit(): void {
    if (!managedRow || !selectedEntry) return;
    const count = Math.max(1, Math.min(safeQuantity, managedRow.fleet.ships - 1, selectedEntry.quantity));
    if (count < 1) return;
    queueFleetOrder(
      {
        fleetId: managedRow.fleet.id,
        action: 'split',
        designId: selectedEntry.designId,
        quantity: count,
        name: `${managedRow.fleet.name} Detachment`
      },
      `Split ${count} × ${selectedEntry.designName} from ${managedRow.fleet.name}.`
    );
  }

  function queueTransfer(): void {
    if (!managedRow || !selectedEntry || !targetFleetId) return;
    const count = Math.max(1, Math.min(safeQuantity, managedRow.fleet.ships - 1, selectedEntry.quantity));
    if (count < 1) return;
    queueFleetOrder(
      {
        fleetId: managedRow.fleet.id,
        action: 'transfer',
        targetFleetId,
        designId: selectedEntry.designId,
        quantity: count
      },
      `Transfer ${count} × ${selectedEntry.designName} to ${fleetNameById(targetFleetId)}.`,
      [managedRow.fleet.id, targetFleetId]
    );
  }

  function queueMerge(): void {
    if (!managedRow || !targetFleetId) return;
    queueFleetOrder(
      { fleetId: managedRow.fleet.id, action: 'merge', targetFleetId },
      `Merge ${managedRow.fleet.name} into ${fleetNameById(targetFleetId)}.`,
      [managedRow.fleet.id, targetFleetId]
    );
  }

  function queueRefit(): void {
    if (!managedRow || !selectedEntry || !selectedTargetDesign) return;
    queueFleetOrder(
      {
        fleetId: managedRow.fleet.id,
        action: 'refit',
        designId: selectedEntry.designId,
        targetDesignId: selectedTargetDesign.id,
        quantity: safeQuantity
      },
      `Refit queued: ${safeQuantity} × ${selectedEntry.designName} → ${selectedTargetDesign.name} (~${refitEstimate} industry, 2 turns).`
    );
  }

  function orderLabel(order: FleetOrder | undefined): string {
    if (!order) return 'Idle';
    switch (order.action) {
      case 'move': return `Waypoint → ${targetName(order)}`;
      case 'colonize': return `Colonize ${targetName(order)}`;
      case 'rename': return `Rename → ${order.name ?? ''}`;
      case 'split': return `Split ${order.quantity ?? 0}`;
      case 'transfer': return `Transfer ${order.quantity ?? 0} → ${fleetNameById(order.targetFleetId)}`;
      case 'merge': return `Merge → ${fleetNameById(order.targetFleetId)}`;
      case 'refit': return `Refit ${order.quantity ?? 0} ship(s)`;
      default: return 'Hold';
    }
  }

  function estimateRefitCost(source: ShipDesign, target: ShipDesign, count: number): number {
    const sourceByCategory = new Map(source.components.map((component) => [component.category, component]));
    const targetByCategory = new Map(target.components.map((component) => [component.category, component]));
    const categories = new Set([...sourceByCategory.keys(), ...targetByCategory.keys()]);
    let changedBatchCost = 0;

    for (const category of categories) {
      const from = sourceByCategory.get(category);
      const to = targetByCategory.get(category);
      if (from?.modelId === to?.modelId) continue;
      const fromModel = modelCatalog?.components.find((component) => component.id === from?.modelId);
      const toModel = modelCatalog?.components.find((component) => component.id === to?.modelId);
      const fromCost = Math.max(0, Number(fromModel?.stats.industryCost ?? 0));
      const toCost = Math.max(0, Number(toModel?.stats.industryCost ?? 0));
      if (toCost > 0) changedBatchCost += Math.max(1, toCost - Math.floor(fromCost / 2));
      else if (fromCost > 0) changedBatchCost += Math.max(1, Math.ceil(fromCost * 0.1));
    }

    return Math.max(1, Math.ceil(Math.max(1, changedBatchCost) * Math.max(1, count) / Math.max(1, target.batchSize)));
  }

  function compositionSummary(fleet: FleetSummary): string {
    return fleet.composition?.map((entry) => `${entry.designName} ×${entry.quantity}`).join(' · ')
      ?? ((fleet.colonizationCapacity ?? 0) > 0 ? `Colony module ×${fleet.colonizationCapacity}` : 'legacy ships');
  }

  function structuralDisabled(fleet: FleetSummary): boolean {
    return !editableTurn || Boolean(fleet.refit);
  }
</script>

<section class="fleets-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">Live fleet command</p>
      <h1>Fleets</h1>
      <p class="intro">Fleets now preserve exact ship generations and can be renamed, split, merged or reorganized at the same system. Refit is explicit: it costs industry at one of your colonies, takes two turns, and does not change the old hardware until completion.</p>
    </div>
    <div class="fleet-summary">
      <span><strong>{ownRows.length}</strong><small>your fleets</small></span>
      <span><strong>{totalShips.toLocaleString('en-US')}</strong><small>ships</small></span>
      <span><strong>{sensorSystemCount}</strong><small>systems scanned</small></span>
      <span><strong>{fuelEfficiencyPercent}%</strong><small>fuel saving</small></span>
    </div>
  </header>

  <div class="fleet-table panel-cut">
    <div class="fleet-row table-head">
      <span>Fleet</span><span>Location</span><span>Ships / model</span><span>Order / capability</span><span>Actions</span>
    </div>

    {#if ownRows.length > 0}
      {#each ownRows as row}
        {@const order = orderFor(row.fleet.id)}
        {@const targetingOrder = targetingOrderFor(row.fleet.id)}
        <article class="fleet-row own" class:selected={row.fleet.id === selectedFleetId || row.fleet.id === managedFleetId} style={`--fleet-color:${fleetColor(row.fleet)}`}>
          <button class="fleet-name" onclick={() => onLocate(row.fleet, row.system)}>
            <span class="fleet-icon"><Icon name="fleet" size={20}/></span>
            <span>
              <strong>{row.fleet.name}</strong>
              <small>{row.fleet.role}{#if row.fleet.refit} · REFIT {row.fleet.refit.turnsRemaining}/{row.fleet.refit.turnsTotal}{/if}</small>
            </span>
          </button>
          <button class="location" onclick={() => onLocate(row.fleet, row.system)}>
            <strong>{row.system.name}</strong>
            <small>{row.system.ownerPlayerId === currentPlayerId ? 'Your colony' : row.system.ownerPlayerId === null ? 'Unclaimed' : row.system.ownerLabel}</small>
          </button>
          <span class="ships">
            <strong>{row.fleet.ships.toLocaleString('en-US')}</strong>
            <small>{compositionSummary(row.fleet)}</small>
          </span>
          <span class="order-state">
            {#if order}
              <strong>{orderLabel(order)}</strong><small>Draft order</small>
            {:else if targetingOrder}
              <strong>Reserved</strong><small>Target of {targetingOrder.action} from {fleetNameById(targetingOrder.fleetId)}</small>
            {:else if row.fleet.refit}
              <strong>In refit</strong><small>{row.fleet.refit.fromDesignName} → {row.fleet.refit.toDesignName}</small>
            {:else}
              <strong>Idle</strong><small>No draft order</small>
            {/if}
            <small class="capability">SPD {row.fleet.movementRange ?? 1} · SEN {row.fleet.sensorRange ?? 0} · ATK {row.fleet.attack ?? 0} · DEF {row.fleet.defense ?? 0}{#if (row.fleet.colonizationCapacity ?? 0) > 0} · COL {row.fleet.colonizationCapacity}{/if}</small>
          </span>
          <span class="actions">
            <button onclick={() => onLocate(row.fleet, row.system)}><Icon name="galaxy" size={15}/>Locate</button>
            <button class="route" disabled={structuralDisabled(row.fleet)} onclick={() => onPlanRoute(row.fleet, row.system)}><Icon name="target" size={15}/>Waypoint</button>
            <button class="manage" disabled={!editableTurn} onclick={() => openManager(row.fleet)}><Icon name="edit" size={15}/>Manage</button>
          </span>
        </article>
      {/each}
    {:else}
      <div class="empty"><Icon name="fleet" size={36}/><p>You have no fleets in the current game state.</p></div>
    {/if}
  </div>

  {#if managedRow}
    {@const draftOrder = orderFor(managedRow.fleet.id)}
    <section class="fleet-manager panel-cut">
      <header>
        <div>
          <p class="eyebrow">Fleet management</p>
          <h2>{managedRow.fleet.name}</h2>
          <p>{managedRow.system.name} · {managedRow.fleet.ships} ships · exact generations stay intact until an explicit transfer or refit completes.</p>
        </div>
        <button class="close-manager" onclick={() => (managedFleetId = '')}>Close</button>
      </header>

      {#if draftOrder}
        <div class="draft-banner">
          <span><strong>Draft:</strong> {orderLabel(draftOrder)}</span>
          <button onclick={() => clearFleetOrder(managedRow.fleet.id)}>Remove draft</button>
        </div>
      {/if}

      {#if managedRow.fleet.refit}
        <div class="refit-banner">
          <Icon name="industry" size={22}/>
          <span>
            <strong>Refit in progress</strong>
            <small>{managedRow.fleet.refit.quantity} × {managedRow.fleet.refit.fromDesignName} → {managedRow.fleet.refit.toDesignName} · {managedRow.fleet.refit.turnsRemaining} work turn(s) remaining · {managedRow.fleet.refit.industryCost} industry already paid.</small>
          </span>
        </div>
      {/if}

      <div class="manager-grid">
        <section class="manager-card">
          <h3>Rename</h3>
          <p>Names are cosmetic and do not alter the fleet composition.</p>
          <label><span>Fleet name</span><input maxlength="40" bind:value={fleetName}/></label>
          <button class="primary" disabled={!editableTurn || !fleetName.trim()} onclick={queueRename}>Queue rename</button>
        </section>

        <section class="manager-card">
          <h3>Split / transfer</h3>
          <p>Move a specific immutable ship generation. The source fleet must keep at least one ship.</p>
          <label>
            <span>Ship generation</span>
            <select value={selectedDesignId} onchange={(event) => setSelectedDesign((event.currentTarget as HTMLSelectElement).value)}>
              {#each managedRow.fleet.composition ?? [] as entry}
                <option value={entry.designId}>{entry.designName} × {entry.quantity}</option>
              {/each}
            </select>
          </label>
          <label><span>Quantity</span><input type="number" min="1" max={Math.max(1, maxQuantity)} bind:value={quantity}/></label>
          <div class="button-pair">
            <button disabled={structuralDisabled(managedRow.fleet) || managedRow.fleet.ships <= 1 || !selectedEntry} onclick={queueSplit}>Split new fleet</button>
          </div>
          <label>
            <span>Target fleet in {managedRow.system.name}</span>
            <select bind:value={targetFleetId}>
              <option value="">Choose fleet…</option>
              {#each sameSystemTargets as target}
                <option value={target.fleet.id}>{target.fleet.name} · {target.fleet.ships} ships</option>
              {/each}
            </select>
          </label>
          <div class="button-pair">
            <button disabled={structuralDisabled(managedRow.fleet) || !targetFleetId || managedRow.fleet.ships <= 1 || !selectedEntry} onclick={queueTransfer}>Transfer ships</button>
            <button class="danger-lite" disabled={structuralDisabled(managedRow.fleet) || !targetFleetId} onclick={queueMerge}>Merge whole fleet</button>
          </div>
        </section>

        <section class="manager-card refit-card">
          <h3>Refit</h3>
          <p>Refit requires one of your colonies. Only newer generations in the same hull family are valid. Changed hardware costs industry; unchanged components are reused. The fleet is unavailable for structural orders while work is active.</p>
          {#if managedRow.system.ownerPlayerId !== currentPlayerId}
            <div class="manager-warning">Move this fleet to one of your colonies before starting a refit.</div>
          {:else if !selectedEntry}
            <div class="manager-warning">This fleet has no explicit ship-generation composition to refit.</div>
          {:else}
            <label>
              <span>Source generation</span>
              <select value={selectedDesignId} onchange={(event) => setSelectedDesign((event.currentTarget as HTMLSelectElement).value)}>
                {#each managedRow.fleet.composition ?? [] as entry}
                  <option value={entry.designId}>{entry.designName} × {entry.quantity}</option>
                {/each}
              </select>
            </label>
            <label>
              <span>Target generation</span>
              <select bind:value={targetDesignId}>
                <option value="">Newest compatible…</option>
                {#each refitTargets as design}
                  <option value={design.id}>{design.name} · Gen {design.generation}</option>
                {/each}
              </select>
            </label>
            <label><span>Quantity</span><input type="number" min="1" max={Math.max(1, maxQuantity)} bind:value={quantity}/></label>
            {#if selectedTargetDesign}
              <div class="refit-preview">
                <span><small>Target</small><strong>{selectedTargetDesign.name}</strong></span>
                <span><small>Estimated cost</small><strong>{refitEstimate} industry</strong></span>
                <span><small>Time</small><strong>2 turns</strong></span>
              </div>
            {/if}
            <button class="primary" disabled={structuralDisabled(managedRow.fleet) || !selectedTargetDesign} onclick={queueRefit}>Queue refit</button>
          {/if}
        </section>
      </div>
    </section>
  {/if}

  <section class="other-fleets">
    <div class="other-title">
      <div><h2>Other visible fleets</h2><p>Visibility follows the sensor hardware actually installed on colonies and fleets. Better scanner models extend detection; fleets outside current coverage are not sent to this client.</p></div>
      <span>{visibleOtherRows.length} detected</span>
    </div>
    {#if visibleOtherRows.length > 0}
      <div class="other-grid">
        {#each visibleOtherRows as row}
          <button class="other-card" style={`--fleet-color:${fleetColor(row.fleet)}`} onclick={() => onLocate(row.fleet, row.system)}>
            <span class="fleet-icon"><Icon name="fleet" size={18}/></span>
            <span><strong>{row.fleet.name}</strong><small>{ownerName(row.fleet)} · {row.system.name} · {row.fleet.ships.toLocaleString('en-US')} ships{row.fleet.composition?.[0] ? ` · ${row.fleet.composition[0].designName}` : ''}</small></span>
          </button>
        {/each}
      </div>
    {:else}
      <div class="no-detections"><Icon name="research" size={24}/><span><strong>No enemy fleets detected</strong><small>Enemy fleets outside your current sensor coverage are not sent to this client.</small></span></div>
    {/if}
  </section>
</section>

<style>
  .fleets-view{height:100%;overflow:auto;padding:1.35rem;background:radial-gradient(circle at 46% 16%,rgba(15,89,128,.14),transparent 42%),#030912;color:#91a8b7}
  .view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1rem}.eyebrow{margin:0 0 .3rem;color:#43c5ff;text-transform:uppercase;letter-spacing:.14em;font-size:.65rem}h1{margin:0;color:#edf9ff;font-size:1.65rem;font-weight:500;letter-spacing:.08em}.intro{max-width:760px;margin:.5rem 0 0;color:#7f98aa;font-size:.76rem;line-height:1.5}.fleet-summary{display:flex;gap:1.2rem}.fleet-summary span{text-align:right}.fleet-summary strong,.fleet-summary small{display:block}.fleet-summary strong{color:#e4f4fb;font-size:1rem}.fleet-summary small{margin-top:.15rem;color:#648296;text-transform:uppercase;font-size:.55rem;letter-spacing:.08em}
  .fleet-table{border:1px solid rgba(58,154,207,.24);background:rgba(4,16,28,.92);overflow:hidden}.fleet-row{display:grid;grid-template-columns:minmax(210px,1.3fr) minmax(120px,.75fr) minmax(150px,1fr) minmax(160px,1fr) 270px;gap:.75rem;align-items:center;min-height:72px;padding:.35rem .85rem;border-bottom:1px solid rgba(57,132,173,.13);font-size:.72rem}.fleet-row:last-child{border-bottom:0}.table-head{min-height:38px;color:#55c8f8;text-transform:uppercase;letter-spacing:.09em;font-size:.59rem;background:rgba(9,38,57,.45)}.fleet-row.own{box-shadow:inset 3px 0 var(--fleet-color)}.fleet-row.selected{background:linear-gradient(90deg,rgba(255,208,92,.11),rgba(4,16,28,.2));box-shadow:inset 3px 0 #ffd05c}.fleet-name,.location{display:flex;align-items:center;gap:.55rem;min-width:0;border:0;background:transparent;color:inherit;text-align:left;cursor:pointer;font:inherit}.fleet-name>span:last-child,.location{min-width:0}.fleet-name strong,.fleet-name small,.location strong,.location small,.ships strong,.ships small,.order-state strong,.order-state small{display:block}.fleet-name strong,.location strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#d9eaf2;font-weight:500}.fleet-name small,.location small,.ships small,.order-state small{margin-top:.14rem;color:#6d899b;font-size:.56rem;line-height:1.35}.fleet-icon{width:34px;height:34px;flex:none;display:grid;place-items:center;border:1px solid color-mix(in srgb,var(--fleet-color) 55%,transparent);background:color-mix(in srgb,var(--fleet-color) 8%,transparent);color:var(--fleet-color)}.ships strong{color:#ecf7fb;font-size:.8rem}.order-state strong{color:#8bdcff;font-weight:500}.order-state small{color:#d3b969}.capability{color:#718b9c!important}.actions{display:flex;justify-content:flex-end;gap:.35rem;flex-wrap:wrap}.actions button{min-height:32px;display:flex;align-items:center;gap:.25rem;padding:0 .48rem;border:1px solid rgba(65,159,207,.28);background:rgba(7,31,47,.8);color:#76ccef;font:inherit;font-size:.56rem;cursor:pointer}.actions .route{border-color:rgba(255,208,92,.35);color:#e5c461}.actions .manage{border-color:rgba(101,221,167,.32);color:#72dbaa}.actions button:hover:not(:disabled){background:rgba(12,55,79,.95)}.actions button:disabled{opacity:.35;cursor:not-allowed}
  .fleet-manager{margin-top:1rem;border:1px solid rgba(75,177,219,.3);background:linear-gradient(180deg,rgba(5,25,40,.97),rgba(3,13,23,.97));padding:1rem}.fleet-manager>header{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;border-bottom:1px solid rgba(63,143,181,.18);padding-bottom:.8rem}.fleet-manager h2{margin:0;color:#e6f5fc;font-size:1.05rem;font-weight:500}.fleet-manager header p:last-child{margin:.35rem 0 0;color:#6e8a9c;font-size:.64rem}.close-manager,.draft-banner button{border:1px solid rgba(72,159,201,.25);background:rgba(5,25,40,.8);color:#76cdef;font:inherit;font-size:.58rem;padding:.45rem .65rem;cursor:pointer}.draft-banner,.refit-banner{margin-top:.75rem;display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.65rem .75rem;border:1px solid rgba(255,208,92,.26);background:rgba(78,57,9,.15);color:#d9be6d;font-size:.65rem}.refit-banner{justify-content:flex-start;border-color:rgba(91,210,163,.3);background:rgba(8,65,47,.16);color:#6ed9aa}.refit-banner span{min-width:0}.refit-banner strong,.refit-banner small{display:block}.refit-banner small{margin-top:.18rem;color:#78a998;font-size:.58rem;line-height:1.4}
  .manager-grid{display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:.75rem;margin-top:.8rem}.manager-card{padding:.8rem;border:1px solid rgba(58,139,179,.2);background:rgba(3,15,25,.72)}.manager-card.refit-card{grid-column:1/-1}.manager-card h3{margin:0;color:#bfe8fa;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}.manager-card>p{margin:.35rem 0 .7rem;color:#657f91;font-size:.58rem;line-height:1.45}.manager-card label{display:grid;gap:.25rem;margin-top:.5rem}.manager-card label>span{color:#6e91a5;font-size:.54rem;text-transform:uppercase;letter-spacing:.06em}.manager-card input,.manager-card select{width:100%;min-height:36px;box-sizing:border-box;border:1px solid rgba(69,154,196,.28);background:#041522;color:#d9edf7;padding:0 .55rem;font:inherit;font-size:.65rem}.manager-card button{min-height:34px;margin-top:.55rem;border:1px solid rgba(66,156,201,.3);background:rgba(8,38,57,.85);color:#79ccec;padding:0 .65rem;font:inherit;font-size:.58rem;cursor:pointer}.manager-card button.primary{border-color:rgba(82,209,158,.38);color:#75ddb0}.manager-card button.danger-lite{border-color:rgba(221,112,96,.34);color:#e69b8e}.manager-card button:disabled{opacity:.35;cursor:not-allowed}.button-pair{display:flex;gap:.45rem}.refit-preview{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-top:.65rem}.refit-preview span{padding:.5rem;border:1px solid rgba(68,146,182,.18);background:rgba(4,21,33,.6)}.refit-preview small,.refit-preview strong{display:block}.refit-preview small{color:#617f91;font-size:.5rem;text-transform:uppercase}.refit-preview strong{margin-top:.15rem;color:#cae4ef;font-size:.65rem;font-weight:500}.manager-warning{padding:.65rem;border:1px solid rgba(213,166,78,.28);color:#c6a85f;background:rgba(72,50,8,.13);font-size:.61rem}
  .empty{min-height:210px;display:grid;place-items:center;align-content:center;gap:.45rem;color:#56caff}.empty p{margin:0;color:#738fa2}.other-fleets{margin-top:1rem}.other-title{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;margin-bottom:.55rem}.other-title h2{margin:0;color:#7d9bad;text-transform:uppercase;letter-spacing:.08em;font-size:.68rem}.other-title p{max-width:760px;margin:.32rem 0 0;color:#647f91;font-size:.6rem;line-height:1.45}.other-title>span{flex:none;color:#65cfff;font-size:.59rem;text-transform:uppercase;letter-spacing:.08em}.no-detections{min-height:64px;display:flex;align-items:center;gap:.65rem;padding:.65rem .8rem;border:1px solid rgba(60,137,178,.18);background:rgba(4,17,29,.72);color:#5ecbfa}.no-detections strong,.no-detections small{display:block}.no-detections strong{color:#bcd4e0;font-size:.68rem;font-weight:500}.no-detections small{margin-top:.16rem;color:#647f91;font-size:.57rem}.other-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:.5rem}.other-card{min-height:58px;display:flex;align-items:center;gap:.55rem;padding:.5rem .65rem;border:1px solid rgba(60,137,178,.2);border-left:3px solid var(--fleet-color);background:rgba(4,17,29,.85);color:#899fae;text-align:left;cursor:pointer}.other-card span:last-child{min-width:0}.other-card strong,.other-card small{display:block}.other-card strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#cadbe4;font-size:.69rem;font-weight:500}.other-card small{margin-top:.15rem;color:#667f90;font-size:.56rem}
  @media(max-width:1180px){.fleet-row{grid-template-columns:minmax(200px,1.2fr) minmax(110px,.7fr) minmax(130px,.9fr) minmax(140px,.9fr) 190px}.actions button:first-child{display:none}.manager-grid{grid-template-columns:1fr 1fr}}
  @media(max-width:760px){.fleets-view{padding:.8rem}.view-header{display:grid}.fleet-summary{justify-content:flex-start;flex-wrap:wrap}.fleet-summary span{text-align:left}.fleet-row{grid-template-columns:1fr 90px;gap:.5rem;padding:.55rem .65rem}.table-head{display:none}.fleet-row>.location,.fleet-row>.order-state{grid-column:1}.fleet-row>.ships{grid-column:2;grid-row:1;text-align:right}.fleet-row>.actions{grid-column:2;grid-row:2/4;display:grid}.actions button{justify-content:center}.actions .route,.actions .manage{display:flex!important}.manager-grid{grid-template-columns:1fr}.manager-card.refit-card{grid-column:auto}.refit-preview{grid-template-columns:1fr}.button-pair{display:grid}}
</style>
