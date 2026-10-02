<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Command;

use Stolt\ComposerPeel\Model\Configuration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'init', description: 'Initialise the internal configuration by writing it to .composer-peel.php')]
class InitCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite the existing configuration file if it exists',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = getcwd() . '/.composer-peel.php';

        if (file_exists($configPath) && !$input->getOption('overwrite')) {
            $output->writeln(
                '<error>Configuration file .composer-peel.php already exists. Use --overwrite to replace it.</error>',
            );
            return Command::FAILURE;
        }

        $configuration = new Configuration();

        $peelSections = implode("',\n            '", $configuration->getPeelSections());
        $files = implode("',\n            '", $configuration->getManagedFiles());
        $backupEnabled = $configuration->isBackupEnabled() ? 'true' : 'false';
        $backupPath = $configuration->getBackupPath();
        $releaseMessage = $configuration->getReleaseCommitMessage();
        $rollbackMessage = $configuration->getRollbackCommitMessage();

        $configContent = <<<PHP
            <?php

            declare(strict_types=1);

            return [
                'peel' => [
                    'sections' => [
                        '{$peelSections}',
                    ],
                ],
                'release' => [
                    'backup' => [
                        'enabled' => {$backupEnabled},
                        'path' => '{$backupPath}',
                    ],
                    'files' => [
                        '{$files}',
                    ],
                    'commit_message' => '{$releaseMessage}',
                ],
                'rollback' => [
                    'commit_message' => '{$rollbackMessage}',
                ],
            ];

            PHP;

        if (file_put_contents($configPath, $configContent) === false) {
            $output->writeln('<error>Failed to write configuration file .composer-peel.php.</error>');
            return Command::FAILURE;
        }

        $output->writeln('Default configuration written to .composer-peel.php successfully.');
        return Command::SUCCESS;
    }
}
