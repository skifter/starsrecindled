<?php

declare(strict_types=1);

use Bellcom\StarsTurnBundle\Notification\TurnNotificationFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Bellcom\\StarsTurnBundle\\', '../src/')
        ->exclude([
            '../src/Entity/',
            '../src/Enum/',
            '../src/StarsTurnBundle.php',
        ]);

    $services->set(TurnNotificationFactory::class)
        ->arg('$mailerFrom', '%stars_turn.mailer_from%')
        ->arg('$frontendBaseUrl', '%stars_turn.frontend_base_url%');
};
