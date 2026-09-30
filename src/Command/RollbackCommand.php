<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Command;

use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ConfigurationLoader;
use Stolt\ComposerPeel\Model\ManifestComparator;
use Stolt\ComposerPeel\Model\ReleaseManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rollback', description: 'Restore the composer.json manifest from a backup file')]
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

        $this->addOption('commit', null, InputOption::VALUE_NONE, 'Commit the restored composer.json to Git');

        $this->addOption(
            'commit-message',
            null,
            InputOption::VALUE_REQUIRED,
            'Commit message for the restored composer.json, overwriting the default or configured one',
        );

        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Restore the backup even if composer.json has been modified since it was peeled',
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
            try {
                $configuration = $loader->load((string) $configPath);
            } catch (RuntimeException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        $backupFile = $input->getOption('backup-file');
        if (is_string($backupFile)) {
            $configuration->setBackupPath($backupFile);
        }

        $commitMessage = $input->getOption('commit-message');
        if (is_string($commitMessage)) {
            if (!$input->getOption('commit')) {
                $output->writeln('<error>The --commit-message option requires the --commit option.</error>');
                return Command::FAILURE;
            }

            if (trim($commitMessage) === '') {
                $output->writeln('<error>The --commit-message option requires a non-empty message.</error>');
                return Command::FAILURE;
            }

            $configuration->setAfterTagCommitMessage($commitMessage);
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

        $backupManifest = $this->decodeManifest($backupContent);
        if ($backupManifest === null) {
            $output->writeln("<error>Backup file {$backupPath} does not contain valid JSON.</error>");
            return Command::FAILURE;
        }

        $manifestPath = getcwd() . '/composer.json';
        $manifestState = $this->determineManifestState($manifestPath, $backupManifest, $configuration);

        if ($manifestState === ManifestComparator::MODIFIED && !$input->getOption('force')) {
            $output->writeln(
                '<error>composer.json has been modified since it was peeled. Restoring the backup would discard '
                . 'these changes. Use --force to restore it anyway.</error>',
            );
            return Command::FAILURE;
        }

        if ($manifestState === ManifestComparator::RESTORED) {
            $output->writeln('composer.json already matches ' . basename($backupPath) . '.');
        } else {
            $output->writeln('Restoring composer.json from ' . basename($backupPath));

            if (file_put_contents($manifestPath, $backupContent) === false) {
                $output->writeln("<error>Failed to restore {$manifestPath} from backup.</error>");
                return Command::FAILURE;
            }

            $output->writeln('composer.json restored successfully.');
        }

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

    /**
     * @param array<string, mixed> $backupManifest
     */
    private function determineManifestState(
        string $manifestPath,
        array $backupManifest,
        Configuration $configuration,
    ): string {
        if (!file_exists($manifestPath)) {
            return ManifestComparator::PEELED;
        }

        $manifest = $this->decodeManifest((string) file_get_contents($manifestPath));
        if ($manifest === null) {
            return ManifestComparator::MODIFIED;
        }

        return (new ManifestComparator($configuration))->compare($manifest, $backupManifest);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeManifest(string $content): ?array
    {
        $manifest = json_decode($content, associative: true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifest)) {
            return null;
        }

        /** @var array<string, mixed> $manifest */
        return $manifest;
    }
}
