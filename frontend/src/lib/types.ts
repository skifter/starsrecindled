export type AppScreen = 'login' | 'lobby' | 'game';
export type GameSection = 'players' | 'galaxy' | 'planets' | 'fleets' | 'research' | 'diplomacy' | 'report';
export type Owner = 'player' | 'neutral' | 'crimson' | 'violet' | 'amber';
export type AccountAuthMode = 'web' | 'direct';

export interface ConnectionSettings {
  apiBase: string;
  gameId: number;
  playerId: number;
  turnNumber: number;
  token: string;
  authMode?: AccountAuthMode | 'demo';
  clientToken?: string;
  csrfToken?: string;
}

export interface AccountLoginInput {
  email: string;
  password: string;
}

export interface AccountRegistrationInput extends AccountLoginInput {
  displayName: string;
}

export interface AccountIdentity {
  id: number;
  email: string;
  displayName: string;
  clientTokenLastFour: string;
  clientTokenCreatedAt: string | null;
}

export interface GamePlayerSummary {
  playerId: number;
  displayName: string;
  active: boolean;
}

export interface AccountGameAccess {
  gameId: number;
  playerId: number;
  turnNumber: number;
  label: string;
  playerLabel: string;
  players: GamePlayerSummary[];
}

export interface AccountGameInvitation {
  id: number;
  gameId: number;
  playerId: number;
  label: string;
  playerLabel: string;
  createdAt: string;
  emailedAt: string | null;
}

export interface AccountProfileResult {
  account: AccountIdentity;
  games: AccountGameAccess[];
  invitations: AccountGameInvitation[];
  csrfToken: string;
  authMode: AccountAuthMode;
  notice?: string | null;
}

export interface JoinGameInput {
  invitationId: number;
}

export interface ResourceValue {
  id: string;
  label: string;
  value: number;
  income: number;
  icon: string;
}

export interface ProductionItem {
  id: string;
  label: string;
  kind: 'ship' | 'defense' | 'infrastructure';
  quantity: number;
  progress: number;
}

export interface FleetSummary {
  id: string;
  name: string;
  ships: number;
  role: string;
  location: string;
  destination?: string;
  eta?: number;
  ownerPlayerId?: number;
  systemId?: string;
  targetSystemId?: string;
}

export interface StarSystem {
  id: string;
  name: string;
  x: number;
  y: number;
  owner: Owner;
  ownerPlayerId?: number | null;
  ownerLabel?: string;
  className: string;
  population: number;
  capacity: number;
  happiness: number;
  security: number;
  development: number;
  defenses: number;
  resources: ResourceValue[];
  production: ProductionItem[];
  fleets: FleetSummary[];
  description: string;
  isCapital?: boolean;
}

export interface RouteLink {
  from: string;
  to: string;
  kind?: 'known' | 'planned' | 'hostile';
}

export interface FleetOrder {
  fleetId: string;
  action: 'move' | 'hold' | 'colonize';
  targetSystemId?: string;
}

export interface ProductionOrder {
  systemId: string;
  item: string;
  quantity: number;
}

export interface ResearchOrder {
  field: string;
  allocation: number;
}

export interface PlayerOrders {
  fleets: FleetOrder[];
  production: ProductionOrder[];
  research?: ResearchOrder[];
  [key: string]: unknown;
}

export interface ServerFleetState {
  id: string;
  ownerPlayerId: number;
  systemId: string;
  name: string;
  ships: number;
  role: string;
  destinationSystemId?: string;
}

export interface ServerSystemState {
  id: string;
  name: string;
  x: number;
  y: number;
  ownerPlayerId: number | null;
  className: string;
  population: number;
  capacity: number;
  happiness: number;
  security: number;
  development: number;
  defenses: number;
  resources: ResourceValue[];
  production: ProductionItem[];
  description: string;
  isCapital?: boolean;
}

export interface ServerUniverseState {
  systems?: ServerSystemState[];
  routes?: RouteLink[];
  fleets?: ServerFleetState[];
  planets?: unknown[];
}

export interface ServerGameState {
  year: number;
  universe: ServerUniverseState;
  [key: string]: unknown;
}

export interface AccountTurnStatusGame {
  id: number;
  name: string;
  current_turn: number;
}

export interface AccountTurnStatusTurn {
  number: number;
  status: string;
  rules_version?: string;
  queued_at: string | null;
  published_at: string | null;
}

export interface AccountTurnStatusPlayer {
  id: number;
  name: string;
  submitted: boolean;
  submitted_at: string | null;
}

export interface AccountTurnStatusYou extends AccountTurnStatusPlayer {
  orders: PlayerOrders;
}

export interface AccountTurnStatus extends Record<string, unknown> {
  game: AccountTurnStatusGame;
  turn: AccountTurnStatusTurn;
  state?: ServerGameState;
  players: AccountTurnStatusPlayer[];
  you: AccountTurnStatusYou;
}
