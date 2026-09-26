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

        $config->setBeforeTagCommitMessage(
            $configArray['git']['commit_messages']['before_tag'] ?? $config->getBeforeTagCommitMessage(),
        );
        $config->setAfterTagCommitMessage(
            $configArray['git']['commit_messages']['after_tag'] ?? $config->getAfterTagCommitMessage(),
        );

        return $config;
    }
}
