<script lang="ts">
  import Icon from '../components/Icon.svelte';
  import type { CreateAiGameInput } from '../types';

  export let busy = false;
  export let onCreate: (input: CreateAiGameInput) => boolean | Promise<boolean>;

  let open = false;
  let name = 'AI test universe';
  let aiPlayers = 3;

  async function createGame(): Promise<void> {
    const trimmed = name.trim();
    if (!trimmed || busy) return;
    const created = await onCreate({ name: trimmed, aiPlayers, aiLevel: 'standard' });
    if (created) open = false;
  }
</script>

<button class="open-create" type="button" onclick={() => (open = true)} disabled={busy}>
  <Icon name="plus" size={18} />
  Create AI game
</button>

{#if open}
  <div class="backdrop" role="presentation" onclick={() => !busy && (open = false)}>
    <div class="dialog panel-cut" role="dialog" aria-modal="true" aria-labelledby="create-ai-game-title" onclick={(event) => event.stopPropagation()} onkeydown={(event) => event.key === 'Escape' && !busy && (open = false)} tabindex="-1">
      <header>
        <div>
          <p>Test universe</p>
          <h2 id="create-ai-game-title">Create game with AI players</h2>
        </div>
        <button class="close" type="button" aria-label="Close" disabled={busy} onclick={() => (open = false)}><Icon name="close" size={18} /></button>
      </header>

      <p class="intro">Standard AI is currently test-oriented. It is a real player seat and automatically submits valid turns so a multiplayer game can advance without extra logins.</p>

      <label>
        <span>Game name</span>
        <input bind:value={name} maxlength="190" autocomplete="off" disabled={busy} />
      </label>

      <div class="fields">
        <label>
          <span>AI players</span>
          <select bind:value={aiPlayers} disabled={busy}>
            <option value={1}>1</option>
            <option value={2}>2</option>
            <option value={3}>3</option>
          </select>
        </label>
        <label>
          <span>AI level</span>
          <select disabled>
            <option>Standard</option>
          </select>
        </label>
      </div>

      <div class="summary">
        <Icon name="user" size={18} />
        <span><strong>1 human + {aiPlayers} AI</strong><small>{aiPlayers + 1} player seats total</small></span>
      </div>

      <footer>
        <button type="button" class="secondary" disabled={busy} onclick={() => (open = false)}>Cancel</button>
        <button type="button" class="primary" disabled={busy || !name.trim()} onclick={createGame}>{busy ? 'Creating…' : 'Create game'}</button>
      </footer>
    </div>
  </div>
{/if}

<style>
  .open-create { position:fixed; right:1.25rem; bottom:1.25rem; z-index:35; min-height:44px; display:flex; align-items:center; gap:.5rem; padding:.7rem 1rem; border:1px solid rgba(75,197,255,.55); background:rgba(4,28,44,.96); color:#bdeaff; font:inherit; font-size:.78rem; letter-spacing:.04em; cursor:pointer; box-shadow:0 10px 30px rgba(0,0,0,.32); }
  .open-create:hover:not(:disabled) { border-color:#62d2ff; background:rgba(8,48,72,.98); }
  button:disabled { opacity:.48; cursor:not-allowed; }
  .backdrop { position:fixed; inset:0; z-index:120; display:grid; place-items:center; padding:1rem; background:rgba(0,5,10,.78); backdrop-filter:blur(3px); }
  .dialog { width:min(520px,100%); padding:1.15rem; border:1px solid rgba(73,184,237,.35); background:linear-gradient(180deg,rgba(7,25,39,.99),rgba(2,11,20,.99)); box-shadow:0 24px 80px rgba(0,0,0,.55); }
  header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; }
  header p { margin:0 0 .2rem; color:#4dcaff; font-size:.63rem; text-transform:uppercase; letter-spacing:.14em; }
  h2 { margin:0; color:#edfaff; font-size:1.16rem; font-weight:500; }
  .close { width:36px; height:36px; display:grid; place-items:center; border:1px solid rgba(88,171,213,.25); background:rgba(2,13,23,.8); color:#8fdfff; cursor:pointer; }
  .intro { margin:.9rem 0 1rem; color:#8199a9; font-size:.75rem; line-height:1.55; }
  label { display:grid; gap:.35rem; color:#7291a4; font-size:.68rem; text-transform:uppercase; letter-spacing:.08em; }
  input, select { width:100%; min-height:43px; box-sizing:border-box; padding:0 .7rem; border:1px solid rgba(70,158,211,.3); outline:0; background:rgba(1,8,16,.82); color:#e8f5fc; font:inherit; }
  input:focus, select:focus { border-color:#4ac8ff; }
  select option { background:#061321; color:#e8f5fc; }
  .fields { display:grid; grid-template-columns:1fr 1fr; gap:.7rem; margin-top:.75rem; }
  .summary { display:flex; align-items:center; gap:.65rem; margin-top:.9rem; padding:.7rem .8rem; border:1px solid rgba(68,160,208,.2); background:rgba(5,23,35,.55); color:#5dcfff; }
  .summary span { display:grid; gap:.12rem; }
  .summary strong { color:#cfeefa; font-size:.76rem; font-weight:500; }
  .summary small { color:#708b9d; font-size:.65rem; }
  footer { display:flex; justify-content:flex-end; gap:.6rem; margin-top:1rem; }
  footer button { min-height:40px; padding:0 .9rem; border:1px solid rgba(73,174,224,.3); font:inherit; font-size:.72rem; cursor:pointer; }
  .secondary { background:rgba(2,12,21,.7); color:#89a7b9; }
  .primary { border-color:rgba(71,201,255,.6); background:rgba(15,83,117,.7); color:#dcf7ff; }
  @media(max-width:620px) { .open-create { right:.75rem; bottom:5.3rem; }.fields { grid-template-columns:1fr; } }
</style>
