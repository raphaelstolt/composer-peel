<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class ConfigurationLoader
{
    public function load(string $path): Configuration
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Configuration file not found: {$path}");
        }

        /** @var mixed $configArray */
        $configArray = require $path;

        if (!is_array($configArray)) {
            throw new RuntimeException('Configuration file must return an array.');
        }

        $config = new Configuration();

        $config->setPeelSections($configArray['peel']['sections'] ?? $config->getPeelSections());

        $config->setBackupEnabled($configArray['release']['backup']['enabled'] ?? $config->isBackupEnabled());
        $config->setBackupPath($configArray['release']['backup']['path'] ?? $config->getBackupPath());
        $config->setManagedFiles($configArray['release']['files'] ?? $config->getManagedFiles());

        $config->setReleaseCommitMessage(
            $configArray['release']['commit_message'] ?? $config->getReleaseCommitMessage(),
        );
        $config->setRollbackCommitMessage(
            $configArray['rollback']['commit_message'] ?? $config->getRollbackCommitMessage(),
        );

        return $config;
    }
}
