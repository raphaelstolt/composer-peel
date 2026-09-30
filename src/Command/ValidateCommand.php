<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Command;

use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ConfigurationLoader;
use Stolt\ComposerPeel\Model\ManifestValidator;
use Stolt\ComposerPeel\Model\ValidationCheck;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'validate',
    description: 'Validate the peeled composer.json against the configuration and the backup file',
)]
class ValidateCommand extends Command
{
    private ?ManifestValidator $manifestValidator;

    public function __construct(?ManifestValidator $manifestValidator = null)
    {
        $this->manifestValidator = $manifestValidator;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('backup-file', null, InputOption::VALUE_REQUIRED, 'Name of the composer.json backup file');

        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the configuration file');

        $this->addOption(
            'skip-composer-validate',
            null,
            InputOption::VALUE_NONE,
            'Do not run composer validate on the manifests',
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

        if ($this->manifestValidator === null) {
            $this->manifestValidator = new ManifestValidator($configuration);
        }

        $result = $this->manifestValidator->check(
            'composer.json',
            $configuration->getBackupPath(),
            !$input->getOption('skip-composer-validate'),
        );

        foreach ($result->getChecks() as $check) {
            $label = match ($check->getStatus()) {
                ValidationCheck::PASSED => '<info>[PASS]</info>',
                ValidationCheck::FAILED => '<error>[FAIL]</error>',
                default => '<comment>[SKIP]</comment>',
            };

            $output->writeln("{$label} {$check->getDescription()}");

            foreach ($check->getMessages() as $message) {
                $output->writeln("       - {$message}");
            }
        }

        $output->writeln('');

        if (!$result->isValid()) {
            $output->writeln('<error>Validation of the peeled composer.json failed.</error>');
            return Command::FAILURE;
        }

        $output->writeln('Peeled composer.json validated successfully.');

        return Command::SUCCESS;
    }
}
