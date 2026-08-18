export type AppScreen = 'login' | 'lobby' | 'game';
export type GameSection = 'players' | 'galaxy' | 'planets' | 'fleets' | 'designs' | 'research' | 'diplomacy' | 'report';
export type Owner = 'player' | 'neutral' | 'crimson' | 'violet' | 'amber';
export type AccountAuthMode = 'web' | 'direct';
export type VisibilityState = 'visible' | 'explored';

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

export interface CreateAiGameInput {
  name: string;
  aiPlayers: number;
  aiLevel: 'standard';
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
  controllerType?: 'human' | 'ai';
  aiLevel?: 'standard' | null;
  submitted?: boolean;
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
  ownerLabel?: string;
  systemId?: string;
  targetSystemId?: string;
  colonizationCapacity?: number;
  legacyColonizationCapacity?: number;
  composition?: FleetCompositionEntry[];
  refit?: FleetRefitState | null;
  movementRange?: number;
  sensorRange?: number;
  attack?: number;
  defense?: number;
  fuelCapacity?: number;
  fuelUsePerHop?: number;
}

export interface StarSystem {
  id: string;
  name: string;
  x: number;
  y: number;
  owner: Owner;
  ownerPlayerId?: number | null;
  ownerLabel?: string;
  isYours?: boolean;
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
  sensorRange?: number;
  installations?: PlanetInstallation[];
  installationUpgrades?: PlanetInstallationUpgrade[];
  visibilityState?: VisibilityState;
  lastSeenTurn?: number;
}

export interface RouteLink {
  from: string;
  to: string;
  kind?: 'known' | 'planned' | 'hostile';
}

export type FleetOrderAction =
  | 'move'
  | 'hold'
  | 'colonize'
  | 'rename'
  | 'split'
  | 'transfer'
  | 'merge'
  | 'refit';

export interface FleetOrder {
  fleetId: string;
  action: FleetOrderAction;
  targetSystemId?: string;
  targetFleetId?: string;
  designId?: string;
  targetDesignId?: string;
  quantity?: number;
  name?: string;
}

export interface ProductionOrder {
  systemId: string;
  item: string;
  quantity: number;
  modelId?: string;
  modelName?: string;
  modelVersion?: number;
  productionKind?: 'ship' | 'installation' | 'upgrade' | 'legacy';
  sourceModelId?: string;
  sourceModelVersion?: number;
  upgradeTurns?: number;
}

export interface ResearchOrder {
  technologyId?: string;
  field?: string;
  allocation?: number;
}

export type ResearchField = 'propulsion' | 'sensors' | 'weapons' | 'defenses' | 'industry';

export interface ResearchTechnology {
  id: string;
  field: ResearchField;
  tier: number;
  name: string;
  cost: number;
  prerequisites: string[];
  effect: string;
  kind?: 'hardware' | 'applied';
  unlocks?: string[];
  globalEffects?: string[];
}

export type ModelCategory = 'hull' | 'engine' | 'scanner' | 'weapon' | 'armor' | 'utility' | 'installation';
export type ShipComponentCategory = Exclude<ModelCategory, 'installation'>;

export interface TechnologyModel {
  id: string;
  category: ModelCategory;
  family: string;
  name: string;
  version: number;
  requires: string[];
  unlocked: boolean;
  description: string;
  stats: Record<string, number>;
  upgradeFrom?: string | null;
  upgradeCost?: number | null;
  upgradeTurns?: number | null;
}

export interface ShipDesignComponentRef {
  category: ShipComponentCategory;
  modelId: string;
  name: string;
  version: number;
}

export interface ShipDesign {
  id: string;
  name: string;
  family: string;
  generation: number;
  components: ShipDesignComponentRef[];
  stats: {
    movementRange: number;
    sensorRange: number;
    attack: number;
    defense: number;
    fuelCapacity: number;
    fuelUsePerHop: number;
    colonizationCapacity?: number;
  };
  industryCost: number;
  batchSize: number;
  unlocked: boolean;
  current: boolean;
  obsolete?: boolean;
  basedOnDesignId?: string | null;
  createdTurn?: number;
}

export interface ShipDesignOrder {
  action: 'create';
  baseDesignId: string;
  name: string;
  componentModelIds: Record<ShipComponentCategory, string>;
  designId?: string;
  generation?: number;
}

export interface PlanetInstallation {
  family: string;
  modelId: string;
  name: string;
  version: number;
  installedTurn?: number;
}

export interface PlanetInstallationUpgrade {
  family: string;
  fromModelId: string;
  fromName: string;
  fromVersion: number;
  toModelId: string;
  toName: string;
  toVersion: number;
  industryCost: number;
  turnsTotal: number;
  turnsRemaining: number;
  startedTurn: number;
}

export interface ModelCatalog {
  components: TechnologyModel[];
  installations: TechnologyModel[];
  designs: ShipDesign[];
}

export interface FleetCompositionEntry {
  designId: string;
  designName: string;
  generation: number;
  quantity: number;
  components?: ShipDesignComponentRef[];
  stats?: ShipDesign['stats'];
}

export interface FleetRefitState {
  fromDesignId: string;
  fromDesignName: string;
  toDesignId: string;
  toDesignName: string;
  quantity: number;
  industryCost: number;
  turnsTotal: number;
  turnsRemaining: number;
  startedTurn: number;
  systemId: string;
}

export interface ResearchModifiers {
  fleetMovementRange: number;
  colonySensorBonus: number;
  fleetAttackPercent: number;
  planetDefensePercent: number;
  defenseGridAmount: number;
  industryIncomePercent: number;
  fuelEfficiencyPercent: number;
}

