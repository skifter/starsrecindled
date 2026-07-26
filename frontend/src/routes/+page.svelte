<script lang="ts">
  import { onMount } from 'svelte';

  let apiBase = '';
  let gameId = 1;
  let playerId = 1;
  let turnNumber = 1;
  let token = '';
  let ordersText = JSON.stringify({ fleets: [], production: [] }, null, 2);
  let status: Record<string, unknown> | null = null;
  let message = '';
  let busy = false;

  onMount(() => {
    const params = new URLSearchParams(window.location.search);
    apiBase = localStorage.getItem('stars.apiBase') ?? apiBase;
    gameId = Number(params.get('game') ?? localStorage.getItem('stars.gameId') ?? gameId);
    playerId = Number(params.get('player') ?? localStorage.getItem('stars.playerId') ?? playerId);
    turnNumber = Number(params.get('turn') ?? localStorage.getItem('stars.turnNumber') ?? turnNumber);
    token = localStorage.getItem('stars.token') ?? '';
  });

  function remember(): void {
    localStorage.setItem('stars.apiBase', apiBase);
    localStorage.setItem('stars.gameId', String(gameId));
    localStorage.setItem('stars.playerId', String(playerId));
    localStorage.setItem('stars.turnNumber', String(turnNumber));
    localStorage.setItem('stars.token', token);
  }

  async function request(path = '', method = 'GET', body?: unknown): Promise<Record<string, unknown>> {
    remember();
    busy = true;
    message = '';

    try {
      const response = await fetch(
        `${apiBase}/stars/api/games/${gameId}/turns/${turnNumber}${path}`,
        {
          method,
          headers: {
            Authorization: `Bearer ${token}`,
            'X-Stars-Player-Id': String(playerId),
            'Content-Type': 'application/json'
          },
          body: body === undefined ? undefined : JSON.stringify(body)
        }
      );
      const data = await response.json();
      if (!response.ok) {
        throw new Error(String(data.error ?? `HTTP ${response.status}`));
      }
      return data;
    } finally {
      busy = false;
    }
  }

  async function loadStatus(): Promise<void> {
    try {
      status = await request();
      const ownOrders = (status.you as { orders?: unknown } | undefined)?.orders;
      if (ownOrders !== undefined) {
        ordersText = JSON.stringify(ownOrders, null, 2);
      }
    } catch (error) {
      message = error instanceof Error ? error.message : String(error);
    }
  }

  async function send(path: string, method: string): Promise<void> {
    try {
      const orders = JSON.parse(ordersText) as Record<string, unknown>;
      const result = await request(path, method, { orders });
      message = JSON.stringify(result);
      await loadStatus();
    } catch (error) {
      message = error instanceof Error ? error.message : String(error);
    }
  }

  async function reopen(): Promise<void> {
    try {
      const result = await request('/reopen', 'POST');
      message = JSON.stringify(result);
      await loadStatus();
    } catch (error) {
      message = error instanceof Error ? error.message : String(error);
    }
  }
</script>

<svelte:head>
  <title>Stars Turn MVP</title>
</svelte:head>

<main>
  <h1>Stars Turn MVP</h1>

  <section class="grid">
    <label>API-base <input bind:value={apiBase} /></label>
    <label>Game ID <input type="number" min="1" bind:value={gameId} /></label>
    <label>Player ID <input type="number" min="1" bind:value={playerId} /></label>
    <label>Runde <input type="number" min="1" bind:value={turnNumber} /></label>
    <label class="wide">Token <input type="password" bind:value={token} /></label>
  </section>

  <div class="actions">
    <button disabled={busy} onclick={loadStatus}>Hent status</button>
    <button disabled={busy} onclick={() => send('/draft', 'PUT')}>Gem kladde</button>
    <button disabled={busy} onclick={() => send('/submit', 'POST')}>Aflever</button>
    <button disabled={busy} onclick={reopen}>Genåbn</button>
  </div>

  <label>
    Ordrer som JSON
    <textarea bind:value={ordersText} rows="16"></textarea>
  </label>

  {#if message}<p class="message">{message}</p>{/if}
  {#if status}<pre>{JSON.stringify(status, null, 2)}</pre>{/if}
</main>

<style>
  :global(body) { margin: 0; font-family: system-ui, sans-serif; background: #111827; color: #e5e7eb; }
  main { max-width: 1000px; margin: 0 auto; padding: 2rem; }
  .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
  .wide { grid-column: 1 / -1; }
  label { display: grid; gap: .35rem; margin-bottom: 1rem; }
  input, textarea { padding: .65rem; border: 1px solid #4b5563; border-radius: .35rem; background: #1f2937; color: inherit; }
  textarea { width: 100%; box-sizing: border-box; font-family: ui-monospace, monospace; }
  .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin: 1rem 0; }
  button { padding: .65rem 1rem; cursor: pointer; }
  pre { padding: 1rem; overflow: auto; background: #030712; border-radius: .35rem; }
  .message { padding: .75rem; background: #374151; }
  @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } .wide { grid-column: auto; } }
</style>
