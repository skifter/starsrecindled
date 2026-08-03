import type { FleetSummary, ResourceValue, RouteLink, StarSystem } from './types';

const baseResources = (industry: number, science: number, bio: number, energy: number): ResourceValue[] => [
  { id: 'industry', label: 'Industry', value: industry * 52, income: industry, icon: 'industry' },
  { id: 'science', label: 'Science', value: science * 44, income: science, icon: 'research' },
  { id: 'bio', label: 'Biomass', value: bio * 38, income: bio, icon: 'planet' },
  { id: 'energy', label: 'Energy', value: energy * 48, income: energy, icon: 'energy' }
];

const fleets: FleetSummary[] = [
  { id: 'fleet-1', name: '1st Stellar Fleet', ships: 2340, role: 'Battle group', location: 'Aurelia' },
  { id: 'fleet-2', name: 'Frontier Guard', ships: 620, role: 'Patrol', location: 'Aurelia' },
  { id: 'fleet-3', name: 'Pathfinder Wing', ships: 84, role: 'Scout', location: 'Estara', destination: 'Mirzan', eta: 1 },
  { id: 'fleet-4', name: 'Colonial Convoy', ships: 37, role: 'Colonizer', location: 'Lumen', destination: 'Yarvik', eta: 2 },
  { id: 'fleet-5', name: 'Silent Meridian', ships: 156, role: 'Interceptor', location: 'Kaltos', destination: 'Vorus', eta: 1 }
];

