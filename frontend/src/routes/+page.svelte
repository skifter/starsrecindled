<script lang="ts">
  import { onMount } from 'svelte';
  import '../app.css';
  import AccountLobby from '$lib/screens/AccountLobby.svelte';
  import GameShell from '$lib/screens/GameShell.svelte';
  import LoginScreen from '$lib/screens/LoginScreen.svelte';
  import type {
    AccountGameAccess,
    AccountLoginInput,
    AccountProfileResult,
    AccountRegistrationInput,
    AccountTurnStatus,
    AppScreen,
    ConnectionSettings,
    JoinGameInput,
    PlayerOrders
  } from '$lib/types';

  const defaultConnection: ConnectionSettings = {
    apiBase: '',
    gameId: 1,
    playerId: 1,
    turnNumber: 1,
    token: '',
    authMode: 'web'
  };
  const defaultOrders: PlayerOrders = { fleets: [], production: [] };
  const directTokenKey = 'stars.clientToken';
  const directApiBaseKey = 'stars.clientApiBase';
  const pendingInvitationKey = 'stars.pendingInvitation';

  let screen: AppScreen = 'login';
  let connection: ConnectionSettings = { ...defaultConnection };
  let profile: AccountProfileResult | null = null;
  let activeGame: AccountGameAccess | null = null;
  let orders: PlayerOrders = { ...defaultOrders };
  let ordersDirty = false;
  let status: AccountTurnStatus | null = null;
  let busy = false;
  let loginError = '';
  let accountMessage = '';
  let apiMessage = '';
  let demoMode = false;
  let directClientToken = '';
  let directApiBase = '';
  let invitationNotice = '';

  onMount(() => {
    void initialize();

    const pollTimer = window.setInterval(() => {
      if (screen === 'game' && !demoMode && !busy) void loadStatus(true);
    }, 10000);

    return () => window.clearInterval(pollTimer);
  });

  async function initialize(): Promise<void> {
    const params = new URLSearchParams(window.location.search);
    const invitationFromLink = params.get('invite')?.trim() ?? '';
    if (invitationFromLink) {
      sessionStorage.setItem(pendingInvitationKey, invitationFromLink);
      invitationNotice = 'Game invitation detected. Log in or create an account with the invited email address.';
    }
    const savedOrders = localStorage.getItem('stars.orders');
    if (savedOrders) {
      try {
        const parsed = JSON.parse(savedOrders) as PlayerOrders;
        if (Array.isArray(parsed.fleets) && Array.isArray(parsed.production)) orders = parsed;
      } catch {
        localStorage.removeItem('stars.orders');
      }
    }

    if (params.get('demo') === '1') {
      openDemo();
      return;
    }

    try {
      const restored = await fetchProfile('web', '', '');
      profile = restored;
      accountMessage = restored.notice ?? '';
      screen = 'lobby';
      await acceptPendingInvitationLink();
      return;
    } catch {
      // No web session. Invitation links require email/password login.
    }

    if (sessionStorage.getItem(pendingInvitationKey)) {
      screen = 'login';
      return;
    }

    const savedDirectToken = sessionStorage.getItem(directTokenKey) ?? '';
    const savedDirectApiBase = sessionStorage.getItem(directApiBaseKey) ?? '';
    if (savedDirectToken) {
      try {
        profile = await fetchProfile('direct', savedDirectApiBase, savedDirectToken);
        directClientToken = savedDirectToken;
        directApiBase = savedDirectApiBase;
        screen = 'lobby';
        return;
      } catch {
        sessionStorage.removeItem(directTokenKey);
        sessionStorage.removeItem(directApiBaseKey);
      }
    }

    screen = 'login';
  }

  async function parseJsonResponse<T>(response: Response): Promise<T> {
    if (response.status === 204) return undefined as T;
    let data: T & { error?: string };
    try {
      data = await response.json() as T & { error?: string };
    } catch {
      throw new Error(`API returned HTTP ${response.status} without a JSON response.`);
    }
    if (!response.ok) throw new Error(String(data.error ?? `HTTP ${response.status}`));
    return data;
  }

  async function fetchProfile(mode: 'web' | 'direct', apiBase: string, clientToken: string): Promise<AccountProfileResult> {
    const response = await fetch(`${apiBase}/stars/api/account/${mode === 'direct' ? 'direct-login' : 'me'}`, {
      method: mode === 'direct' ? 'POST' : 'GET',
      credentials: mode === 'web' ? 'include' : 'omit',
      headers: mode === 'direct' ? { Authorization: `Bearer ${clientToken}` } : {}
    });

    return parseJsonResponse<AccountProfileResult>(response);
  }

  async function webAccountRequest<T>(path: string, method = 'GET', body?: unknown): Promise<T> {
    const response = await fetch(`/stars/api/account${path}`, {
      method,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        ...(method !== 'GET' && profile?.csrfToken ? { 'X-Stars-CSRF': profile.csrfToken } : {})
      },
      body: body === undefined ? undefined : JSON.stringify(body)
    });

    return parseJsonResponse<T>(response);
  }

  async function accountLogin(credentials: AccountLoginInput): Promise<void> {
    busy = true;
    loginError = '';
    try {
      profile = await webAccountRequest<AccountProfileResult>('/login', 'POST', credentials);
      directClientToken = '';
      directApiBase = '';
      accountMessage = profile.notice ?? '';
      screen = 'lobby';
      await acceptPendingInvitationLink();
    } catch (caught) {
      loginError = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  async function accountRegister(registration: AccountRegistrationInput): Promise<void> {
    busy = true;
    loginError = '';
    try {
      profile = await webAccountRequest<AccountProfileResult>('/register', 'POST', registration);
      directClientToken = '';
      directApiBase = '';
      accountMessage = profile.notice ?? 'Account created. Your personal client token was sent by email.';
      screen = 'lobby';
      await acceptPendingInvitationLink();
    } catch (caught) {
      loginError = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  async function directLogin(apiBase: string, clientToken: string): Promise<void> {
    busy = true;
    loginError = '';
    try {
      profile = await fetchProfile('direct', apiBase, clientToken);
      directClientToken = clientToken;
      directApiBase = apiBase;
      sessionStorage.setItem(directTokenKey, clientToken);
      sessionStorage.setItem(directApiBaseKey, apiBase);
      accountMessage = profile.notice ?? '';
      screen = 'lobby';
    } catch (caught) {
      loginError = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  async function joinGame(input: JoinGameInput): Promise<void> {
    if (!profile || profile.authMode !== 'web') return;
    busy = true;
    accountMessage = '';
    try {
      profile = await webAccountRequest<AccountProfileResult>('/games/join', 'POST', input);
      accountMessage = profile.notice ?? 'The selected game was linked to your account.';
    } catch (caught) {
      accountMessage = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  async function leaveGame(game: AccountGameAccess): Promise<void> {
    if (!profile || profile.authMode !== 'web') return;
    busy = true;
    accountMessage = '';
    try {
      profile = await webAccountRequest<AccountProfileResult>(`/games/${game.gameId}/leave`, 'POST', {});
      if (activeGame?.gameId === game.gameId) activeGame = null;
      accountMessage = profile.notice ?? `You left ${game.label}.`;
    } catch (caught) {
      accountMessage = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  async function acceptPendingInvitationLink(): Promise<void> {
    if (!profile || profile.authMode !== 'web') return;
    const token = sessionStorage.getItem(pendingInvitationKey) ?? '';
    if (!token) return;

    try {
      profile = await webAccountRequest<AccountProfileResult>('/invitations/accept-link', 'POST', { token });
      accountMessage = profile.notice ?? 'Invitation accepted. The game is now linked to your account.';
      sessionStorage.removeItem(pendingInvitationKey);
      invitationNotice = '';
      const url = new URL(window.location.href);
      url.searchParams.delete('invite');
      window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
    } catch (caught) {
      accountMessage = caught instanceof Error ? caught.message : String(caught);
    }
  }

  async function rotateClientToken(): Promise<void> {
    if (!profile || profile.authMode !== 'web') return;
    busy = true;
    accountMessage = '';
    try {
      profile = await webAccountRequest<AccountProfileResult>('/token/rotate', 'POST', {});
      accountMessage = profile.notice ?? 'A new client token was sent by email.';
    } catch (caught) {
      accountMessage = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  async function logout(): Promise<void> {
    if (profile?.authMode === 'web') {
      try { await webAccountRequest<void>('/logout', 'POST', {}); } catch { /* Clear the client state anyway. */ }
    }
    sessionStorage.removeItem(directTokenKey);
    sessionStorage.removeItem(directApiBaseKey);
    directClientToken = '';
    directApiBase = '';
    profile = null;
    connection = { ...defaultConnection };
    accountMessage = '';
    loginError = '';
    screen = 'login';
  }

  function setOrders(next: PlayerOrders, dirty: boolean): void {
    orders = next;
    ordersDirty = dirty;
    localStorage.setItem('stars.orders', JSON.stringify(next));
  }

  function updateOrders(next: PlayerOrders): void {
    setOrders(next, true);
  }

  async function request<T = AccountTurnStatus>(path = '', method = 'GET', body?: unknown): Promise<T> {
    const apiBase = connection.apiBase;
    const headers: Record<string, string> = { 'Content-Type': 'application/json' };
    const options: RequestInit = {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body)
    };

    if (connection.authMode === 'web') {
      options.credentials = 'include';
      if (method !== 'GET' && connection.csrfToken) headers['X-Stars-CSRF'] = connection.csrfToken;
    } else if (connection.authMode === 'direct') {
      options.credentials = 'omit';
      headers.Authorization = `Bearer ${connection.clientToken ?? ''}`;
    }

    const response = await fetch(
      `${apiBase}/stars/api/account/games/${connection.gameId}/turns/${connection.turnNumber}${path}`,
      options
    );

    return parseJsonResponse<T>(response);
  }

  function syncOrdersFromStatus(nextStatus: AccountTurnStatus, force = false): void {
    if (ordersDirty && !force) return;
    const parsed = nextStatus.you.orders;
    if (Array.isArray(parsed.fleets) && Array.isArray(parsed.production)) setOrders(parsed, false);
  }

  function moveToServerTurn(turnNumber: number): void {
    if (!Number.isInteger(turnNumber) || turnNumber < 1 || turnNumber === connection.turnNumber) return;

    connection = { ...connection, turnNumber };
    if (activeGame) activeGame = { ...activeGame, turnNumber };
    if (profile) {
      profile = {
        ...profile,
        games: profile.games.map((game) => game.gameId === connection.gameId ? { ...game, turnNumber } : game)
      };
    }
  }

  async function loadStatus(silent = false): Promise<boolean> {
    if (!silent) {
      busy = true;
      apiMessage = '';
    }

    try {
      let nextStatus = await request<AccountTurnStatus>();
      let turnChanged = false;
      if (nextStatus.game.current_turn !== connection.turnNumber) {
        moveToServerTurn(nextStatus.game.current_turn);
        nextStatus = await request<AccountTurnStatus>();
        turnChanged = true;
      }

      status = nextStatus;
      syncOrdersFromStatus(nextStatus, turnChanged);
      if (!silent) apiMessage = 'Turn status synchronized.';
      return true;
    } catch (caught) {
      if (!silent) apiMessage = caught instanceof Error ? caught.message : String(caught);
      return false;
    } finally {
      if (!silent) busy = false;
    }
  }

  async function playGame(game: AccountGameAccess): Promise<void> {
    if (!profile) return;
    activeGame = game;
    connection = {
      apiBase: profile.authMode === 'direct' ? directApiBase : '',
      gameId: game.gameId,
      playerId: game.playerId,
      turnNumber: game.turnNumber,
      token: '',
      authMode: profile.authMode,
      clientToken: profile.authMode === 'direct' ? directClientToken : undefined,
      csrfToken: profile.csrfToken
    };
    demoMode = false;
    status = null;
    ordersDirty = false;
    const connected = await loadStatus();
    if (connected) screen = 'game';
    else accountMessage = apiMessage;
  }

  function openDemo(): void {
    activeGame = { gameId: 1, playerId: 1, turnNumber: 1, label: 'Demonstration universe', playerLabel: 'Demonstration player', players: [] };
    demoMode = true;
    connection = { ...defaultConnection, authMode: 'demo' };
    status = null;
    apiMessage = 'Demonstration universe: orders are stored locally but are not sent to the server.';
    screen = 'game';
  }

  async function saveDraft(): Promise<void> {
    if (demoMode) { apiMessage = 'Demo draft stored locally.'; return; }
    busy = true;
    apiMessage = '';
    try {
      await request('/draft', 'PUT', { orders });
      ordersDirty = false;
      await loadStatus(true);
      apiMessage = 'Draft saved on the server.';
    } catch (caught) {
      apiMessage = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  async function submitTurn(): Promise<void> {
    if (demoMode) { apiMessage = 'Demo turn submitted locally.'; return; }
    busy = true;
    apiMessage = '';
    try {
      await request('/submit', 'POST', { orders });
      ordersDirty = false;
      await loadStatus(true);
      apiMessage = 'Turn submitted successfully. Waiting for the remaining players.';
    } catch (caught) {
      apiMessage = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  async function reopenTurn(): Promise<void> {
    if (demoMode) { apiMessage = 'Demo turn reopened locally.'; return; }
    busy = true;
    apiMessage = '';
    try {
      await request('/reopen', 'POST');
      await loadStatus(true);
      apiMessage = 'Turn reopened.';
    } catch (caught) {
      apiMessage = caught instanceof Error ? caught.message : String(caught);
    } finally {
      busy = false;
    }
  }

  function exitGame(): void {
    apiMessage = '';
    activeGame = null;
    if (profile) screen = 'lobby';
    else screen = 'login';
  }
</script>

<svelte:head>
  <title>Stars Rekindled — Player Client</title>
  <meta name="description" content="Player interface for the turn-based Stars Rekindled strategy game." />
  <link rel="icon" href="/favicon.svg" />
</svelte:head>

{#if screen === 'login'}
  <LoginScreen
    {busy}
    error={loginError}
    notice={invitationNotice}
    onAccountLogin={accountLogin}
    onAccountRegister={accountRegister}
    onDirectLogin={directLogin}
    onDemo={openDemo}
  />
{:else if screen === 'lobby' && profile}
  <AccountLobby
    {profile}
    {busy}
    message={accountMessage}
    onPlay={playGame}
    onJoin={joinGame}
    onLeave={leaveGame}
    onRotateToken={rotateClientToken}
    onLogout={logout}
    onDemo={openDemo}
  />
{:else}
  <GameShell
    {connection}
    game={activeGame}
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
