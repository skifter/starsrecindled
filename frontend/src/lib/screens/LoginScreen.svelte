<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import Logo from '../components/Logo.svelte';
  import type { AccountLoginInput, AccountRegistrationInput } from '../types';

  export let busy = false;
  export let error = '';
  export let notice = '';
  export let onAccountLogin: (credentials: AccountLoginInput) => void;
  export let onAccountRegister: (registration: AccountRegistrationInput) => void;
  export let onDirectLogin: (apiBase: string, clientToken: string) => void;
  export let onDemo: () => void;

  let activeTab: 'player' | 'direct' = 'player';
  let accountMode: 'login' | 'register' = 'login';
  let displayName = '';
  let email = '';
  let password = '';
  let passwordRepeat = '';
  let apiBase = '';
  let clientToken = '';
  let showPassword = false;
  let showToken = false;
  let localError = '';

  function submit(event: SubmitEvent): void {
    event.preventDefault();
    localError = '';

    if (activeTab === 'direct') {
      if (clientToken.trim().length < 20) {
        localError = 'Enter the client token that was sent to the account email address.';
        return;
      }
      onDirectLogin(apiBase.trim(), clientToken.trim());
      return;
    }

    if (accountMode === 'login') {
      onAccountLogin({ email: email.trim(), password });
      return;
    }

    if (password !== passwordRepeat) {
      localError = 'The two passwords do not match.';
      return;
    }

    onAccountRegister({ displayName: displayName.trim(), email: email.trim(), password });
  }
</script>