export const systems: StarSystem[] = [
  {
    id: 'aurelia', name: 'Aurelia', x: 49, y: 48, owner: 'player', className: 'Blue-white star',
    population: 7.8, capacity: 10, happiness: 72, security: 85, development: 68, defenses: 1860,
    resources: baseResources(95, 72, 48, 16),
    production: [
      { id: 'p1', label: 'Starport IV', kind: 'infrastructure', quantity: 3, progress: 68 },
      { id: 'p2', label: 'Defense Grid II', kind: 'defense', quantity: 2, progress: 41 },
      { id: 'p3', label: 'Hydroponic Farms II', kind: 'infrastructure', quantity: 1, progress: 83 }
    ],
    fleets: fleets.filter((fleet) => fleet.location === 'Aurelia'),
    description: 'Core world of the Nova Dominion and administrative center of the inner systems.',
    isCapital: true
  },
  {
    id: 'lumen', name: 'Lumen', x: 39, y: 38, owner: 'player', className: 'White dwarf',
    population: 3.4, capacity: 6.2, happiness: 81, security: 67, development: 51, defenses: 720,
    resources: baseResources(52, 88, 34, 23),
    production: [{ id: 'p4', label: 'Research Annex', kind: 'infrastructure', quantity: 1, progress: 54 }],
    fleets: fleets.filter((fleet) => fleet.location === 'Lumen'),
    description: 'A scientific colony built around a stable white dwarf observatory network.'
  },
  {
    id: 'estara', name: 'Estara', x: 39, y: 60, owner: 'player', className: 'Yellow star',
    population: 2.8, capacity: 5.1, happiness: 76, security: 59, development: 47, defenses: 510,
    resources: baseResources(61, 39, 73, 28),
    production: [{ id: 'p5', label: 'Orbital Foundry', kind: 'infrastructure', quantity: 1, progress: 31 }],
    fleets: fleets.filter((fleet) => fleet.location === 'Estara'),
    description: 'Agricultural and ship-support world on the southern frontier.'
  },
  {
    id: 'kaltos', name: 'Kaltos', x: 25, y: 25, owner: 'player', className: 'Orange giant',
    population: 1.7, capacity: 3.9, happiness: 64, security: 74, development: 39, defenses: 960,
    resources: baseResources(74, 26, 21, 46),
    production: [{ id: 'p6', label: 'Escort Frigate', kind: 'ship', quantity: 4, progress: 62 }],
    fleets: fleets.filter((fleet) => fleet.location === 'Kaltos'),
    description: 'Fortified mining colony guarding the north-western approach.'
  },
  {
    id: 'yarvik', name: 'Yarvik', x: 23, y: 51, owner: 'player', className: 'Red dwarf',
    population: 0.9, capacity: 4.4, happiness: 69, security: 42, development: 24, defenses: 190,
    resources: baseResources(34, 21, 62, 15), production: [], fleets: [],
    description: 'Young colony with broad terraforming potential and limited defenses.'
  },
  {
    id: 'vorus', name: 'Vorus', x: 15, y: 14, owner: 'neutral', className: 'Binary system',
    population: 0, capacity: 5.8, happiness: 0, security: 0, development: 0, defenses: 0,
    resources: baseResources(42, 67, 29, 55), production: [], fleets: [],
    description: 'Surveyed binary system. No permanent settlement detected.'
  },
  {
    id: 'zorvan', name: 'Zorvan', x: 17, y: 34, owner: 'neutral', className: 'Blue giant',
    population: 0, capacity: 2.7, happiness: 0, security: 0, development: 0, defenses: 0,
    resources: baseResources(83, 31, 8, 71), production: [], fleets: [],
    description: 'Mineral-rich but environmentally hostile system.'
  },
  {
    id: 'vaskor', name: 'Vaskor', x: 49, y: 20, owner: 'crimson', className: 'Red giant',
    population: 4.2, capacity: 7.1, happiness: 61, security: 92, development: 57, defenses: 2240,
    resources: baseResources(89, 46, 31, 44), production: [], fleets: [],
    description: 'Crimson League stronghold. Long-range scans show extensive military traffic.'
  },
  {
    id: 'drellon', name: 'Drellon', x: 58, y: 11, owner: 'crimson', className: 'Orange star',
    population: 2.6, capacity: 5.6, happiness: 58, security: 79, development: 45, defenses: 1320,
    resources: baseResources(67, 38, 41, 51), production: [], fleets: [],
    description: 'Industrial Crimson League colony on the northern rim.'
  },
  {
    id: 'elysia', name: 'Elysia', x: 68, y: 43, owner: 'neutral', className: 'White star',
    population: 0, capacity: 8.4, happiness: 0, security: 0, development: 0, defenses: 0,
    resources: baseResources(39, 79, 77, 26), production: [], fleets: [],
    description: 'A highly habitable unclaimed system between competing borders.'
  },
  {
    id: 'hydrus', name: 'Hydrus', x: 70, y: 28, owner: 'violet', className: 'Violet variable',
    population: 3.9, capacity: 6.7, happiness: 73, security: 71, development: 53, defenses: 1180,
    resources: baseResources(48, 92, 36, 63), production: [], fleets: [],
    description: 'Research enclave controlled by the Mael Covenant.'
  },
  {
    id: 'maeltir', name: 'Maeltir', x: 83, y: 25, owner: 'violet', className: 'Purple giant',
    population: 5.1, capacity: 8.9, happiness: 79, security: 88, development: 66, defenses: 2410,
    resources: baseResources(58, 84, 52, 69), production: [], fleets: [],
    description: 'Covenant capital and center of a dense psionic research network.'
  },
  {
    id: 'qorin', name: 'Qorin', x: 91, y: 55, owner: 'violet', className: 'Red dwarf',
    population: 1.5, capacity: 3.8, happiness: 67, security: 62, development: 32, defenses: 640,
    resources: baseResources(42, 55, 39, 28), production: [], fleets: [],
    description: 'Remote Covenant outpost near the eastern dark.'
  },
  {
    id: 'nexora', name: 'Nexora', x: 24, y: 77, owner: 'amber', className: 'Yellow giant',
    population: 2.1, capacity: 5.5, happiness: 56, security: 68, development: 43, defenses: 780,
    resources: baseResources(72, 28, 64, 34), production: [], fleets: [],
    description: 'Amber Combine trading colony surrounded by rich asteroid belts.'
  },
  {
    id: 'mirzan', name: 'Mirzan', x: 50, y: 82, owner: 'neutral', className: 'Violet dwarf',
    population: 0, capacity: 4.6, happiness: 0, security: 0, development: 0, defenses: 0,
    resources: baseResources(37, 62, 43, 47), production: [], fleets: [],
    description: 'Unclaimed system at the edge of Nova Dominion sensor range.'
  },
  {
    id: 'triune', name: 'Triune', x: 68, y: 91, owner: 'neutral', className: 'Triple system',
    population: 0, capacity: 6.3, happiness: 0, security: 0, development: 0, defenses: 0,
    resources: baseResources(61, 72, 22, 82), production: [], fleets: [],
    description: 'A rare triple-star system with unstable but powerful energy signatures.'
  },
  {
    id: 'velnor', name: 'Velnor', x: 76, y: 79, owner: 'violet', className: 'Blue star',
    population: 1.8, capacity: 4.3, happiness: 71, security: 64, development: 37, defenses: 690,
    resources: baseResources(44, 73, 35, 59), production: [], fleets: [],
    description: 'Covenant border colony overlooking the southern passage.'
  }
];

