import type { StarSystem } from './types';

export interface TerritorySource {
  empireId: number;
  systemId: string;
  x: number;
  y: number;
  strength: number;
  radius: number;
  memory: boolean;
}

export interface EmpireInfluenceTerritory {
  empireId: number;
  paths: string[];
  memory: boolean;
  mixedIntel: boolean;
  sourceSystemIds: string[];
}

export interface ContestedTerritory {
  paths: string[];
}

export interface InfluenceResult {
  territories: EmpireInfluenceTerritory[];
  contested: ContestedTerritory;
}

export interface InfluenceOptions {
  width?: number;
  height?: number;
  step?: number;
  minimumControl?: number;
  contestedRatio?: number;
}

type Point = { x: number; y: number };
type Segment = { a: Point; b: Point };
type GridPoint = { x: number; y: number; scores: Map<number, number> };

const DEFAULT_WIDTH = 1000;
const DEFAULT_HEIGHT = 620;
const DEFAULT_STEP = 12;
const DEFAULT_MINIMUM_CONTROL = 0.13;
const DEFAULT_CONTESTED_RATIO = 0.76;

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

export function colonyInfluenceStrength(system: StarSystem): number {
  const population = Math.max(0, system.population ?? 0);
  const capacity = Math.max(1, system.capacity ?? Math.max(population, 1));
  const populationRatio = clamp(population / capacity, 0, 1.5);
  const development = clamp((system.development ?? 0) / 100, 0, 1.5);
  const defenses = clamp((system.defenses ?? 0) / 1000, 0, 2.5);
  const sensorRange = clamp(Math.round(system.sensorRange ?? 1), 0, 3);

  return (
    1.0 +
    populationRatio * 0.7 +
    development * 0.55 +
    defenses * 0.6 +
    sensorRange * 0.2 +
    (system.isCapital ? 0.9 : 0)
  );
}

export function colonyInfluenceRadius(system: StarSystem): number {
  const strength = colonyInfluenceStrength(system);
  const sensorRange = clamp(Math.round(system.sensorRange ?? 1), 0, 3);
  const defenses = clamp((system.defenses ?? 0) / 1000, 0, 2.5);

  return clamp(
    92 + strength * 16 + sensorRange * 11 + defenses * 10 + (system.isCapital ? 12 : 0),
    108,
    190,
  );
}

function gaussianInfluence(distance: number, strength: number, radius: number): number {
  const sigma = Math.max(1, radius * 0.58);
  return strength * Math.exp(-(distance * distance) / (2 * sigma * sigma));
}

function buildSources(systems: StarSystem[]): TerritorySource[] {
  return systems
    .filter((system) => typeof system.ownerPlayerId === 'number' && system.ownerPlayerId > 0)
    .map((system) => ({
      empireId: system.ownerPlayerId as number,
      systemId: system.id,
      x: system.x * 10,
      y: system.y * 6.2,
      strength: colonyInfluenceStrength(system),
      radius: colonyInfluenceRadius(system),
      memory: system.visibilityState === 'explored',
    }));
}

function scoreAtPoint(x: number, y: number, sources: TerritorySource[]): Map<number, number> {
  const scores = new Map<number, number>();
  for (const source of sources) {
    const distance = Math.hypot(x - source.x, y - source.y);
    if (distance > source.radius * 2.4) continue;
    const score = gaussianInfluence(distance, source.strength, source.radius);
    if (score < 0.0005) continue;
    scores.set(source.empireId, (scores.get(source.empireId) ?? 0) + score);
  }
  return scores;
}

function buildGrid(width: number, height: number, step: number, sources: TerritorySource[]): GridPoint[][] {
  const margin = 260;
  const minX = -margin;
  const minY = -margin;
  const maxX = width + margin;
  const maxY = height + margin;
  const columns = Math.ceil((maxX - minX) / step) + 1;
  const rows = Math.ceil((maxY - minY) / step) + 1;
  const grid: GridPoint[][] = [];

  for (let row = 0; row < rows; row += 1) {
    const y = Math.min(maxY, minY + row * step);
    const line: GridPoint[] = [];
    for (let column = 0; column < columns; column += 1) {
      const x = Math.min(maxX, minX + column * step);
      line.push({ x, y, scores: scoreAtPoint(x, y, sources) });
    }
    grid.push(line);
  }

  return grid;
}

function empireField(point: GridPoint, empireId: number, minimumControl: number): number {
  const own = point.scores.get(empireId) ?? 0;
  let strongestOther = minimumControl;
  for (const [otherEmpireId, score] of point.scores) {
    if (otherEmpireId === empireId) continue;
    strongestOther = Math.max(strongestOther, score);
  }
  return own - strongestOther;
}

