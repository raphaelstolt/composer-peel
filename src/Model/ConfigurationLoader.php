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

        $this->applyReleaseConfig($config, $configArray);
        $this->applyGitConfig($config, $configArray);

        return $config;
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function applyReleaseConfig(Configuration $config, array $configArray): void
    {
        if (
            array_key_exists('release', $configArray)
            && is_array($configArray['release'])
            && array_key_exists('backup', $configArray['release'])
            && is_array($configArray['release']['backup'])
        ) {
            $backupConfig = $configArray['release']['backup'];
            if (array_key_exists('enabled', $backupConfig)) {
                $config->setBackupEnabled((bool) $backupConfig['enabled']);
            }
            if (array_key_exists('path', $backupConfig)) {
                $config->setBackupPath((string) $backupConfig['path']);
            }
        }
    }

    /**
     * @param array<string, mixed> $configArray
     */
    private function applyGitConfig(Configuration $config, array $configArray): void
    {
        if (
            array_key_exists('git', $configArray)
            && is_array($configArray['git'])
            && array_key_exists('commit_messages', $configArray['git'])
            && is_array($configArray['git']['commit_messages'])
        ) {
            $messagesConfig = $configArray['git']['commit_messages'];
            if (array_key_exists('before_tag', $messagesConfig)) {
                $config->setBeforeTagCommitMessage((string) $messagesConfig['before_tag']);
            }
            if (array_key_exists('after_tag', $messagesConfig)) {
                $config->setAfterTagCommitMessage((string) $messagesConfig['after_tag']);
            }
        }
    }
}