export const routes: RouteLink[] = [
  { from: 'vorus', to: 'kaltos' }, { from: 'kaltos', to: 'zorvan' }, { from: 'kaltos', to: 'lumen' },
  { from: 'zorvan', to: 'yarvik' }, { from: 'yarvik', to: 'estara' }, { from: 'lumen', to: 'aurelia' },
  { from: 'lumen', to: 'estara' }, { from: 'aurelia', to: 'estara' }, { from: 'aurelia', to: 'elysia' },
  { from: 'estara', to: 'mirzan' }, { from: 'mirzan', to: 'triune' }, { from: 'triune', to: 'velnor' },
  { from: 'elysia', to: 'hydrus' }, { from: 'hydrus', to: 'maeltir' }, { from: 'maeltir', to: 'qorin' },
  { from: 'hydrus', to: 'vaskor', kind: 'hostile' }, { from: 'vaskor', to: 'drellon', kind: 'hostile' },
  { from: 'nexora', to: 'mirzan' }, { from: 'nexora', to: 'estara' }
];

export const allFleets = fleets;

export const researchFields = [
  { id: 'propulsion', name: 'Propulsion', level: 7, progress: 64, bonus: 'Warp efficiency +8%' },
  { id: 'energy', name: 'Energy', level: 6, progress: 38, bonus: 'Shield capacity +6%' },
  { id: 'weapons', name: 'Weapons', level: 5, progress: 81, bonus: 'Beam damage +5%' },
  { id: 'construction', name: 'Construction', level: 7, progress: 22, bonus: 'Hull mass −4%' },
  { id: 'biotech', name: 'Biotechnology', level: 4, progress: 57, bonus: 'Growth rate +3%' },
  { id: 'electronics', name: 'Electronics', level: 6, progress: 46, bonus: 'Scanner range +7%' }
];

export const factions = [
  { id: 'nova', name: 'Nova Dominion', relation: 'Your empire', score: 100, color: 'cyan' },
  { id: 'crimson', name: 'Crimson League', relation: 'Hostile', score: -72, color: 'red' },
  { id: 'mael', name: 'Mael Covenant', relation: 'Cold peace', score: -14, color: 'violet' },
  { id: 'amber', name: 'Amber Combine', relation: 'Trade partner', score: 48, color: 'amber' }
];

export const turnEvents = [
  { time: 'Turn start', title: 'Year 2347 begins', text: 'Empire production and research income has been credited.', tone: 'info' },
  { time: 'Movement', title: 'Pathfinder Wing reached Estara', text: 'Long-range route to Mirzan is now available.', tone: 'good' },
  { time: 'Intelligence', title: 'Crimson activity near Vaskor', text: 'Three unidentified fleet signatures were detected.', tone: 'warning' },
  { time: 'Production', title: 'Hydroponic Farms II nearing completion', text: 'Aurelia production queue is 83% complete.', tone: 'good' },
  { time: 'Diplomacy', title: 'Amber trade shipment received', text: 'Industry reserves increased by 140 units.', tone: 'info' }
];
