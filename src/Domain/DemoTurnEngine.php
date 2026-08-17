<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Domain;

use Bellcom\StarsTurnBundle\Entity\Turn;

final class DemoTurnEngine implements TurnEngineInterface
{
    public function generate(Turn $turn, array $submittedOrders): TurnGenerationResult
    {
        $state = $turn->getInitialState();
        $currentYear = (int) ($state['year'] ?? 2400);

        ksort($submittedOrders, SORT_STRING);

        $nextState = $state;
        $nextState['year'] = $currentYear + 1;
        $nextState['last_turn'] = $turn->getNumber();
        $nextState['rules_version'] = $turn->getRulesVersion();
        $nextState['seed'] = $turn->getRandomSeed();
        $nextState['submitted_orders'] = $submittedOrders;

        $systems = is_array($nextState['universe']['systems'] ?? null)
            ? $nextState['universe']['systems']
            : [];
        $routes = is_array($nextState['universe']['routes'] ?? null)
            ? $nextState['universe']['routes']
            : [];
        $fleets = is_array($nextState['universe']['fleets'] ?? null)
            ? $nextState['universe']['fleets']
            : [];

        $systemIds = [];
        foreach ($systems as $index => $system) {
            if (is_array($system) && is_string($system['id'] ?? null)) {
                $systemIds[(string) $system['id']] = $index;
            }
        }

        $reports = [];
        foreach ($submittedOrders as $playerId => $orders) {
            $movements = [];
            $colonizations = [];
            $warnings = [];
            $movedFleetIds = [];
            $fleetOrders = is_array($orders['fleets'] ?? null) ? $orders['fleets'] : [];

            // Movement is resolved before colonization. A fleet that moved this turn
            // cannot colonize until the following turn.
            foreach ($fleetOrders as $fleetOrder) {
                if (!is_array($fleetOrder) || ($fleetOrder['action'] ?? null) !== 'move') {
                    continue;
                }

                $fleetId = is_string($fleetOrder['fleetId'] ?? null) ? $fleetOrder['fleetId'] : '';
                $targetSystemId = is_string($fleetOrder['targetSystemId'] ?? null) ? $fleetOrder['targetSystemId'] : '';
                if ($fleetId === '' || $targetSystemId === '') {
                    $warnings[] = 'En fleet move-ordre manglede fleetId eller targetSystemId.';
                    continue;
                }

                $fleetIndex = $this->findFleetIndex($fleets, $fleetId);
                if ($fleetIndex === null) {
                    $warnings[] = sprintf('Flåden %s findes ikke.', $fleetId);
                    continue;
                }

                $fleet = $fleets[$fleetIndex];
                if ((string) ($fleet['ownerPlayerId'] ?? '') !== (string) $playerId) {
                    $warnings[] = sprintf('Flåden %s tilhører ikke spiller %s.', $fleetId, $playerId);
                    continue;
                }
                if (!isset($systemIds[$targetSystemId])) {
                    $warnings[] = sprintf('Destinationssystemet %s findes ikke.', $targetSystemId);
                    continue;
                }

                $fromSystemId = is_string($fleet['systemId'] ?? null) ? $fleet['systemId'] : '';
                if ($fromSystemId === $targetSystemId) {
                    continue;
                }
                if (!$this->systemsAreConnected($routes, $fromSystemId, $targetSystemId)) {
                    $warnings[] = sprintf('Ingen kendt rute forbinder %s og %s.', $fromSystemId, $targetSystemId);
                    continue;
                }

                $fleets[$fleetIndex]['systemId'] = $targetSystemId;
                unset($fleets[$fleetIndex]['destinationSystemId']);
                $movedFleetIds[$fleetId] = true;
                $movements[] = [
                    'fleetId' => $fleetId,
                    'fromSystemId' => $fromSystemId,
                    'toSystemId' => $targetSystemId,
                ];
            }

            foreach ($fleetOrders as $fleetOrder) {
                if (!is_array($fleetOrder) || ($fleetOrder['action'] ?? null) !== 'colonize') {
                    continue;
                }

                $fleetId = is_string($fleetOrder['fleetId'] ?? null) ? $fleetOrder['fleetId'] : '';
                $targetSystemId = is_string($fleetOrder['targetSystemId'] ?? null) ? $fleetOrder['targetSystemId'] : '';
                if ($fleetId === '' || $targetSystemId === '') {
                    $warnings[] = 'En koloniseringsordre manglede fleetId eller targetSystemId.';
                    continue;
                }
                if (isset($movedFleetIds[$fleetId])) {
                    $warnings[] = sprintf('Flåden %s kan ikke både flytte og kolonisere i samme runde.', $fleetId);
                    continue;
                }

                $fleetIndex = $this->findFleetIndex($fleets, $fleetId);
                if ($fleetIndex === null) {
                    $warnings[] = sprintf('Flåden %s findes ikke.', $fleetId);
                    continue;
                }

                $fleet = $fleets[$fleetIndex];
                if ((string) ($fleet['ownerPlayerId'] ?? '') !== (string) $playerId) {
                    $warnings[] = sprintf('Flåden %s tilhører ikke spiller %s.', $fleetId, $playerId);
                    continue;
                }
                if (($fleet['systemId'] ?? null) !== $targetSystemId) {
                    $warnings[] = sprintf('Flåden %s befinder sig ikke i %s.', $fleetId, $targetSystemId);
                    continue;
                }

                $systemIndex = $systemIds[$targetSystemId] ?? null;
                if (!is_int($systemIndex) || !isset($systems[$systemIndex]) || !is_array($systems[$systemIndex])) {
                    $warnings[] = sprintf('Koloniseringssystemet %s findes ikke.', $targetSystemId);
                    continue;
                }
                if (($systems[$systemIndex]['ownerPlayerId'] ?? null) !== null) {
                    $warnings[] = sprintf('Systemet %s er allerede koloniseret.', $targetSystemId);
                    continue;
                }

                $capacity = $this->colonyCapacity($fleet);
                if ($capacity < 1) {
                    $warnings[] = sprintf('Flåden %s har intet ubrugt colony module.', $fleetId);
                    continue;
                }

                $systems[$systemIndex]['ownerPlayerId'] = (int) $playerId;
                $systems[$systemIndex]['population'] = max(0.25, (float) ($systems[$systemIndex]['population'] ?? 0.0));
                $systems[$systemIndex]['happiness'] = 70;
                $systems[$systemIndex]['security'] = 15;
                $systems[$systemIndex]['development'] = 8;
                $systems[$systemIndex]['defenses'] = max(0, (int) ($systems[$systemIndex]['defenses'] ?? 0));
                $systems[$systemIndex]['description'] = sprintf(
                    'New colony established in year %d by player %s.',
                    (int) $nextState['year'],
                    $playerId,
                );
                $systems[$systemIndex]['isCapital'] = false;

                $fleets[$fleetIndex]['colonizationCapacity'] = $capacity - 1;
                $colonizations[] = [
                    'fleetId' => $fleetId,
                    'systemId' => $targetSystemId,
                    'population' => $systems[$systemIndex]['population'],
                ];
            }

            $parts = [];
            if (count($movements) > 0) {
                $parts[] = sprintf('%d flådebevægelse(r)', count($movements));
            }
            if (count($colonizations) > 0) {
                $parts[] = sprintf('%d kolonisering(er)', count($colonizations));
            }

            $reports[$playerId] = [
                'message' => $parts === []
                    ? 'Ingen flådebevægelser eller koloniseringer blev udført i denne runde.'
                    : ucfirst(implode(' og ', $parts)).' blev udført.',
                'movements' => $movements,
                'colonizations' => $colonizations,
                'warnings' => $warnings,
                'orders' => $orders,
            ];
        }

        $nextState['universe']['systems'] = array_values($systems);
        $nextState['universe']['fleets'] = array_values($fleets);

        return new TurnGenerationResult($nextState, $reports);
    }

    /** @param list<mixed> $fleets */
    private function findFleetIndex(array $fleets, string $fleetId): ?int
    {
        foreach ($fleets as $index => $fleet) {
            if (is_array($fleet) && ($fleet['id'] ?? null) === $fleetId) {
                return $index;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $fleet */
    private function colonyCapacity(array $fleet): int
    {
        if (isset($fleet['colonizationCapacity']) && is_numeric($fleet['colonizationCapacity'])) {
            return max(0, (int) $fleet['colonizationCapacity']);
        }

        // Backwards compatibility for 0.5.1/0.5.3 test games.
        return ($fleet['role'] ?? null) === 'Exploration fleet' ? 1 : 0;
    }

    /** @param list<mixed> $routes */
    private function systemsAreConnected(array $routes, string $a, string $b): bool
    {
        foreach ($routes as $route) {
            if (!is_array($route)) {
                continue;
            }
            $from = $route['from'] ?? null;
            $to = $route['to'] ?? null;
            if (($from === $a && $to === $b) || ($from === $b && $to === $a)) {
                return true;
            }
        }

        return false;
    }
}
