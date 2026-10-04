<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Command;

use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ConfigurationLoader;
use Stolt\ComposerPeel\Model\StatusChecker;
use Stolt\ComposerPeel\Model\StatusResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'status', description: 'Display the current Composer Peel state and release readiness')]
class StatusCommand extends Command
{
    private ?StatusChecker $statusChecker;

    public function __construct(?StatusChecker $statusChecker = null)
    {
        $this->statusChecker = $statusChecker;
        parent::__construct();
    }

    protected function configure(): void
    {
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

        if ($this->statusChecker === null) {
            $this->statusChecker = new StatusChecker($configuration);
        }

        $result = $this->statusChecker->check();

        $this->renderStatus($output, $result);

        return $result->isReleaseReady() ? Command::SUCCESS : Command::FAILURE;
    }

    private function renderStatus(OutputInterface $output, StatusResult $result): void
    {
        $output->writeln('');
        $output->writeln('Composer peel status');
        $output->writeln('');

        $this->renderManifestAndBackup($output, $result);
        $this->renderPackageVersion($output, $result);
        $this->renderApplicationVersions($output, $result);
        $this->renderVersionConsistency($output, $result);
        $this->renderGitTag($output, $result);
        $this->renderChangelog($output, $result);
        $this->renderWorkingTree($output, $result);
        $this->renderReleaseState($output, $result);
        $this->renderIssues($output, $result);
        $this->renderWorkingTreeChanges($output, $result);
    }

    private function renderManifestAndBackup(OutputInterface $output, StatusResult $result): void
    {
        $output->writeln(sprintf('%-20s %s', 'Composer manifest', $result->manifestState));

        $backupLabel = $result->backupExists ? 'available' : 'not available';
        $output->writeln(sprintf('%-20s %s', 'Backup', $backupLabel));
        $output->writeln('');
    }

    private function renderPackageVersion(OutputInterface $output, StatusResult $result): void
    {
        if ($result->packageVersion === null) {
            return;
        }

        $output->writeln(sprintf('%-20s %s', 'Package version', $result->packageVersion));
        $output->writeln('');
    }

    private function renderApplicationVersions(OutputInterface $output, StatusResult $result): void
    {
        if (count($result->applicationVersionSources) === 0) {
            return;
        }

        $output->writeln('Application versions');
        foreach ($result->applicationVersionSources as $source) {
            $output->writeln(sprintf('  %-30s %s', $source->path, $source->version));
        }
        $output->writeln('');
    }

    private function renderVersionConsistency(OutputInterface $output, StatusResult $result): void
    {
        $consistencyLabel = $this->formatConsistency($result->versionConsistency);

        if (count($result->applicationVersionSources) > 0) {
            $appVersionLabel = $result->canonicalApplicationVersion ?? 'unknown';
            $output->writeln(sprintf('%-20s %s', 'Application version', $appVersionLabel));
        } else {
            $output->writeln(sprintf('%-20s <comment>unknown</comment>', 'Application version'));
        }

        $output->writeln(sprintf('%-20s %s', 'Version consistency', $consistencyLabel));
    }

    private function formatConsistency(string $consistency): string
    {
        return match ($consistency) {
            StatusResult::VERSION_CONSISTENT => '<info>consistent</info>',
            StatusResult::VERSION_INCONSISTENT => '<error>inconsistent</error>',
            default => '<comment>unknown</comment>',
        };
    }

    private function renderGitTag(OutputInterface $output, StatusResult $result): void
    {
        $gitTagLabel = $result->latestGitTag ?? 'none';
        $output->writeln(sprintf('%-20s %s', 'Latest Git tag', $gitTagLabel));
    }

    private function renderChangelog(OutputInterface $output, StatusResult $result): void
    {
        $changelogLabel = $result->changelogVersion ?? 'unknown';
        $output->writeln(sprintf('%-20s %s', 'CHANGELOG.md', $changelogLabel));
    }

    private function renderWorkingTree(OutputInterface $output, StatusResult $result): void
    {
        $treeLabel = $result->workingTreeClean ? '<info>clean</info>' : '<error>modified</error>';
        $output->writeln(sprintf('%-20s %s', 'Working tree', $treeLabel));
    }

    private function renderReleaseState(OutputInterface $output, StatusResult $result): void
    {
        $releaseLabel = $this->formatReleaseState($result->releaseState);
        $output->writeln(sprintf('%-20s %s', 'Release state', $releaseLabel));
    }

    private function formatReleaseState(string $releaseState): string
    {
        return match ($releaseState) {
            StatusResult::RELEASE_READY => '<info>ready</info>',
            default => '<error>not ready</error>',
        };
    }

    private function renderIssues(OutputInterface $output, StatusResult $result): void
    {
        if (count($result->issues) === 0) {
            return;
        }

        $output->writeln('');
        $output->writeln('Issues:');
        foreach ($result->issues as $issue) {
            $output->writeln('  - ' . $issue);
        }
    }

    private function renderWorkingTreeChanges(OutputInterface $output, StatusResult $result): void
    {
        if ($result->workingTreeClean) {
            return;
        }
        if (count($result->workingTreeChanges) === 0) {
            return;
        }

        $output->writeln('');
        $output->writeln('Working tree changes:');
        foreach ($result->workingTreeChanges as $change) {
            $output->writeln('  ' . $change);
        }
    }
}