function contestedField(point: GridPoint, minimumControl: number, contestedRatio: number): number {
  const ranked = [...point.scores.values()].sort((a, b) => b - a);
  const first = ranked[0] ?? 0;
  const second = ranked[1] ?? 0;
  if (first < minimumControl || second <= 0) return -1;
  return second / first - contestedRatio;
}

function interpolate(a: Point, b: Point, va: number, vb: number): Point {
  const denominator = va - vb;
  const t = Math.abs(denominator) < 1e-9 ? 0.5 : clamp(va / denominator, 0, 1);
  return {
    x: a.x + (b.x - a.x) * t,
    y: a.y + (b.y - a.y) * t,
  };
}

function marchingSegments(grid: GridPoint[][], valueAt: (point: GridPoint) => number): Segment[] {
  const segments: Segment[] = [];

  for (let row = 0; row < grid.length - 1; row += 1) {
    for (let column = 0; column < grid[row].length - 1; column += 1) {
      const tl = grid[row][column];
      const tr = grid[row][column + 1];
      const br = grid[row + 1][column + 1];
      const bl = grid[row + 1][column];
      const vtl = valueAt(tl);
      const vtr = valueAt(tr);
      const vbr = valueAt(br);
      const vbl = valueAt(bl);

      const crossings: { edge: number; point: Point }[] = [];
      if ((vtl >= 0) !== (vtr >= 0)) crossings.push({ edge: 0, point: interpolate(tl, tr, vtl, vtr) });
      if ((vtr >= 0) !== (vbr >= 0)) crossings.push({ edge: 1, point: interpolate(tr, br, vtr, vbr) });
      if ((vbr >= 0) !== (vbl >= 0)) crossings.push({ edge: 2, point: interpolate(br, bl, vbr, vbl) });
      if ((vbl >= 0) !== (vtl >= 0)) crossings.push({ edge: 3, point: interpolate(bl, tl, vbl, vtl) });

      if (crossings.length === 2) {
        segments.push({ a: crossings[0].point, b: crossings[1].point });
      } else if (crossings.length === 4) {
        const centerValue = (vtl + vtr + vbr + vbl) / 4;
        const byEdge = new Map(crossings.map((entry) => [entry.edge, entry.point]));
        const e0 = byEdge.get(0);
        const e1 = byEdge.get(1);
        const e2 = byEdge.get(2);
        const e3 = byEdge.get(3);
        if (!e0 || !e1 || !e2 || !e3) continue;

        if (centerValue >= 0) {
          segments.push({ a: e0, b: e3 }, { a: e1, b: e2 });
        } else {
          segments.push({ a: e0, b: e1 }, { a: e2, b: e3 });
        }
      }
    }
  }

  return segments;
}

function pointKey(point: Point): string {
  return `${point.x.toFixed(2)}:${point.y.toFixed(2)}`;
}

function connectSegments(segments: Segment[]): Point[][] {
  const endpointMap = new Map<string, { segmentIndex: number; end: 'a' | 'b' }[]>();
  segments.forEach((segment, segmentIndex) => {
    for (const end of ['a', 'b'] as const) {
      const key = pointKey(segment[end]);
      const list = endpointMap.get(key) ?? [];
      list.push({ segmentIndex, end });
      endpointMap.set(key, list);
    }
  });

  const used = new Set<number>();
  const lines: Point[][] = [];

  const nextUnusedAt = (point: Point): { segmentIndex: number; end: 'a' | 'b' } | null => {
    for (const candidate of endpointMap.get(pointKey(point)) ?? []) {
      if (!used.has(candidate.segmentIndex)) return candidate;
    }
    return null;
  };

  for (let startIndex = 0; startIndex < segments.length; startIndex += 1) {
    if (used.has(startIndex)) continue;
    used.add(startIndex);
    const start = segments[startIndex];
    const line: Point[] = [start.a, start.b];

    let guard = 0;
    while (guard < segments.length + 5) {
      guard += 1;
      const candidate = nextUnusedAt(line[line.length - 1]);
      if (!candidate) break;
      const segment = segments[candidate.segmentIndex];
      used.add(candidate.segmentIndex);
      line.push(candidate.end === 'a' ? segment.b : segment.a);
      if (pointKey(line[line.length - 1]) === pointKey(line[0])) break;
    }

    guard = 0;
    while (guard < segments.length + 5 && pointKey(line[0]) !== pointKey(line[line.length - 1])) {
      guard += 1;
      const candidate = nextUnusedAt(line[0]);
      if (!candidate) break;
      const segment = segments[candidate.segmentIndex];
      used.add(candidate.segmentIndex);
      line.unshift(candidate.end === 'a' ? segment.b : segment.a);
    }

    if (line.length >= 4) lines.push(line);
  }

  return lines;
}

