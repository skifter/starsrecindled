<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import type { AccountGameAccess, AccountTurnStatus, AccountTurnStatusPlayer, GamePlayerSummary } from '../types';

  export let game: AccountGameAccess | null = null;
  export let status: AccountTurnStatus | null = null;
  export let demoMode = false;

  const demoPlayers: GamePlayerSummary[] = [
    { playerId: 1, displayName: 'Demonstration player', active: true },
    { playerId: 2, displayName: 'Crimson Reach', active: true },
    { playerId: 3, displayName: 'Violet Assembly', active: true },
    { playerId: 4, displayName: 'Amber League', active: true }
  ];

  $: players = demoMode ? demoPlayers : (game?.players ?? []);
  $: activePlayers = players.filter((player) => player.active).length;
  $: currentPlayerId = game?.playerId ?? 1;
  $: gameName = game?.label ?? (demoMode ? 'Demonstration universe' : 'Current game');
  $: turnNumber = demoMode ? (game?.turnNumber ?? 1) : (status?.turn.number ?? game?.turnNumber ?? 1);
  $: turnStatus = demoMode ? 'demo' : (status?.turn.status ?? 'loading');
  $: turnPlayers = demoMode ? [] : (status?.players ?? []);
  $: submittedCount = turnPlayers.filter((player) => player.submitted).length;

  function turnPlayer(playerId: number): AccountTurnStatusPlayer | undefined {
    return turnPlayers.find((player) => player.id === playerId);
  }

  function turnState(playerId: number): string {
    if (demoMode) return playerId === currentPlayerId ? 'Your turn' : 'Active';
    const player = turnPlayer(playerId);
    if (!player) return 'Connected';
    if (player.submitted) return 'Submitted';
    if (turnStatus !== 'open') return turnStatus.charAt(0).toUpperCase() + turnStatus.slice(1);
    return playerId === currentPlayerId ? 'Your turn' : 'Waiting';
  }
</script>

