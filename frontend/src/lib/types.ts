export type AppScreen = 'login' | 'lobby' | 'game';
export type GameSection = 'galaxy' | 'planets' | 'fleets' | 'research' | 'diplomacy' | 'report';
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

export interface AccountGameAccess {
  gameId: number;
  playerId: number;
  turnNumber: number;
  label: string;
  playerLabel: string;
}

export interface AccountProfileResult {
  account: AccountIdentity;
  games: AccountGameAccess[];
  csrfToken: string;
  authMode: AccountAuthMode;
  notice?: string | null;
}

export interface JoinGameInput {
  gameId: number;
  invitationCode: string;
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
}

export interface StarSystem {
  id: string;
  name: string;
  x: number;
  y: number;
  owner: Owner;
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
