<?php

declare(strict_types=1);

namespace Bellcom\StarsTurnBundle\Command;

use Bellcom\StarsTurnBundle\Service\GameInvitationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'stars:invitation:reconcile',
    description: 'Creates missing game invitations and sends pending invitation emails.',
)]
final class ReconcileGameInvitationsCommand extends Command
{
    public function __construct(private readonly GameInvitationService $invitations)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->invitations->reconcile();
        $output->writeln(sprintf(
            'Game invitations: created=%d emailed=%d failed=%d pending=%d',
            $result['created'],
            $result['emailed'],
            $result['failed'],
            $result['pending'],
        ));

        return Command::SUCCESS;
    }
}
