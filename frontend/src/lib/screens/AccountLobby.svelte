<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import Logo from '../components/Logo.svelte';
  import type { AccountGameAccess, AccountProfileResult, JoinGameInput } from '../types';

  export let profile: AccountProfileResult;
  export let busy = false;
  export let message = '';
  export let onPlay: (game: AccountGameAccess) => void;
  export let onJoin: (input: JoinGameInput) => void;
  export let onRotateToken: () => void;
  export let onLogout: () => void;
  export let onDemo: () => void;

  let gameId = 1;
  let invitationCode = '';
  let showInvitation = false;

  function join(event: SubmitEvent): void {
    event.preventDefault();
    onJoin({ gameId, invitationCode: invitationCode.trim() });
  }
</script>

<section class="lobby-screen space-backdrop">
  <div class="lobby-shell">
    <header class="lobby-header">
      <Logo compact={true} subtitle={false} />
      <div class="identity">
        <span>{profile.account.displayName}</span>
        <small>{profile.account.email}</small>
      </div>
      <button class="ghost" type="button" onclick={onLogout}><Icon name="power" size={18} /> Log out</button>
    </header>

    <div class="lobby-grid">
      <main class="games-panel panel-cut">
        <div class="section-heading">
          <div>
            <p class="eyebrow">Player account</p>
            <h1>Your games</h1>
          </div>
          <span class="auth-mode"><Icon name={profile.authMode === 'web' ? 'shield' : 'key'} size={18} /> {profile.authMode === 'web' ? 'Web login' : 'Client token'}</span>
        </div>

        {#if message || profile.notice}
          <p class="notice">{message || profile.notice}</p>
        {/if}

        {#if profile.games.length > 0}
          <div class="game-list">
            {#each profile.games as game}
              <article class="game-card">
                <div class="game-icon"><Icon name="galaxy" size={35} /></div>
                <div class="game-data">
                  <h2>{game.label}</h2>
                  <p>Game {game.gameId} · {game.playerLabel}</p>
                  <span>Turn {game.turnNumber}</span>
                </div>
                <button type="button" disabled={busy} onclick={() => onPlay(game)}><Icon name="play" size={18} /> Play</button>
              </article>
            {/each}
          </div>
        {:else}
          <div class="empty-state">
            <Icon name="galaxy" size={58} />
            <h2>No games linked yet</h2>
            <p>Use a game ID and the invitation code supplied by the game host. The invitation is used once to link this account to a player seat.</p>
          </div>
        {/if}

        <button class="demo" type="button" onclick={onDemo}><Icon name="galaxy" size={18} /> Open demonstration universe</button>
      </main>

      <aside class="account-panel panel-cut">
        {#if profile.authMode === 'web'}
          <form onsubmit={join}>
            <p class="eyebrow">Join a game</p>
            <h2>Accept invitation</h2>
            <p class="helper">The game ID identifies the game. The invitation code assigns an available player seat to your account.</p>

            <label class="field">
              <span>Game ID</span>
              <div><Icon name="shield" size={18} /><input type="number" min="1" bind:value={gameId} required /></div>
            </label>

            <label class="field">
              <span>Invitation code</span>
              <div><Icon name="key" size={18} /><input type={showInvitation ? 'text' : 'password'} minlength="16" bind:value={invitationCode} required autocomplete="off" /><button type="button" onclick={() => (showInvitation = !showInvitation)}>{showInvitation ? 'Hide' : 'Show'}</button></div>
            </label>

            <button class="primary" type="submit" disabled={busy || invitationCode.trim().length < 16}><Icon name="plus" size={18} /> Join game</button>
          </form>

          <div class="token-box">
            <p class="eyebrow">Other clients</p>
            <h2>Personal client token</h2>
            <p>Your token belongs to this account and works for all linked games.</p>
            <dl>
              <div><dt>Last four</dt><dd>{profile.account.clientTokenLastFour ? `••••${profile.account.clientTokenLastFour}` : 'Not issued'}</dd></div>
              <div><dt>Delivery</dt><dd>Email</dd></div>
            </dl>
            <button class="secondary" type="button" disabled={busy} onclick={onRotateToken}><Icon name="key" size={18} /> Email a new token</button>
          </div>
        {:else}
          <div class="direct-info">
            <p class="eyebrow">Direct client session</p>
            <h2>Authenticated with user token</h2>
            <p>This mode can play games already linked to the account. Joining games and rotating the token require email/password login.</p>
            <dl>
              <div><dt>Account</dt><dd>#{profile.account.id}</dd></div>
              <div><dt>Token</dt><dd>••••{profile.account.clientTokenLastFour}</dd></div>
            </dl>
          </div>
        {/if}
      </aside>
    </div>
  </div>
</section>

<style>
  .lobby-screen { min-height: 100svh; padding: 1.5rem; }
  .lobby-shell { width: min(1180px, 100%); margin: 0 auto; position: relative; z-index: 2; }
  .lobby-header { min-height: 74px; display: grid; grid-template-columns: 1fr auto auto; gap: 1.2rem; align-items: center; border-bottom: 1px solid rgba(65,177,235,.2); margin-bottom: 1.4rem; }
  .identity { text-align: right; }
  .identity span, .identity small { display: block; }
  .identity span { color: #eefaff; }
  .identity small { color: #7793a7; margin-top: .2rem; }
  button { font: inherit; cursor: pointer; }
  button:disabled { opacity: .5; cursor: wait; }
  .ghost { display: flex; align-items: center; gap: .45rem; border: 1px solid rgba(81,184,236,.3); background: rgba(3,16,28,.72); color: #8ed7fb; min-height: 42px; padding: 0 .9rem; }
  .lobby-grid { display: grid; grid-template-columns: minmax(0, 1fr) 350px; gap: 1.4rem; }
  .games-panel, .account-panel { background: rgba(3,14,25,.92); padding: 1.4rem; }
  .section-heading { display: flex; justify-content: space-between; gap: 1rem; align-items: start; border-bottom: 1px solid rgba(65,177,235,.17); padding-bottom: 1rem; }
  .eyebrow { margin: 0 0 .4rem; color: #53c9ff; text-transform: uppercase; letter-spacing: .15em; font-size: .68rem; }
  h1, h2 { color: #eefaff; margin: 0; }
  h1 { font-size: 1.7rem; }
  h2 { font-size: 1.05rem; }
  .auth-mode { display: flex; align-items: center; gap: .4rem; color: #6cdca8; font-size: .78rem; }
  .notice { border: 1px solid rgba(64,190,255,.32); background: rgba(12,61,88,.35); color: #a8e5ff; padding: .8rem; }
  .game-list { display: grid; gap: .75rem; margin-top: 1rem; }
  .game-card { display: grid; grid-template-columns: 58px minmax(0, 1fr) auto; gap: 1rem; align-items: center; padding: 1rem; border: 1px solid rgba(76,177,228,.23); background: rgba(1,9,18,.72); }
  .game-icon { width: 54px; height: 54px; display: grid; place-items: center; color: #69d1ff; border: 1px solid rgba(82,195,247,.35); }
  .game-data h2 { margin-bottom: .35rem; }
  .game-data p { margin: 0 0 .25rem; color: #8ea5b6; }
  .game-data span { color: #d0aa53; font-size: .8rem; }
  .game-card button, .primary, .secondary, .demo { display: flex; align-items: center; justify-content: center; gap: .5rem; min-height: 44px; padding: 0 .9rem; text-transform: uppercase; letter-spacing: .07em; }
  .game-card button, .primary { border: 1px solid #39c5ff; background: rgba(11,58,84,.75); color: #8addff; }
  .secondary, .demo { border: 1px solid rgba(65,177,235,.38); background: rgba(4,19,32,.78); color: #80d5fb; }
  .demo { margin-top: 1rem; width: 100%; }
  .empty-state { min-height: 300px; display: grid; place-items: center; align-content: center; text-align: center; color: #5dcaff; }
  .empty-state p { max-width: 540px; color: #8ea5b6; line-height: 1.6; }
  .account-panel { display: grid; gap: 1.2rem; align-content: start; }
  .account-panel form, .token-box, .direct-info { padding-bottom: 1.2rem; border-bottom: 1px solid rgba(65,177,235,.17); }
  .helper, .token-box p, .direct-info p { color: #8ea5b6; line-height: 1.55; font-size: .84rem; }
  .field { display: grid; gap: .35rem; margin-top: .8rem; }
  .field > span { color: #65cfff; text-transform: uppercase; letter-spacing: .07em; font-size: .67rem; }
  .field > div { min-height: 48px; display: flex; align-items: center; gap: .6rem; padding: 0 .7rem; border: 1px solid rgba(70,158,211,.28); background: rgba(1,8,16,.78); color: #6abfe8; }
  input { width: 100%; border: 0; outline: 0; color: #e8f5fc; background: transparent; font: inherit; min-width: 0; }
  .field button { border: 0; background: transparent; color: #64cfff; }
  .primary, .secondary { width: 100%; margin-top: .85rem; }
  dl { font-size: .78rem; margin: .8rem 0; }
  dl div { display: flex; justify-content: space-between; padding: .45rem 0; border-bottom: 1px solid rgba(80,149,190,.1); }
  dt { color: #7892a5; } dd { margin: 0; color: #d6e5ee; }
  @media (max-width: 900px) {
    .lobby-grid { grid-template-columns: 1fr; }
    .account-panel { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 650px) {
    .lobby-screen { padding: .75rem; }
    .lobby-header { grid-template-columns: 1fr auto; }
    .identity { display: none; }
    .account-panel { grid-template-columns: 1fr; }
    .game-card { grid-template-columns: 46px 1fr; }
    .game-card button { grid-column: 1 / -1; width: 100%; }
  }
</style>
