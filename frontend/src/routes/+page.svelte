<script lang="ts">
  import { onMount } from 'svelte';
  import '../app.css';
  import GameShell from '$lib/screens/GameShell.svelte';
  import LoginScreen from '$lib/screens/LoginScreen.svelte';
  import MainMenu from '$lib/screens/MainMenu.svelte';
  import type {
    AccountAuthResult,
    AccountGameAccess,
    AccountLoginInput,
    AccountProfileResult,
    AccountRegistrationInput,
    AppScreen,
    ConnectionSettings,
    PlayerOrders
  } from '$lib/types';

  const defaultConnection: ConnectionSettings = {
    apiBase: '',
    gameId: 1,
    playerId: 1,
    turnNumber: 1,
    token: ''
  };

  const defaultOrders: PlayerOrders = { fleets: [], production: [] };
  const accountSessionKey = 'stars.accountSession';
  const accountGameTokenKey = 'stars.accountGameToken';

  let screen: AppScreen = 'menu';
  let connection: ConnectionSettings = { ...defaultConnection };
  let orders: PlayerOrders = { ...defaultOrders };
  let status: Record<string, unknown> | null = null;
  let busy = false;
  let apiMessage = '';
  let loginError = '';
  let hasSession = false;
  let demoMode = false;
  let accountSessionToken = '';

  onMount(() => {
    const params = new URLSearchParams(window.location.search);
    accountSessionToken = sessionStorage.getItem(accountSessionKey) ?? localStorage.getItem(accountSessionKey) ?? '';

    connection = {
      apiBase: localStorage.getItem('stars.apiBase') ?? '',
      gameId: Number(params.get('game') ?? localStorage.getItem('stars.gameId') ?? 1),
      playerId: Number(params.get('player') ?? localStorage.getItem('stars.playerId') ?? 1),
      turnNumber: Number(params.get('turn') ?? localStorage.getItem('stars.turnNumber') ?? 1),
      token: sessionStorage.getItem(accountGameTokenKey) ?? localStorage.getItem('stars.token') ?? ''
    };

    const savedOrders = localStorage.getItem('stars.orders');
    if (savedOrders) {
      try {
        const parsed = JSON.parse(savedOrders) as PlayerOrders;
        if (Array.isArray(parsed.fleets) && Array.isArray(parsed.production)) orders = parsed;
      } catch {
        localStorage.removeItem('stars.orders');
      }
    }

    hasSession = accountSessionToken.length > 0 || connection.token.length > 0;
    if (params.get('access') === '1') screen = 'login';
    if (params.get('demo') === '1') openDemo();
  });

  function storeDirectConnection(remember: boolean): void {
    const keys = ['stars.apiBase', 'stars.gameId', 'stars.playerId', 'stars.turnNumber', 'stars.token'];
    if (!remember) {
      keys.forEach((key) => localStorage.removeItem(key));
      hasSession = accountSessionToken.length > 0;
      return;
    }
    localStorage.setItem('stars.apiBase', connection.apiBase);
    localStorage.setItem('stars.gameId', String(connection.gameId));
    localStorage.setItem('stars.playerId', String(connection.playerId));
    localStorage.setItem('stars.turnNumber', String(connection.turnNumber));
    localStorage.setItem('stars.token', connection.token);
    hasSession = connection.token.length > 0 || accountSessionToken.length > 0;
  }

  function storeAccountSession(token: string, remember: boolean): void {
    localStorage.removeItem(accountSessionKey);
    sessionStorage.removeItem(accountSessionKey);
    if (remember) localStorage.setItem(accountSessionKey, token);
    else sessionStorage.setItem(accountSessionKey, token);
    accountSessionToken = token;
    hasSession = true;
  }

  function activateAccountGame(access: AccountGameAccess): void {
    connection = {
      apiBase: '',
      gameId: access.gameId,
      playerId: access.playerId,
      turnNumber: access.turnNumber,
      token: access.token
    };

    localStorage.setItem('stars.gameId', String(access.gameId));
    localStorage.setItem('stars.playerId', String(access.playerId));
    localStorage.setItem('stars.turnNumber', String(access.turnNumber));
    localStorage.removeItem('stars.token');
    sessionStorage.setItem(accountGameTokenKey, access.token);
  }

  function updateOrders(next: PlayerOrders): void {
    orders = next;
    localStorage.setItem('stars.orders', JSON.stringify(next));
  }

  async function parseJsonResponse<T>(response: Response): Promise<T> {
    let data: T & { error?: string };
    try {
      data = await response.json() as T & { error?: string };
    } catch {
      throw new Error(`API returned HTTP ${response.status} without a JSON response.`);
    }
    if (!response.ok) throw new Error(String(data.error ?? `HTTP ${response.status}`));
    return data;
  }

  async function accountRequest<T>(path: string, method = 'GET', body?: unknown, sessionToken = accountSessionToken): Promise<T> {
    const response = await fetch(`/stars/api/account${path}`, {
      method,
      headers: {
        'Content-Type': 'application/json',
        ...(sessionToken ? { 'X-Stars-Account-Token': sessionToken } : {})
      },
      body: body === undefined ? undefined : JSON.stringify(body)
    });

    return parseJsonResponse<T>(response);
  }

  async function request(path = '', method = 'GET', body?: unknown): Promise<Record<string, unknown>> {
    const response = await fetch(
      `${connection.apiBase}/stars/api/games/${connection.gameId}/turns/${connection.turnNumber}${path}`,
      {
        method,
        headers: {
          Authorization: `Bearer ${connection.token}`,
          'X-Stars-Player-Id': String(connection.playerId),
          'Content-Type': 'application/json'
        },
        body: body === undefined ? undefined : JSON.stringify(body)
      }
    );

    return parseJsonResponse<Record<string, unknown>>(response);
  }

  function syncOrdersFromStatus(nextStatus: Record<string, unknown>): void {
    const you = nextStatus.you;
    if (!you || typeof you !== 'object') return;
    const ownOrders = (you as Record<string, unknown>).orders;
    if (!ownOrders || typeof ownOrders !== 'object') return;
    const parsed = ownOrders as PlayerOrders;
    if (Array.isArray(parsed.fleets) && Array.isArray(parsed.production)) updateOrders(parsed);
  }

  async function loadStatus(): Promise<boolean> {
    busy = true;
    apiMessage = '';
    try {
      const nextStatus = await request();
      status = nextStatus;
      syncOrdersFromStatus(nextStatus);
      apiMessage = 'Turn status synchronized.';
      return true;
    } catch (error) {
      apiMessage = error instanceof Error ? error.message : String(error);
      return false;
    } finally {
      busy = false;
    }
  }

  async function enterAccountGame(games: AccountGameAccess[], warning?: string | null): Promise<void> {
    if (games.length === 0) throw new Error('This account is not linked to a game yet.');
    activateAccountGame(games[0]);
    demoMode = false;
    const connected = await loadStatus();
    if (!connected) throw new Error(apiMessage);
    if (warning) apiMessage = warning;
    screen = 'game';
  }

  async function accountLogin(credentials: AccountLoginInput, remember: boolean): Promise<void> {
    busy = true;
    loginError = '';
    try {
      const result = await accountRequest<AccountAuthResult>('/login', 'POST', credentials, '');
      storeAccountSession(result.sessionToken, remember);
      await enterAccountGame(result.games, result.mailWarning);
    } catch (error) {
      loginError = error instanceof Error ? error.message : String(error);
    } finally {
      busy = false;
    }
  }

  async function accountRegister(registration: AccountRegistrationInput, remember: boolean): Promise<void> {
    busy = true;
    loginError = '';
    try {
      const result = await accountRequest<AccountAuthResult>('/register', 'POST', registration, '');
      storeAccountSession(result.sessionToken, remember);
      await enterAccountGame(result.games, result.mailWarning);
    } catch (error) {
      loginError = error instanceof Error ? error.message : String(error);
    } finally {
      busy = false;
    }
  }

  async function directLogin(nextConnection: ConnectionSettings, remember: boolean): Promise<void> {
    connection = nextConnection;
    demoMode = false;
    loginError = '';
    storeDirectConnection(remember);
    const connected = await loadStatus();
    if (connected) screen = 'game';
    else loginError = apiMessage;
  }

  async function continueSession(): Promise<void> {
    loginError = '';
    demoMode = false;

    if (accountSessionToken) {
      busy = true;
      try {
        const profile = await accountRequest<AccountProfileResult>('/me');
        await enterAccountGame(profile.games);
        return;
      } catch (error) {
        localStorage.removeItem(accountSessionKey);
        sessionStorage.removeItem(accountSessionKey);
        sessionStorage.removeItem(accountGameTokenKey);
        accountSessionToken = '';
        loginError = error instanceof Error ? error.message : String(error);
      } finally {
        busy = false;
      }
    }

    if (!connection.token) {
      screen = 'login';
      return;
    }

    const connected = await loadStatus();
    if (connected) screen = 'game';
    else {
      loginError = apiMessage;
      screen = 'login';
    }
  }

  function openDemo(): void {
    demoMode = true;
    status = null;
    apiMessage = 'Demonstration universe: orders are stored locally but are not sent to the server.';
    screen = 'game';
  }

  async function saveDraft(): Promise<void> {
    busy = true;
    apiMessage = '';
    try {
      await request('/draft', 'PUT', { orders });
      apiMessage = 'Draft saved on the server.';
      await loadStatus();
    } catch (error) {
      apiMessage = error instanceof Error ? error.message : String(error);
      busy = false;
    }
  }

  async function submitTurn(): Promise<void> {
    busy = true;
    apiMessage = '';
    try {
      await request('/submit', 'POST', { orders });
      apiMessage = 'Turn submitted successfully.';
      await loadStatus();
    } catch (error) {
      apiMessage = error instanceof Error ? error.message : String(error);
      busy = false;
    }
  }

  async function reopenTurn(): Promise<void> {
    busy = true;
    apiMessage = '';
    try {
      await request('/reopen', 'POST');
      apiMessage = 'Turn reopened.';
      await loadStatus();
    } catch (error) {
      apiMessage = error instanceof Error ? error.message : String(error);
      busy = false;
    }
  }

  function exitGame(): void {
    screen = 'menu';
    apiMessage = '';
  }
</script>

<svelte:head>
  <title>Stars Rekindled — Player Client</title>
  <meta name="description" content="Player interface for the turn-based Stars Rekindled strategy game." />
  <link rel="icon" href="/favicon.svg" />
</svelte:head>

{#if screen === 'menu'}
  <MainMenu
    {hasSession}
    savedGameId={connection.gameId}
    savedTurn={connection.turnNumber}
    onContinue={continueSession}
    onLogin={() => { loginError = ''; screen = 'login'; }}
    onDemo={openDemo}
  />
{:else if screen === 'login'}
  <LoginScreen
    initial={connection}
    {busy}
    error={loginError}
    onDirectSubmit={directLogin}
    onAccountLogin={accountLogin}
    onAccountRegister={accountRegister}
    onDemo={openDemo}
    onBack={() => { loginError = ''; screen = 'menu'; }}
  />
{:else}
  <GameShell
    {connection}
    {orders}
    {status}
    {busy}
    message={apiMessage}
    {demoMode}
    onOrdersChange={updateOrders}
    onSaveDraft={saveDraft}
    onSubmit={submitTurn}
    onReopen={reopenTurn}
    onRefresh={loadStatus}
    onExit={exitGame}
  />
{/if}
