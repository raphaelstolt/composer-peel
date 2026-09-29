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

#[AsCommand(
    name: "release",
    description: "Executes the complete Git release workflow with a peeled manifest",
)]
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
        $this->addArgument(
            "tag",
            InputArgument::REQUIRED,
            "The Git tag to create for this release"
        );

        $this->addOption("backup-file", null, InputOption::VALUE_REQUIRED, "Name of the composer.json backup file");

        $this->addOption("config", null, InputOption::VALUE_REQUIRED, "Path to the configuration file");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configOption = $input->getOption("config");
        $configPath = is_string($configOption) ? $configOption : getcwd() . "/.composer-peel.php";

        $configuration = new Configuration();

        if (file_exists((string) $configPath)) {
            $loader = new ConfigurationLoader();
            try {
                $configuration = $loader->load((string) $configPath);
            } catch (RuntimeException $e) {
                $output->writeln("<error>" . $e->getMessage() . "</error>");
                return Command::FAILURE;
            }
        }

        $backupFile = $input->getOption("backup-file");
        if (is_string($backupFile)) {
            $configuration->setBackupPath($backupFile);
        }

        $tag = $input->getArgument("tag");

        if (is_string($tag)) {
            if ($this->releaseManager === null) {
                $this->releaseManager = new ReleaseManager($configuration);
            }
            
            try {
                $this->releaseManager->release($tag);
                $output->writeln("Release workflow completed successfully for tag: {$tag}.");
                return Command::SUCCESS;
            } catch (RuntimeException $e) {
                $output->writeln("<error>" . $e->getMessage() . "</error>");
                return Command::FAILURE;
            }
        }

        return Command::FAILURE;
    }
}
