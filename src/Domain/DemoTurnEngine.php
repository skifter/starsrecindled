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
        foreach ($systems as $system) {
            if (is_array($system) && is_string($system['id'] ?? null)) {
                $systemIds[(string) $system['id']] = true;
            }
        }

        $reports = [];
        foreach ($submittedOrders as $playerId => $orders) {
            $movements = [];
            $warnings = [];
            $fleetOrders = is_array($orders['fleets'] ?? null) ? $orders['fleets'] : [];

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
                $movements[] = [
                    'fleetId' => $fleetId,
                    'fromSystemId' => $fromSystemId,
                    'toSystemId' => $targetSystemId,
                ];
            }

            $reports[$playerId] = [
                'message' => count($movements) > 0
                    ? sprintf('%d flådebevægelse(r) blev udført.', count($movements))
                    : 'Ingen flådebevægelser blev udført i denne runde.',
                'movements' => $movements,
                'warnings' => $warnings,
                'orders' => $orders,
            ];
        }

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
