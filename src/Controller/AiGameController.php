<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stars/api/account/games/ai', name: 'stars_account_create_ai_game', methods: ['POST'])]
final class AiGameController extends AbstractController
{
    public function __construct(private readonly AccountController $accountController)
    {
    }

    public function __invoke(Request $request, KernelInterface $kernel): JsonResponse
    {
        $profileResponse = $this->accountController->me($request);
        if ($profileResponse->getStatusCode() !== Response::HTTP_OK) {
            return $profileResponse;
        }

        try {
            /** @var array<string, mixed> $profile */
            $profile = json_decode((string) $profileResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Kunne ikke læse den aktive konto.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (($profile['authMode'] ?? null) !== 'web') {
            return $this->json(['error' => 'AI-testspil kan kun oprettes fra en web-login session.'], Response::HTTP_FORBIDDEN);
        }

        $expectedCsrf = (string) ($profile['csrfToken'] ?? '');
        $providedCsrf = (string) $request->headers->get('X-Stars-CSRF', '');
        if ($expectedCsrf === '' || $providedCsrf === '' || !hash_equals($expectedCsrf, $providedCsrf)) {
            return $this->json(['error' => 'Ugyldigt CSRF-token.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'Ugyldig JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $aiPlayers = filter_var($payload['aiPlayers'] ?? null, FILTER_VALIDATE_INT);
        $aiLevel = strtolower(trim((string) ($payload['aiLevel'] ?? 'standard')));

        if ($name === '' || mb_strlen($name) > 190) {
            return $this->json(['error' => 'Spilnavnet skal være mellem 1 og 190 tegn.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($aiPlayers === false || $aiPlayers < 1 || $aiPlayers > 3) {
            return $this->json(['error' => 'Vælg mellem 1 og 3 AI-spillere.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($aiLevel !== 'standard') {
            return $this->json(['error' => 'Kun AI-niveauet Standard findes endnu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $account = $profile['account'] ?? null;
        if (!is_array($account)) {
            return $this->json(['error' => 'Den aktive konto mangler profildata.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $displayName = trim((string) ($account['displayName'] ?? ''));
        $email = trim((string) ($account['email'] ?? ''));
        if ($displayName === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->json(['error' => 'Den aktive konto mangler gyldigt navn eller e-mail.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $input = new ArrayInput([
            'command' => 'stars:game:create',
            'name' => $name,
            '--player' => [sprintf('%s <%s>', $displayName, $email)],
            '--ai' => (string) $aiPlayers,
            '--ai-level' => $aiLevel,
            '--no-interaction' => true,
        ]);
        $output = new BufferedOutput();

        try {
            $exitCode = $application->run($input, $output);
        } catch (\Throwable $exception) {
            return $this->json([
                'error' => sprintf('AI-testspillet kunne ikke oprettes: %s', $exception->getMessage()),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($exitCode !== 0) {
            $message = trim($output->fetch());
            return $this->json([
                'error' => $message !== '' ? $message : 'AI-testspillet kunne ikke oprettes.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $refreshedResponse = $this->accountController->me($request);
        if ($refreshedResponse->getStatusCode() !== Response::HTTP_OK) {
            return $refreshedResponse;
        }

        try {
            /** @var array<string, mixed> $refreshed */
            $refreshed = json_decode((string) $refreshedResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'Spillet blev oprettet, men lobbyen kunne ikke genindlæses.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $refreshed['notice'] = sprintf(
            'Game “%s” created with %d Standard AI player%s.',
            $name,
            $aiPlayers,
            $aiPlayers === 1 ? '' : 's',
        );

        return $this->json($refreshed, Response::HTTP_CREATED);
    }
}
