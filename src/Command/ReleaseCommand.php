<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Command;

use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ConfigurationLoader;
use Stolt\ComposerPeel\Model\ReleaseManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'release', description: 'Execute the complete Git release workflow with a peeled composer.json')]
class ReleaseCommand extends Command
{
    private ?ReleaseManager $releaseManager;

    public function __construct(?ReleaseManager $releaseManager = null)
    {
        $this->releaseManager = $releaseManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tag', InputArgument::REQUIRED, 'The Git tag to create for this release');

        $this->addOption('backup-file', null, InputOption::VALUE_REQUIRED, 'Name of the composer.json backup file');

        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the configuration file');

        $this->addOption(
            'commit-message',
            null,
            InputOption::VALUE_REQUIRED,
            'Commit message for the peeled composer.json, overwriting the default or configured one',
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Preview the release operation without modifying Git',
        );
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
            if (trim($commitMessage) === '') {
                $output->writeln('<error>The --commit-message option requires a non-empty message.</error>');
                return Command::FAILURE;
            }

            $configuration->setBeforeTagCommitMessage($commitMessage);
        }

        $tag = $input->getArgument('tag');
        $isDryRun = $input->getOption('dry-run') === true;

        if (is_string($tag)) {
            if ($this->releaseManager === null) {
                $this->releaseManager = new ReleaseManager($configuration);
            }

            try {
                $result = $this->releaseManager->release($tag, $isDryRun);

                if ($isDryRun && $result !== null) {
                    $output->writeln('<info>Files to be committed:</info>');
                    foreach ($result->getFilesToCommit() as $file) {
                        $output->writeln('+ ' . $file);
                    }
                    $output->writeln('');
                    $output->writeln('<info>Commit:</info> ' . $result->getCommitMessage());
                    $output->writeln('<info>Tag:</info> ' . $result->getTag());

                    return Command::SUCCESS;
                }

                $output->writeln("Release workflow completed successfully for tag: {$tag}.");
                return Command::SUCCESS;
            } catch (RuntimeException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        return Command::FAILURE;
    }
}
