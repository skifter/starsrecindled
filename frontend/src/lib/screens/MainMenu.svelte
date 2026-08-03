<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import Logo from '../components/Logo.svelte';

  export let hasSession = false;
  export let savedGameId = 1;
  export let savedTurn = 1;
  export let onContinue: () => void;
  export let onLogin: () => void;
  export let onDemo: () => void;

  let showAbout = false;
</script>

<section class="menu-screen space-backdrop">
  <div class="orbit orbit-a"></div>
  <div class="orbit orbit-b"></div>
  <div class="planet planet-left"></div>
  <div class="planet planet-right"></div>
  <div class="galaxy-glow"></div>
  <div class="fleet-silhouettes" aria-hidden="true">
    <span></span><span></span><span></span><span></span>
  </div>

  <div class="menu-layout">
    <header class="menu-header">
      <Logo />
    </header>

    <div class="menu-content">
      <nav class="main-menu panel-cut" aria-label="Main menu">
        <button class="menu-choice primary" disabled={!hasSession} onclick={onContinue}>
          <Icon name="play" size={25} /><span>Continue</span>
        </button>
        <button class="menu-choice" onclick={onDemo}>
          <Icon name="galaxy" size={25} /><span>New demo game</span>
        </button>
        <button class="menu-choice" onclick={onLogin}>
          <Icon name="load" size={25} /><span>Load / access game</span>
        </button>
        <button class="menu-choice" onclick={onLogin}>
          <Icon name="diplomacy" size={25} /><span>Multiplayer</span>
        </button>
        <button class="menu-choice" onclick={() => (showAbout = !showAbout)}>
          <Icon name="settings" size={25} /><span>Interface notes</span>
        </button>
      </nav>

      <aside class="latest-save panel-cut">
        <p class="eyebrow">Latest session</p>
        {#if hasSession}
          <div class="save-row">
            <div class="save-emblem"><Icon name="shield" size={34} /></div>
            <div>
              <strong>Nova Dominion</strong>
              <span>Game {savedGameId}</span>
              <span>Turn {savedTurn}</span>
            </div>
          </div>
          <p class="save-state"><span></span> Player access stored locally</p>
        {:else}
          <p class="empty-save">No stored player session. Use game access to enter a token or open the demonstration universe.</p>
        {/if}
      </aside>
    </div>

    {#if showAbout}
      <div class="about-card panel-cut">
        <button class="icon-button close" aria-label="Close" onclick={() => (showAbout = false)}><Icon name="close" /></button>
        <p class="eyebrow">Player client prototype</p>
        <h2>Original, responsive interface</h2>
        <p>The interface uses custom CSS, SVG icons and generated demo data. No third-party game artwork or external UI library is included.</p>
      </div>
    {/if}
  </div>

  <footer class="menu-footer">
    <span class="sync"><i></i> Stardatabase ready</span>
    <span>Player GUI prototype · v0.2.0</span>
  </footer>
</section>

<style>
  .menu-screen { min-height: 100svh; position: relative; overflow: hidden; display: grid; place-items: center; padding: 2.2rem; }
  .menu-layout { width: min(1160px, 100%); position: relative; z-index: 4; }
  .menu-header { margin-bottom: 2rem; }
  .menu-content { display: grid; grid-template-columns: minmax(310px, 470px) minmax(250px, 350px); gap: 1.5rem; align-items: center; }
  .main-menu { padding: 1rem; display: grid; gap: .55rem; background: linear-gradient(145deg, rgba(7,20,35,.93), rgba(2,9,18,.86)); }
  .menu-choice { width: 100%; min-height: 64px; display: flex; align-items: center; gap: 1.2rem; padding: 0 1.25rem; border: 1px solid rgba(92,184,237,.28); background: rgba(3,14,26,.72); color: #c6d9e7; font: inherit; text-transform: uppercase; letter-spacing: .15em; cursor: pointer; transition: .18s ease; }
  .menu-choice:hover:not(:disabled), .menu-choice:focus-visible { border-color: rgba(78,196,255,.85); background: rgba(9,38,63,.9); color: #f2fbff; transform: translateX(4px); box-shadow: inset 0 0 18px rgba(31,157,231,.1), 0 0 16px rgba(31,157,231,.09); }
  .menu-choice.primary { border-color: #3cc4ff; color: #e8f9ff; box-shadow: inset 0 0 25px rgba(21,141,225,.18), 0 0 18px rgba(30,174,255,.25); }
  .menu-choice:disabled { opacity: .36; cursor: not-allowed; }
  .latest-save { padding: 1.45rem; min-height: 180px; background: rgba(4,16,28,.86); }
  .eyebrow { margin: 0 0 .8rem; color: #53c9ff; text-transform: uppercase; letter-spacing: .16em; font-size: .72rem; }
  .save-row { display: flex; gap: 1rem; align-items: center; padding: 1rem 0; border-top: 1px solid rgba(91,178,227,.18); border-bottom: 1px solid rgba(91,178,227,.18); }
  .save-emblem { width: 58px; height: 58px; display: grid; place-items: center; border: 1px solid rgba(95,201,255,.4); color: #8bdcff; clip-path: polygon(20% 0,80% 0,100% 20%,100% 80%,80% 100%,20% 100%,0 80%,0 20%); }
  .save-row strong, .save-row span { display: block; }
  .save-row strong { color: #f4fbff; margin-bottom: .35rem; }
  .save-row span { font-size: .84rem; color: #829bb0; margin-top: .2rem; }
  .save-state { color: #77d6aa; font-size: .78rem; margin: .9rem 0 0; }
  .save-state span { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #48e49c; box-shadow: 0 0 10px #48e49c; margin-right: .4rem; }
  .empty-save { color: #8da2b5; line-height: 1.65; }
  .about-card { position: absolute; right: 0; bottom: -10rem; width: min(450px, 90vw); padding: 1.5rem; background: rgba(4,16,28,.97); z-index: 8; }
  .about-card h2 { margin: 0 0 .75rem; font-size: 1.15rem; color: #effaff; }
  .about-card p:last-child { color: #93a9ba; line-height: 1.6; }
  .close { position: absolute; top: .7rem; right: .7rem; }
  .menu-footer { position: absolute; left: 1.5rem; right: 1.5rem; bottom: 1rem; display: flex; justify-content: space-between; color: #71899c; font-size: .72rem; letter-spacing: .07em; z-index: 5; }
  .sync i { display: inline-block; width: 9px; height: 9px; border: 2px solid #36c0ff; border-radius: 50%; margin-right: .5rem; box-shadow: 0 0 12px rgba(54,192,255,.7); }
  .galaxy-glow { position: absolute; width: 58vw; height: 58vw; right: -7vw; top: -14vw; border-radius: 50%; background: radial-gradient(circle at center, rgba(255,224,172,.72) 0 1%, rgba(94,158,255,.25) 4%, rgba(89,61,155,.15) 18%, transparent 52%); filter: blur(2px); transform: rotate(-20deg) scaleY(.45); }
  .planet { position: absolute; border-radius: 50%; box-shadow: inset -35px -18px 70px rgba(0,0,0,.88), inset 12px 8px 38px rgba(73,155,217,.24), 0 0 42px rgba(38,132,196,.16); }
  .planet-left { width: 330px; height: 330px; left: -170px; bottom: 8%; background: radial-gradient(circle at 35% 30%, #405467, #172638 45%, #040811 72%); }
  .planet-right { width: 430px; height: 430px; right: -170px; bottom: -170px; background: radial-gradient(circle at 32% 26%, #4d6b83, #1d3145 42%, #050a12 75%); }
  .orbit { position: absolute; border: 1px solid rgba(55,158,220,.12); border-radius: 50%; }
  .orbit-a { width: 760px; height: 420px; right: 2%; top: 14%; transform: rotate(-18deg); }
  .orbit-b { width: 520px; height: 280px; right: 14%; top: 24%; transform: rotate(20deg); }
  .fleet-silhouettes { position: absolute; right: 11%; bottom: 21%; width: 360px; transform: rotate(-8deg); }
  .fleet-silhouettes span { display: block; width: 86px; height: 12px; margin: 17px 0 0 auto; background: linear-gradient(90deg, transparent, #244a68 30%, #08121e 75%, #47c5ff); clip-path: polygon(0 45%,65% 0,100% 35%,83% 60%,100% 78%,58% 76%,10% 100%); box-shadow: 24px 0 18px rgba(50,183,255,.25); }
  .fleet-silhouettes span:nth-child(2) { width: 64px; margin-right: 90px; }
  .fleet-silhouettes span:nth-child(3) { width: 110px; margin-right: 15px; }
  .fleet-silhouettes span:nth-child(4) { width: 54px; margin-right: 160px; }
  @media (max-width: 850px) {
    .menu-screen { padding: 1rem; align-items: start; overflow-y: auto; }
    .menu-layout { margin: 2rem 0 5rem; }
    .menu-header { display: grid; place-items: center; }
    .menu-content { grid-template-columns: 1fr; max-width: 520px; margin: 0 auto; }
    .latest-save { min-height: auto; }
    .about-card { position: fixed; right: 1rem; left: 1rem; bottom: 4rem; width: auto; }
    .menu-footer { position: fixed; background: rgba(2,8,15,.8); padding: .6rem; left: 0; right: 0; bottom: 0; }
    .fleet-silhouettes { display: none; }
  }
  @media (max-width: 520px) {
    .menu-choice { min-height: 56px; font-size: .78rem; letter-spacing: .1em; }
    .menu-footer span:first-child { display: none; }
    .menu-footer { justify-content: center; }
  }
</style>
