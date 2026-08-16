<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

final class StartUniverseGenerator
{
    /**
     * @param list<array{id:int,name:string}> $players
     * @return array<string, mixed>
     */
    public function generate(array $players, string $seed): array
    {
        if (count($players) < 2) {
            throw new \InvalidArgumentException('Et startunivers kræver mindst to spillere.');
        }

        $names = $this->orderedNames($seed);
        $systems = [];
        $routes = [];
        $fleets = [];
        $playerCount = count($players);
        $homeRadius = 34.0;
        $centerX = 50.0;
        $centerY = 50.0;

        foreach ($players as $index => $player) {
            $angle = -M_PI / 2 + (2 * M_PI * $index / $playerCount);
            $systemId = 'home-'.$player['id'];
            $name = array_shift($names) ?? 'Home '.$player['id'];
            $x = round($centerX + cos($angle) * $homeRadius, 1);
            $y = round($centerY + sin($angle) * $homeRadius, 1);

            $systems[] = $this->system(
                id: $systemId,
                name: $name,
                x: $x,
                y: $y,
                seed: $seed,
                ownerPlayerId: $player['id'],
                isCapital: true,
                description: sprintf('%s home system and starting capital.', $player['name']),
            );

            $fleets[] = [
                'id' => 'fleet-'.$player['id'].'-1',
                'ownerPlayerId' => $player['id'],
                'systemId' => $systemId,
                'name' => $player['name'].' 1st Expeditionary Fleet',
                'ships' => 120,
                'role' => 'Exploration fleet',
            ];
        }

        $neutralCount = max(4, $playerCount * 2);
        $neutralIds = [];
        $neutralRadius = 18.0;
        for ($i = 0; $i < $neutralCount; ++$i) {
            $angle = -M_PI / 2 + M_PI / max(3, $neutralCount) + (2 * M_PI * $i / $neutralCount);
            $systemId = 'neutral-'.($i + 1);
            $neutralIds[] = $systemId;
            $name = array_shift($names) ?? 'Frontier '.($i + 1);
            $radiusJitter = $this->int($seed, 'radius-'.$i, -3, 4);
            $radius = $neutralRadius + $radiusJitter;
            $x = round($centerX + cos($angle) * $radius, 1);
            $y = round($centerY + sin($angle) * $radius, 1);

            $systems[] = $this->system(
                id: $systemId,
                name: $name,
                x: $x,
                y: $y,
                seed: $seed,
                ownerPlayerId: null,
                isCapital: false,
                description: 'Unclaimed frontier system available for exploration and future colonization.',
            );
        }

        for ($i = 0; $i < $neutralCount; ++$i) {
            $this->addRoute($routes, $neutralIds[$i], $neutralIds[($i + 1) % $neutralCount]);
        }

        foreach ($players as $player) {
            $homeId = 'home-'.$player['id'];
            $home = $this->findSystem($systems, $homeId);
            $nearest = $neutralIds;
            usort($nearest, function (string $left, string $right) use ($systems, $home): int {
                $a = $this->findSystem($systems, $left);
                $b = $this->findSystem($systems, $right);
                return $this->distance($home, $a) <=> $this->distance($home, $b);
            });

            foreach (array_slice($nearest, 0, min(2, count($nearest))) as $neutralId) {
                $this->addRoute($routes, $homeId, $neutralId);
            }
        }

        return [
            'year' => 2400,
            'universe' => [
                'systems' => $systems,
                'routes' => array_values($routes),
                'planets' => [],
                'fleets' => $fleets,
            ],
        ];
    }

    /** @return list<string> */
    private function orderedNames(string $seed): array
    {
        $names = [
            'Aurelia', 'Bellatrix', 'Caelum', 'Denebola', 'Erebus', 'Fomalhaut', 'Galen', 'Helion',
            'Ilyra', 'Juno', 'Kestrel', 'Lumen', 'Meridian', 'Nereid', 'Orion', 'Praxus', 'Qorin',
            'Rigel', 'Sirius', 'Talos', 'Umbra', 'Vega', 'Warden', 'Xanthe', 'Yarvik', 'Zenith',
            'Altair', 'Cygnus', 'Draconis', 'Estara', 'Hydrus', 'Mirzan', 'Nexora', 'Triune',
        ];

        usort($names, static fn (string $a, string $b): int => strcmp(
            hash('sha256', $seed.':name:'.$a),
            hash('sha256', $seed.':name:'.$b),
        ));

        return $names;
    }

    /** @return array<string, mixed> */
    private function system(
        string $id,
        string $name,
        float $x,
        float $y,
        string $seed,
        ?int $ownerPlayerId,
        bool $isCapital,
        string $description,
    ): array {
        $home = $ownerPlayerId !== null;

        return [
            'id' => $id,
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'ownerPlayerId' => $ownerPlayerId,
            'className' => $this->starClass($seed, $id),
            'population' => $home ? 6.0 : 0.0,
            'capacity' => $home ? 10.0 : $this->int($seed, $id.':capacity', 4, 9) + 0.5,
            'happiness' => $home ? 80 : 0,
            'security' => $home ? 70 : 0,
            'development' => $home ? 55 : 0,
            'defenses' => $home ? 500 : 0,
            'resources' => $this->resources($seed, $id, $home),
            'production' => [],
            'description' => $description,
            'isCapital' => $isCapital,
        ];
    }

    /** @return list<array{id:string,label:string,value:int,income:int,icon:string}> */
    private function resources(string $seed, string $systemId, bool $home): array
    {
        $floor = $home ? 45 : 20;
        $ceiling = $home ? 70 : 65;
        $definitions = [
            ['industry', 'Industry', 'industry'],
            ['science', 'Science', 'research'],
            ['bio', 'Biomass', 'planet'],
            ['energy', 'Energy', 'energy'],
        ];

        $rows = [];
        foreach ($definitions as [$id, $label, $icon]) {
            $income = $this->int($seed, $systemId.':resource:'.$id, $floor, $ceiling);
            $rows[] = [
                'id' => $id,
                'label' => $label,
                'value' => $income * 20,
                'income' => $income,
                'icon' => $icon,
            ];
        }

        return $rows;
    }

    private function starClass(string $seed, string $systemId): string
    {
        $classes = ['Yellow star', 'Orange star', 'Red dwarf', 'Blue-white star', 'White star', 'Binary system'];
        return $classes[$this->int($seed, $systemId.':class', 0, count($classes) - 1)];
    }

    private function int(string $seed, string $key, int $min, int $max): int
    {
        $range = $max - $min + 1;
        $value = hexdec(substr(hash('sha256', $seed.':'.$key), 0, 8));
        return $min + ($value % $range);
    }

    /**
     * @param list<array<string, mixed>> $systems
     * @return array<string, mixed>
     */
    private function findSystem(array $systems, string $id): array
    {
        foreach ($systems as $system) {
            if (($system['id'] ?? null) === $id) {
                return $system;
            }
        }

        throw new \LogicException('System not found: '.$id);
    }

    /** @param array<string, mixed> $a @param array<string, mixed> $b */
    private function distance(array $a, array $b): float
    {
        $dx = (float) $a['x'] - (float) $b['x'];
        $dy = (float) $a['y'] - (float) $b['y'];
        return sqrt($dx * $dx + $dy * $dy);
    }

    /** @param array<string, array{from:string,to:string,kind:string}> $routes */
    private function addRoute(array &$routes, string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $pair = [$from, $to];
        sort($pair, SORT_STRING);
        $key = implode('|', $pair);
        $routes[$key] = ['from' => $pair[0], 'to' => $pair[1], 'kind' => 'known'];
    }
}