<section class="login-screen space-backdrop">
  <div class="login-map" aria-hidden="true">
    <svg viewBox="0 0 1000 600" preserveAspectRatio="xMidYMid slice">
      <g opacity=".28" stroke="#35bfff" fill="none">
        <circle cx="260" cy="290" r="170" /><circle cx="260" cy="290" r="120" /><circle cx="260" cy="290" r="70" />
        <path d="M70 210 170 125 285 170 370 95 480 180 610 130 740 205 900 135" />
        <path d="M90 430 220 350 350 405 455 320 590 390 700 300 875 385" />
      </g>
      <g fill="#58ceff">
        <circle cx="170" cy="125" r="3" /><circle cx="285" cy="170" r="5" /><circle cx="260" cy="290" r="7" /><circle cx="455" cy="320" r="4" /><circle cx="740" cy="205" r="5" /><circle cx="875" cy="385" r="3" />
      </g>
    </svg>
  </div>

  <div class="login-shell">
    <header><Logo /></header>

    <div class="access-layout">
      <aside class="status-card panel-cut">
        <p class="eyebrow">Player network</p>
        <div class="mini-galaxy"></div>
        <h2>Account access</h2>
        <p class="year">One identity · every game</p>
        <div class="faction"><Icon name="shield" size={31} /><span><strong>Secure player profile</strong><small>Email/password or client token</small></span></div>
        <dl>
          <div><dt>Web login</dt><dd>Email + password</dd></div>
          <div><dt>Other clients</dt><dd>User token</dd></div>
          <div><dt>Game access</dt><dd>Game invitation</dd></div>
        </dl>
        <p class="secure"><Icon name="shield" size={18} /> Token belongs to the user, not a game</p>
      </aside>

      <form class="login-card panel-cut" onsubmit={submit}>
        <div class="tabs" role="tablist" aria-label="Login method">
          <button type="button" class:active={activeTab === 'player'} onclick={() => { activeTab = 'player'; localError = ''; }}><Icon name="user" /> Player login</button>
          <button type="button" class:active={activeTab === 'direct'} onclick={() => { activeTab = 'direct'; localError = ''; }}><Icon name="key" /> Client token</button>
        </div>

        {#if activeTab === 'player'}
          <div class="form-body">
            <div class="account-mode" role="tablist" aria-label="Account action">
              <button type="button" class:active={accountMode === 'login'} onclick={() => { accountMode = 'login'; localError = ''; }}>Log in</button>
              <button type="button" class:active={accountMode === 'register'} onclick={() => { accountMode = 'register'; localError = ''; }}>Create account</button>
            </div>

            {#if notice}<p class="invitation-notice"><Icon name="report" size={18} /> {notice}</p>{/if}

            <p class="helper">
              {accountMode === 'login'
                ? 'Log in to use the web player as a normal Stars Rekindled client.'
                : 'Create your account first. A personal client token will be generated and sent by email.'}
            </p>

            <div class="connection-grid account-grid">
              {#if accountMode === 'register'}
                <label class="field wide">
                  <span>Player name</span>
                  <div><Icon name="user" size={19} /><input bind:value={displayName} minlength="2" maxlength="120" autocomplete="name" required placeholder="Your display name" /></div>
                </label>
              {/if}

              <label class="field wide">
                <span>Email address</span>
                <div><Icon name="report" size={19} /><input type="email" bind:value={email} maxlength="180" autocomplete="email" required placeholder="player@example.net" /></div>
              </label>

              <label class="field" class:wide={accountMode === 'login'}>
                <span>Password</span>
                <div><Icon name="key" size={19} /><input type={showPassword ? 'text' : 'password'} bind:value={password} minlength={accountMode === 'register' ? 12 : 1} autocomplete={accountMode === 'register' ? 'new-password' : 'current-password'} required /><button type="button" class="show-token" onclick={() => (showPassword = !showPassword)}>{showPassword ? 'Hide' : 'Show'}</button></div>
              </label>

              {#if accountMode === 'register'}
                <label class="field">
                  <span>Repeat password</span>
                  <div><Icon name="key" size={19} /><input type={showPassword ? 'text' : 'password'} bind:value={passwordRepeat} minlength="12" autocomplete="new-password" required /></div>
                </label>
              {/if}
            </div>

            {#if localError || error}<p class="error-message">{localError || error}</p>{/if}

            <button class="enter-button" disabled={busy} type="submit"><Icon name="play" /> {busy ? 'Connecting…' : accountMode === 'login' ? 'Log in' : 'Create account'}</button>
            <button class="secondary wide-button" type="button" onclick={onDemo}>Open demonstration universe</button>
          </div>
        {:else}
          <div class="form-body">
            <p class="helper">Use the personal client token sent to your email. The same token gives access to every game linked to your account.</p>
            <div class="connection-grid direct-grid">
              <label class="field wide">
                <span>API base</span>
                <div><Icon name="galaxy" size={19} /><input bind:value={apiBase} placeholder="Blank for this server" autocomplete="url" /></div>
              </label>
              <label class="field wide">
                <span>Personal client token</span>
                <div><Icon name="key" size={19} /><input type={showToken ? 'text' : 'password'} bind:value={clientToken} placeholder="srk_…" autocomplete="off" required /><button type="button" class="show-token" onclick={() => (showToken = !showToken)}>{showToken ? 'Hide' : 'Show'}</button></div>
              </label>
            </div>

            {#if localError || error}<p class="error-message">{localError || error}</p>{/if}

            <button class="enter-button" disabled={busy} type="submit"><Icon name="play" /> {busy ? 'Connecting…' : 'Open account with token'}</button>
            <button class="secondary wide-button" type="button" onclick={onDemo}>Open demonstration universe</button>
          </div>
        {/if}
      </form>
    </div>

    <footer><span>Web player uses account session</span><span>External clients use personal token</span><span>Game invitations are separate</span></footer>
  </div>
</section>

<style>
  .login-screen { min-height: 100svh; position: relative; overflow: hidden; padding: 1.5rem; display: grid; place-items: center; }
  .login-map { position: absolute; inset: 0; opacity: .8; }
  .login-map svg { width: 100%; height: 100%; }
  .login-shell { position: relative; z-index: 3; width: min(1200px, 100%); }
  header { display: grid; place-items: center; margin-bottom: 1.35rem; }
  .access-layout { display: grid; grid-template-columns: 280px minmax(470px, 650px); justify-content: center; gap: 1.4rem; align-items: stretch; }
  .status-card, .login-card { background: rgba(3,14,25,.93); }
  .status-card { padding: 1.15rem; }
  .eyebrow { color: #55cbff; text-transform: uppercase; font-size: .7rem; letter-spacing: .15em; margin: 0 0 .9rem; }
  .mini-galaxy { height: 120px; border: 1px solid rgba(74,184,242,.22); overflow: hidden; background: radial-gradient(ellipse at center, rgba(53,176,255,.45), transparent 18%), radial-gradient(ellipse at 45% 53%, rgba(113,76,200,.38), transparent 35%), #020811; position: relative; }
  .mini-galaxy::before, .mini-galaxy::after { content: ''; position: absolute; inset: 18px; border: 1px solid rgba(71,191,255,.32); border-radius: 50%; transform: rotate(-16deg) scaleY(.45); }
  .mini-galaxy::after { inset: 35px; border-color: rgba(205,169,79,.4); }
  .status-card h2 { margin: 1rem 0 .2rem; color: #edf9ff; font-size: 1.35rem; }
  .year { margin: 0; color: #60cfff; }
  .faction { display: flex; gap: .75rem; align-items: center; margin: 1rem 0; padding: .8rem 0; border-block: 1px solid rgba(70,158,211,.15); color: #8edcff; }
  .faction strong, .faction small { display: block; }
  .faction strong { color: #eefaff; font-size: .9rem; }
  .faction small { color: #cba75f; font-size: .7rem; margin-top: .25rem; }
  dl { margin: 0; font-size: .77rem; }
  dl div { display: flex; justify-content: space-between; gap: .8rem; padding: .42rem 0; border-bottom: 1px solid rgba(80,149,190,.1); }
  dt { color: #7892a5; } dd { margin: 0; color: #c6d7e2; text-align: right; }
  .secure { display: flex; align-items: center; gap: .5rem; color: #66d9a2; font-size: .73rem; margin: 1rem 0 0; line-height: 1.4; }
  .login-card { overflow: hidden; display: flex; flex-direction: column; }
  .tabs { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid rgba(68,168,224,.2); }
  .tabs button { min-height: 58px; border: 0; border-right: 1px solid rgba(68,168,224,.15); background: rgba(6,20,34,.7); color: #819aab; display: flex; align-items: center; justify-content: center; gap: .6rem; text-transform: uppercase; letter-spacing: .08em; cursor: pointer; }
  .tabs button.active { color: #72d7ff; background: linear-gradient(180deg, rgba(11,50,76,.75), rgba(3,18,31,.9)); box-shadow: inset 0 -2px #28bdff, inset 0 -10px 25px rgba(27,165,231,.08); }
  .form-body { padding: 1.35rem 1.5rem; flex: 1; }
  .account-mode { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-bottom: 1rem; }
  .account-mode button { min-height: 38px; border: 1px solid rgba(57,188,248,.28); background: rgba(4,19,32,.7); color: #7897aa; cursor: pointer; text-transform: uppercase; letter-spacing: .08em; }
  .account-mode button.active { color: #77d7ff; border-color: rgba(57,188,248,.65); background: rgba(12,48,70,.72); }
  .helper { color: #8ea5b6; text-align: center; margin: 0 0 1.2rem; font-size: .86rem; line-height: 1.5; }
  .connection-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .85rem; }
  .field { display: grid; gap: .35rem; }
  .field.wide { grid-column: 1 / -1; }
  .field > span { color: #65cfff; text-transform: uppercase; letter-spacing: .07em; font-size: .67rem; padding-left: .2rem; }
  .field > div { height: 52px; display: flex; align-items: center; gap: .65rem; padding: 0 .8rem; border: 1px solid rgba(70,158,211,.28); background: rgba(1,8,16,.78); color: #6abfe8; }
  .field > div:focus-within { border-color: #39c5ff; box-shadow: 0 0 16px rgba(35,183,247,.12); }
  input { width: 100%; border: 0; outline: 0; color: #e8f5fc; background: transparent; font: inherit; min-width: 0; }
  input::placeholder { color: #4e687a; }
  .show-token { border: 0; background: transparent; color: #64cfff; cursor: pointer; font-size: .72rem; }
  .enter-button { width: 100%; min-height: 58px; margin-top: 1rem; border: 1px solid #ffc43e; background: linear-gradient(180deg, rgba(113,76,5,.72), rgba(55,34,1,.88)); color: #ffd96d; text-transform: uppercase; letter-spacing: .14em; font: inherit; display: flex; align-items: center; justify-content: center; gap: .8rem; cursor: pointer; box-shadow: inset 0 0 22px rgba(255,183,24,.12), 0 0 18px rgba(255,174,0,.16); }
  .enter-button:hover:not(:disabled) { background: linear-gradient(180deg, rgba(145,97,4,.86), rgba(74,45,1,.94)); }
  .enter-button:disabled { opacity: .55; cursor: wait; }
  .secondary { color: #74cfff; border: 1px solid rgba(57,188,248,.5); background: rgba(4,19,32,.7); font: inherit; cursor: pointer; }
  .wide-button { width: 100%; min-height: 46px; margin-top: .7rem; }
  .invitation-notice { display: flex; align-items: center; justify-content: center; gap: .5rem; color: #8fe3ff; border: 1px solid rgba(65,190,255,.34); background: rgba(8,58,84,.28); padding: .7rem; font-size: .8rem; }
  .error-message { color: #ffb4b4; border: 1px solid rgba(255,78,78,.35); background: rgba(96,12,20,.26); padding: .7rem; font-size: .8rem; }
  footer { display: flex; justify-content: center; gap: 2rem; margin-top: 1.2rem; color: #6e879a; font-size: .7rem; }
  footer span::before { content: '◇'; color: #4fc8ff; margin-right: .45rem; }
  @media (max-width: 920px) {
    .login-screen { overflow-y: auto; align-items: start; }
    .access-layout { grid-template-columns: 1fr; max-width: 660px; margin: auto; }
    .status-card { display: none; }
    footer { flex-wrap: wrap; }
  }
  @media (max-width: 600px) {
    .login-screen { padding: .75rem; }
    .tabs button { font-size: .68rem; }
    .connection-grid { grid-template-columns: 1fr; }
    .field.wide { grid-column: auto; }
  }
</style>
