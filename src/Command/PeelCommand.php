<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Command;

use RuntimeException;
use Stolt\ComposerPeel\Model\ComposerPeeler;
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
    name: "peel",
    description: "Strips development-only metadata from the Composer manifest when preparing a PHP package for distribution",
)]
class PeelCommand extends Command
{
    private ComposerPeeler $composerPeeler;
    private ?ReleaseManager $releaseManager;

    public function __construct(?ComposerPeeler $composerPeeler = null, ?ReleaseManager $releaseManager = null)
    {
        $this->composerPeeler = $composerPeeler ?? new ComposerPeeler();
        $this->releaseManager = $releaseManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            "tag",
            InputArgument::OPTIONAL,
            "The Git tag to create for this release"
        );

        $this->addOption("backup-file", null, InputOption::VALUE_REQUIRED, "Name of the composer.json backup file");

        $this->addOption("config", null, InputOption::VALUE_REQUIRED, "Path to the configuration file");

        $this->addOption(
            "dry-run",
            null,
            InputOption::VALUE_NONE,
            "Simulate the metadata peeling without modifying composer.json",
        );

        $this->addOption(
            "format",
            null,
            InputOption::VALUE_REQUIRED,
            "Output format for the dry run (text or json)",
            "text"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configOption = $input->getOption("config");
        $configPath = is_string($configOption) ? $configOption : getcwd() . "/.composer-peel.php";
        
        $configUsed = 'internal defaults';

        $configuration = new Configuration();

        if (file_exists((string) $configPath)) {
            $loader = new ConfigurationLoader();
            try {
                $configuration = $loader->load((string) $configPath);
                $configUsed = basename((string) $configPath);
            } catch (RuntimeException $e) {
                $output->writeln("<error>" . $e->getMessage() . "</error>");
                return Command::FAILURE;
            }
        }
        
        if (!file_exists((string) $configPath) && is_string($configOption)) {
            $configUsed = basename($configOption);
        }

        $backupFile = $input->getOption("backup-file");
        if (is_string($backupFile)) {
            $configuration->setBackupPath($backupFile);
        }

        $this->composerPeeler->setConfiguration($configuration);

        $dryRun = (bool) $input->getOption("dry-run");
        $tag = $input->getArgument("tag");

        if (is_string($tag)) {
            if ($dryRun) {
                $output->writeln("<error>The --dry-run option cannot be used when executing the release workflow.</error>");
                return Command::FAILURE;
            }
            return $this->handleReleaseWorkflow($tag, $configuration, $output);
        }

        if ($dryRun) {
            $format = $input->getOption('format');
            if (!is_string($format) || !in_array($format, ['text', 'json'], true)) {
                $output->writeln("<error>Invalid format specified. Allowed values are 'text' or 'json'.</error>");
                return Command::FAILURE;
            }
            return $this->printDryRunReport($configUsed, $format, $output);
        }

        return $this->handlePeelWorkflow($output);
    }

    private function handleReleaseWorkflow(string $tag, Configuration $configuration, OutputInterface $output): int
    {
        if ($this->releaseManager === null) {
            $this->releaseManager = new ReleaseManager($this->composerPeeler, $configuration);
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

    private function handlePeelWorkflow(OutputInterface $output): int
    {
        try {
            $this->composerPeeler->peel();
            $output->writeln("Metadata peeled successfully.");
            return Command::SUCCESS;
        } catch (RuntimeException $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }

    private function printDryRunReport(string $configUsed, string $format, OutputInterface $output): int
    {
        $result = $this->composerPeeler->simulatePeel();

        $originalSize = $result->getOriginalSize();
        $projectedSize = $result->getProjectedSize();
        $reduction = $originalSize - $projectedSize;
        $percentage = $originalSize > 0 ? ($reduction / $originalSize) * 100 : 0;

        if ($format === 'json') {
            $output->writeln((string) json_encode([
                'manifest' => 'composer.json',
                'configuration' => $configUsed,
                'removed_sections' => $result->getRemovedSections(),
                'original_size_bytes' => $originalSize,
                'projected_size_bytes' => $projectedSize,
                'reduction_bytes' => $reduction,
                'reduction_percentage' => round($percentage, 1),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln("Manifest:      composer.json");
        $output->writeln("Configuration: {$configUsed}");
        $output->writeln('');

        $removed = $result->getRemovedSections();
        if (count($removed) > 0) {
            $output->writeln("Sections to be removed:");
            foreach ($removed as $section) {
                $output->writeln("  - {$section}");
            }
        }

        $output->writeln('');
        
        $output->writeln(sprintf("Original size:       %s bytes", number_format($originalSize)));
        $output->writeln(sprintf("Projected size:      %s bytes", number_format($projectedSize)));
        $output->writeln(sprintf("Estimated reduction: %s bytes (%.1f%%)", number_format($reduction), $percentage));
        $output->writeln('');
        $output->writeln("Dry run completed. No files were modified.");
        
        return Command::SUCCESS;
    }
}
