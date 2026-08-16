import type {
  AccountTurnStatusPlayer,
  Owner,
  RouteLink,
  ServerGameState,
  StarSystem
} from './types';

const opponentOwners: Owner[] = ['crimson', 'violet', 'amber'];

export interface LiveUniverse {
  systems: StarSystem[];
  routes: RouteLink[];
}

export function mapLiveUniverse(
  state: ServerGameState | undefined,
  currentPlayerId: number,
  players: AccountTurnStatusPlayer[]
): LiveUniverse {
  const serverSystems = Array.isArray(state?.universe?.systems) ? state.universe.systems : [];
  const serverFleets = Array.isArray(state?.universe?.fleets) ? state.universe.fleets : [];
  const routes = Array.isArray(state?.universe?.routes) ? state.universe.routes : [];
  const playerNames = new Map(players.map((player) => [player.id, player.name]));
  const opponents = players.filter((player) => player.id !== currentPlayerId).map((player) => player.id);
  const systemNames = new Map(serverSystems.map((system) => [system.id, system.name]));

  const systems: StarSystem[] = serverSystems.map((system) => {
    const owner = ownerFor(system.ownerPlayerId, currentPlayerId, opponents);
    const fleets = serverFleets
      .filter((fleet) => fleet.systemId === system.id)
      .map((fleet) => ({
        id: fleet.id,
        name: fleet.name,
        ships: fleet.ships,
        role: fleet.role,
        location: system.name,
        destination: fleet.destinationSystemId ? systemNames.get(fleet.destinationSystemId) : undefined,
        ownerPlayerId: fleet.ownerPlayerId,
        systemId: fleet.systemId,
        targetSystemId: fleet.destinationSystemId
      }));

    return {
      id: system.id,
      name: system.name,
      x: system.x,
      y: system.y,
      owner,
      ownerPlayerId: system.ownerPlayerId,
      ownerLabel: system.ownerPlayerId === null
        ? 'Unclaimed'
        : (playerNames.get(system.ownerPlayerId) ?? `Player ${system.ownerPlayerId}`),
      className: system.className,
      population: system.population,
      capacity: system.capacity,
      happiness: system.happiness,
      security: system.security,
      development: system.development,
      defenses: system.defenses,
      resources: Array.isArray(system.resources) ? system.resources : [],
      production: Array.isArray(system.production) ? system.production : [],
      fleets,
      description: system.description,
      isCapital: system.isCapital === true
    };
  });

  return { systems, routes };
}

function ownerFor(ownerPlayerId: number | null, currentPlayerId: number, opponents: number[]): Owner {
  if (ownerPlayerId === null) return 'neutral';
  if (ownerPlayerId === currentPlayerId) return 'player';
  const index = Math.max(0, opponents.indexOf(ownerPlayerId));
  return opponentOwners[index % opponentOwners.length];
}