function chaikinClosed(points: Point[], passes = 2): Point[] {
  let current = [...points];
  if (pointKey(current[0]) === pointKey(current[current.length - 1])) current.pop();

  for (let pass = 0; pass < passes; pass += 1) {
    const next: Point[] = [];
    for (let index = 0; index < current.length; index += 1) {
      const a = current[index];
      const b = current[(index + 1) % current.length];
      next.push(
        { x: a.x * 0.75 + b.x * 0.25, y: a.y * 0.75 + b.y * 0.25 },
        { x: a.x * 0.25 + b.x * 0.75, y: a.y * 0.25 + b.y * 0.75 },
      );
    }
    current = next;
  }

  return current;
}

function polygonArea(points: Point[]): number {
  let area = 0;
  for (let index = 0; index < points.length; index += 1) {
    const a = points[index];
    const b = points[(index + 1) % points.length];
    area += a.x * b.y - b.x * a.y;
  }
  return Math.abs(area) / 2;
}

function linesToPaths(lines: Point[][]): string[] {
  return lines
    .filter((line) => pointKey(line[0]) === pointKey(line[line.length - 1]))
    .map((line) => chaikinClosed(line, 2))
    .filter((line) => line.length >= 6 && polygonArea(line) >= 120)
    .map((line) => `M ${line.map((point) => `${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' L ')} Z`);
}

export function buildEmpireInfluence(
  systems: StarSystem[],
  options: InfluenceOptions = {},
): InfluenceResult {
  const width = options.width ?? DEFAULT_WIDTH;
  const height = options.height ?? DEFAULT_HEIGHT;
  const step = options.step ?? DEFAULT_STEP;
  const minimumControl = options.minimumControl ?? DEFAULT_MINIMUM_CONTROL;
  const contestedRatio = options.contestedRatio ?? DEFAULT_CONTESTED_RATIO;
  const sources = buildSources(systems);
  if (sources.length === 0) return { territories: [], contested: { paths: [] } };

  const grid = buildGrid(width, height, step, sources);
  const empireIds = [...new Set(sources.map((source) => source.empireId))];
  const territories: EmpireInfluenceTerritory[] = [];

  for (const empireId of empireIds) {
    const segments = marchingSegments(grid, (point) => empireField(point, empireId, minimumControl));
    const paths = linesToPaths(connectSegments(segments));
    if (paths.length === 0) continue;
    const empireSources = sources.filter((source) => source.empireId === empireId);
    const memoryCount = empireSources.filter((source) => source.memory).length;
    territories.push({
      empireId,
      paths,
      memory: memoryCount === empireSources.length,
      mixedIntel: memoryCount > 0 && memoryCount < empireSources.length,
      sourceSystemIds: empireSources.map((source) => source.systemId),
    });
  }

  const contestedSegments = marchingSegments(
    grid,
    (point) => contestedField(point, minimumControl, contestedRatio),
  );

  return {
    territories,
    contested: { paths: linesToPaths(connectSegments(contestedSegments)) },
  };
}

export function buildCoverageEnvelope(points: Point[], radius = 52): string {
  if (points.length === 0) return '';
  if (points.length === 1) {
    const samples = 48;
    const circle = Array.from({ length: samples }, (_, index) => {
      const angle = (Math.PI * 2 * index) / samples;
      return {
        x: points[0].x + Math.cos(angle) * radius,
        y: points[0].y + Math.sin(angle) * radius,
      };
    });
    return `M ${circle.map((point) => `${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' L ')} Z`;
  }

  const centroid = points.reduce(
    (acc, point) => ({ x: acc.x + point.x / points.length, y: acc.y + point.y / points.length }),
    { x: 0, y: 0 },
  );
  const bucketCount = 72;
  const radial = Array.from({ length: bucketCount }, () => radius);

  for (const point of points) {
    for (let index = 0; index < bucketCount; index += 1) {
      const angle = (Math.PI * 2 * index) / bucketCount;
      const ux = Math.cos(angle);
      const uy = Math.sin(angle);
      const projection = (point.x - centroid.x) * ux + (point.y - centroid.y) * uy;
      radial[index] = Math.max(radial[index], projection + radius);
    }
  }

  const smoothed = radial.map((_, index) => {
    const p2 = radial[(index - 2 + bucketCount) % bucketCount];
    const p1 = radial[(index - 1 + bucketCount) % bucketCount];
    const c = radial[index];
    const n1 = radial[(index + 1) % bucketCount];
    const n2 = radial[(index + 2) % bucketCount];
    return p2 * 0.08 + p1 * 0.17 + c * 0.5 + n1 * 0.17 + n2 * 0.08;
  });

  const outline = smoothed.map((distance, index) => {
    const angle = (Math.PI * 2 * index) / bucketCount;
    return {
      x: centroid.x + Math.cos(angle) * distance,
      y: centroid.y + Math.sin(angle) * distance,
    };
  });

  return `M ${outline.map((point) => `${point.x.toFixed(1)} ${point.y.toFixed(1)}`).join(' L ')} Z`;
}
