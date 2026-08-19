<?php

namespace App\SuperAdmin\Presentation\Command;

use App\SuperAdmin\Application\CreateSuperAdmin;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'super-admin:create',
    description: 'Creates a super-admin account (public schema only) that can manage tenants.',
)]
final class CreateSuperAdminCommand extends Command
{
    public function __construct(private readonly CreateSuperAdmin $createSuperAdmin)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Super-admin login email.')
            ->addArgument('password', InputArgument::OPTIONAL, 'Super-admin password (prompted interactively if omitted).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = $input->getArgument('password');

        if ($password === null) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        }

        $password = (string) $password;

        if ($password === '') {
            $io->error('Password cannot be empty.');

            return Command::INVALID;
        }

        try {
            $superAdmin = ($this->createSuperAdmin)($email, $password);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Created super admin "%s".', $superAdmin->getEmail()));

        return Command::SUCCESS;
    }
}
