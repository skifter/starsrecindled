export type AppScreen = 'menu' | 'login' | 'game';
export type GameSection = 'galaxy' | 'planets' | 'fleets' | 'research' | 'diplomacy' | 'report';
export type Owner = 'player' | 'neutral' | 'crimson' | 'violet' | 'amber';

export interface ConnectionSettings {
  apiBase: string;
  gameId: number;
  playerId: number;
  turnNumber: number;
  token: string;
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

export interface AccountLoginInput {
  email: string;
  password: string;
}

export interface AccountRegistrationInput extends AccountLoginInput {
  displayName: string;
  gameId: number;
  playerId: number;
  gameToken: string;
}

export interface AccountIdentity {
  id: number;
  email: string;
  displayName: string;
}

export interface AccountGameAccess {
  gameId: number;
  playerId: number;
  turnNumber: number;
  token: string;
  tokenLastFour: string;
  label: string;
}

export interface AccountAuthResult {
  sessionToken: string;
  expiresAt: string;
  account: AccountIdentity;
  games: AccountGameAccess[];
  mailWarning?: string | null;
}

export interface AccountProfileResult {
  account: AccountIdentity;
  games: AccountGameAccess[];
}
