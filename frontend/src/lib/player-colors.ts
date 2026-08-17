import type { Owner } from './types';

export const OWNER_COLORS: Record<Owner, string> = {
  player: '#47c8ff',
  neutral: '#dcecff',
  crimson: '#ff675f',
  violet: '#ce72ff',
  amber: '#ffbd48'
};

const PLAYER_OWNER_SEQUENCE: Owner[] = ['player', 'crimson', 'violet', 'amber'];

export function ownerForPlayerId(playerId: number, playerIds: number[]): Owner {
  const orderedIds = [...new Set(playerIds.filter((id) => Number.isInteger(id)))].sort((a, b) => a - b);
  const index = orderedIds.indexOf(playerId);
  return PLAYER_OWNER_SEQUENCE[(index >= 0 ? index : 0) % PLAYER_OWNER_SEQUENCE.length];
}

export function colorForPlayerId(playerId: number, playerIds: number[]): string {
  return OWNER_COLORS[ownerForPlayerId(playerId, playerIds)];
}
