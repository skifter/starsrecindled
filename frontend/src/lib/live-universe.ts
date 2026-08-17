import { ownerForPlayerId } from './player-colors';
import type {
  AccountTurnStatusPlayer,
  RouteLink,
  ServerGameState,
  StarSystem
} from './types';

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
  const playerIds = players.map((player) => player.id);
  const systemNames = new Map(serverSystems.map((system) => [system.id, system.name]));

  const systems: StarSystem[] = serverSystems.map((system) => {
    const owner = system.ownerPlayerId === null
      ? 'neutral'
      : ownerForPlayerId(system.ownerPlayerId, playerIds);
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
        ownerLabel: playerNames.get(fleet.ownerPlayerId) ?? `Player ${fleet.ownerPlayerId}`,
        systemId: fleet.systemId,
        targetSystemId: fleet.destinationSystemId,
        colonizationCapacity: fleet.colonizationCapacity,
        composition: fleet.composition,
        movementRange: fleet.movementRange,
        sensorRange: fleet.sensorRange,
        attack: fleet.attack,
        defense: fleet.defense,
        fuelCapacity: fleet.fuelCapacity,
        fuelUsePerHop: fleet.fuelUsePerHop
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
      isCapital: system.isCapital === true,
      sensorRange: Math.max(0, Number(system.sensorRange ?? (system.ownerPlayerId === null ? 0 : 1))),
      installations: Array.isArray(system.installations) ? system.installations : [],
      visibilityState: system.visibilityState ?? 'visible',
      lastSeenTurn: system.lastSeenTurn,
      isYours: system.ownerPlayerId === currentPlayerId
    };
  });

  return { systems, routes };
}