export interface PlayerResearchState {
  stockpile: number;
  income: number;
  activeTechnologyId: string | null;
  progress: Record<string, number>;
  completed: string[];
  levels: Record<ResearchField, number>;
  modifiers: ResearchModifiers;
}

export interface PlayerOrders {
  fleets: FleetOrder[];
  production: ProductionOrder[];
  research?: ResearchOrder[];
  designs?: ShipDesignOrder[];
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
  colonizationCapacity?: number;
  legacyColonizationCapacity?: number;
  composition?: FleetCompositionEntry[];
  refit?: FleetRefitState | null;
  movementRange?: number;
  sensorRange?: number;
  attack?: number;
  defense?: number;
  fuelCapacity?: number;
  fuelUsePerHop?: number;
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
  sensorRange?: number;
  installations?: PlanetInstallation[];
  installationUpgrades?: PlanetInstallationUpgrade[];
  visibilityState?: VisibilityState;
  lastSeenTurn?: number;
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
  controller_type?: 'human' | 'ai';
  ai_level?: 'standard' | null;
}

export interface AccountTurnStatusYou extends AccountTurnStatusPlayer {
  orders: PlayerOrders;
}

export interface AccountVisibilitySystem {
  state: VisibilityState;
  last_seen_turn: number;
}

export interface AccountVisibility {
  sensor_system_ids: string[];
  visible_enemy_fleets: number;
  colony_sensor_ranges?: Record<string, number>;
  systems?: Record<string, AccountVisibilitySystem>;
  known_system_ids?: string[];
  unknown_system_count?: number;
}

export interface TurnReportMovement {
  fleetId: string;
  fromSystemId: string;
  toSystemId: string;
}

export interface TurnReportFleetAction {
  action: 'rename' | 'split' | 'transfer' | 'merge';
  fleetId: string;
  targetFleetId?: string;
  newFleetId?: string;
  fromName?: string;
  toName?: string;
  name?: string;
  designId?: string;
  designName?: string;
  quantity?: number;
  shipsMerged?: number;
}

export interface TurnReportFleetRefit {
  fleetId: string;
  fleetName: string;
  fromDesignId: string;
  fromDesignName: string;
  toDesignId: string;
  toDesignName: string;
  quantity: number;
  industryCost: number;
  turnsTotal?: number;
  turnsRemaining?: number;
  startedTurn?: number;
  systemId?: string;
  completedTurn?: number;
}

export interface TurnReportColonization {
  fleetId: string;
  systemId: string;
  population?: number;
  consumedDesignName?: string | null;
  colonyShipConsumed?: boolean;
}

export interface TurnReportProduction {
  systemId: string;
  item: string;
  industryCost: number;
  modelId?: string;
  modelVersion?: number;
  productionKind?: 'ship' | 'installation' | 'upgrade' | 'legacy';
  batchSize?: number | null;
  colonizationCapacity?: number;
}


export interface TurnReportInstallationUpgradeCompleted {
  systemId: string;
  family: string;
  fromModelId: string;
  fromName: string;
  fromVersion: number;
  toModelId: string;
  toName: string;
  toVersion: number;
  industryCost: number;
  completedTurn: number;
}

export interface TurnReportDesignCreated {
  designId: string;
  name: string;
  family: string;
  generation: number;
  basedOnDesignId?: string | null;
  components: ShipDesignComponentRef[];
  stats: ShipDesign['stats'];
  industryCost: number;
}

export interface TurnReportResearchCompleted {
  technologyId: string;
  name: string;
  field: ResearchField;
  tier: number;
  cost: number;
  effect: string;
  kind?: 'hardware' | 'applied';
  unlocks?: string[];
}

export interface TurnReportResearchProgress {
  technologyId: string;
  name: string;
  field: ResearchField;
  progress: number;
  cost: number;
  spent: number;
  income: number;
}

export interface TurnReportSighting {
  type: 'detected' | 'lost';
  fleetId: string;
  fleetName?: string;
  systemId?: string | null;
  ownerPlayerId?: number;
  ships?: number;
}

export interface TurnReportData {
  message?: string;
  fleet_actions?: TurnReportFleetAction[];
  refits_started?: TurnReportFleetRefit[];
  refits_completed?: TurnReportFleetRefit[];
  movements?: TurnReportMovement[];
  colonizations?: TurnReportColonization[];
  productions?: TurnReportProduction[];
  installation_upgrades_completed?: TurnReportInstallationUpgradeCompleted[];
  designs_created?: TurnReportDesignCreated[];
  research_completed?: TurnReportResearchCompleted[];
  research_progress?: TurnReportResearchProgress | null;
  research_income?: number;
  research_stockpile?: number;
  sightings?: TurnReportSighting[];
  warnings?: string[];
  orders?: PlayerOrders;
}

export interface PreviousTurnReport {
  turn_number: number;
  year?: number;
  published_at: string | null;
  data: TurnReportData | null;
}

export interface AccountTurnStatus extends Record<string, unknown> {
  game: AccountTurnStatusGame;
  turn: AccountTurnStatusTurn;
  state?: ServerGameState;
  visibility?: AccountVisibility;
  research?: PlayerResearchState;
  research_catalog?: ResearchTechnology[];
  model_catalog?: ModelCatalog;
  previous_report?: PreviousTurnReport | null;
  players: AccountTurnStatusPlayer[];
  you: AccountTurnStatusYou;
}