<section class="players-view">
  <header class="view-header">
    <div>
      <p class="eyebrow">Game overview</p>
      <h1>Players</h1>
      <p class="intro">Players in <strong>{gameName}</strong>. This screen is the natural bridge to diplomacy as player-to-player relations are added.</p>
    </div>
  </header>

  <div class="summary-grid">
    <article class="summary-card panel-cut">
      <Icon name="galaxy" size={24} />
      <span><small>Game</small><strong>{gameName}</strong></span>
    </article>
    <article class="summary-card panel-cut">
      <Icon name="calendar" size={24} />
      <span><small>Turn</small><strong>{turnNumber} · {turnStatus.toUpperCase()}</strong></span>
    </article>
    <article class="summary-card panel-cut">
      <Icon name="user" size={24} />
      <span><small>Players</small><strong>{activePlayers} active · {demoMode ? 'demo' : `${submittedCount}/${turnPlayers.length || players.length} submitted`}</strong></span>
    </article>
    <article class="summary-card panel-cut">
      <Icon name="shield" size={24} />
      <span><small>Your seat</small><strong>{game?.playerLabel ?? 'Demonstration player'} <em>(Player {currentPlayerId})</em></strong></span>
    </article>
  </div>

  <div class="players-table panel-cut">
    <div class="player-row table-head">
      <span>Seat</span><span>Player</span><span>Turn status</span><span>Diplomacy</span>
    </div>
    {#if players.length > 0}
      {#each players as player}
        <article class="player-row" class:you={player.playerId === currentPlayerId}>
          <span class="seat">#{player.playerId}</span>
          <span class="player-name"><Icon name="user" size={19} /><strong>{player.displayName}</strong>{#if player.playerId === currentPlayerId}<small>You</small>{/if}</span>
          <span class:inactive={!player.active} class:submitted={turnPlayer(player.playerId)?.submitted === true}>{player.active ? turnState(player.playerId) : 'Inactive'}</span>
          <span class="diplomacy-state">{player.playerId === currentPlayerId ? 'Your empire' : 'Relations coming later'}</span>
        </article>
      {/each}
    {:else}
      <div class="empty-state"><Icon name="user" size={36} /><p>No player information is available for this game yet.</p></div>
    {/if}
  </div>
</section>

<style>
  .players-view { height:100%; overflow:auto; padding:1.35rem; background:radial-gradient(circle at 42% 20%,rgba(17,83,118,.14),transparent 46%),#030912; }
  .view-header { display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; margin-bottom:1.15rem; }
  .eyebrow { margin:0 0 .3rem; color:#43c5ff; text-transform:uppercase; letter-spacing:.14em; font-size:.65rem; }
  h1 { margin:0; color:#edf9ff; font-size:1.65rem; font-weight:500; letter-spacing:.08em; }
  .intro { max-width:720px; margin:.55rem 0 0; color:#7f98aa; font-size:.76rem; line-height:1.55; }
  .intro strong { color:#cce7f5; font-weight:500; }
  .summary-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.7rem; margin-bottom:.9rem; }
  .summary-card { min-height:78px; display:flex; align-items:center; gap:.8rem; padding:.85rem 1rem; border:1px solid rgba(58,154,207,.2); background:rgba(5,18,31,.9); color:#52caff; }
  .summary-card span,.summary-card small,.summary-card strong { display:block; min-width:0; }
  .summary-card small { color:#6e899c; margin-bottom:.25rem; text-transform:uppercase; letter-spacing:.08em; font-size:.58rem; }
  .summary-card strong { overflow:hidden; text-overflow:ellipsis; color:#dcecf4; font-size:.78rem; font-weight:500; white-space:nowrap; }
  .summary-card em { color:#728fa1; font-size:.65rem; font-style:normal; font-weight:400; }
  .players-table { border:1px solid rgba(58,154,207,.2); background:rgba(4,16,28,.92); }
  .player-row { display:grid; grid-template-columns:80px minmax(220px,1.5fr) minmax(120px,.7fr) minmax(180px,1fr); gap:1rem; align-items:center; min-height:62px; padding:0 1rem; border-bottom:1px solid rgba(57,132,173,.13); color:#8ca5b5; font-size:.75rem; }
  .player-row:last-child { border-bottom:0; }
  .table-head { min-height:38px; color:#55c8f8; text-transform:uppercase; letter-spacing:.09em; font-size:.61rem; background:rgba(9,38,57,.45); }
  .player-row.you { background:linear-gradient(90deg,rgba(40,159,211,.12),rgba(4,16,28,.3)); box-shadow:inset 3px 0 #43c9ff; }
  .seat { color:#5ecbfa; font-family:ui-monospace,SFMono-Regular,Consolas,monospace; }
  .player-name { display:flex; align-items:center; gap:.55rem; color:#58caff; }
  .player-name strong { color:#dbeaf2; font-weight:500; }
  .player-name small { margin-left:.2rem; padding:.15rem .35rem; border:1px solid rgba(75,193,242,.24); color:#58caff; font-size:.55rem; text-transform:uppercase; letter-spacing:.08em; }
  .inactive { color:#667784; }
  .submitted { color:#72d6a0; }
  .diplomacy-state { color:#758fa1; }
  .empty-state { min-height:220px; display:grid; place-items:center; align-content:center; gap:.5rem; color:#58caff; text-align:center; }
  .empty-state p { color:#7b95a7; }
  @media(max-width:1050px){.summary-grid{grid-template-columns:1fr 1fr}.player-row{grid-template-columns:65px 1.3fr .7fr}.player-row>span:last-child{display:none}}
  @media(max-width:650px){.players-view{padding:.85rem}.view-header{display:grid}.summary-grid{grid-template-columns:1fr}.player-row{grid-template-columns:50px 1fr}.player-row>span:nth-child(3),.player-row>span:nth-child(4){display:none}.table-head span:nth-child(2){display:block}}
</style>