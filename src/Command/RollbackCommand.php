<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Command;

use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ConfigurationLoader;
use Stolt\ComposerPeel\Model\ReleaseManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rollback', description: 'Restores the composer.json manifest from a backup file')]
class RollbackCommand extends Command
{
    private ?ReleaseManager $releaseManager;

    public function __construct(?ReleaseManager $releaseManager = null)
    {
        $this->releaseManager = $releaseManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'keep-backup',
            null,
            InputOption::VALUE_NONE,
            'Do not delete the backup file after restoration',
        );

        $this->addOption(
            'commit',
            null,
            InputOption::VALUE_NONE,
            'Commit the restored composer.json to Git',
        );

        $this->addOption('backup-file', null, InputOption::VALUE_REQUIRED, 'Name of the composer.json backup file');
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configOption = $input->getOption('config');
        $configPath = is_string($configOption) ? $configOption : getcwd() . '/.composer-peel.php';

        $configuration = new Configuration();

        if (file_exists((string) $configPath)) {
            $loader = new ConfigurationLoader();
            $configuration = $loader->load((string) $configPath);
        }

        $backupFile = $input->getOption('backup-file');
        if (is_string($backupFile)) {
            $configuration->setBackupPath($backupFile);
        }

        $backupPath = $configuration->getBackupPath();

        if (!file_exists($backupPath)) {
            $output->writeln("<error>Backup file {$backupPath} does not exist.</error>");
            return Command::FAILURE;
        }

        $backupContent = file_get_contents($backupPath);
        if ($backupContent === false) {
            $output->writeln("<error>Failed to read backup file {$backupPath}.</error>");
            return Command::FAILURE;
        }

        $backupManifest = json_decode($backupContent, associative: true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln("<error>Backup file {$backupPath} does not contain valid JSON.</error>");
            return Command::FAILURE;
        }

        $manifestPath = getcwd() . '/composer.json';
        if (file_exists($manifestPath)) {
            $currentManifest = json_decode((string) file_get_contents($manifestPath), associative: true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $peelSections = $configuration->getPeelSections();
                $isPeeled = false;
                foreach ($peelSections as $section) {
                    if (!array_key_exists($section, $backupManifest) || array_key_exists($section, $currentManifest)) {
                        continue;
                    }

                    $isPeeled = true;
                    break;
                }

                if ($isPeeled) {
                    // Verification successful
                }
            }
        }

        $output->writeln('Restoring composer.json from ' . basename($backupPath));

        if (file_put_contents($manifestPath, $backupContent) === false) {
            $output->writeln("<error>Failed to restore {$manifestPath} from backup.</error>");
            return Command::FAILURE;
        }

        $output->writeln('composer.json restored successfully.');

        if ($input->getOption('commit')) {
            if ($this->releaseManager === null) {
                $this->releaseManager = new ReleaseManager($configuration);
            }

            try {
                $this->releaseManager->commitRestoredManifest();
            } catch (RuntimeException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            $output->writeln('Restored composer.json committed successfully.');
        }

        if (!$input->getOption('keep-backup')) {
            unlink($backupPath);
            $output->writeln('Backup removed successfully.');

            return Command::SUCCESS;
        }

        $output->writeln('Backup kept.');

        return Command::SUCCESS;
    }
}
