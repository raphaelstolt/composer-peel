<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

class ManifestComparator
{
    public const PEELED = 'peeled';
    public const RESTORED = 'restored';
    public const MODIFIED = 'modified';

    private Configuration $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Determines the state of the current manifest in relation to the unpeeled backup manifest.
     *
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $backupManifest
     */
    public function compare(array $manifest, array $backupManifest): string
    {
        if ($manifest === $backupManifest) {
            return self::RESTORED;
        }

        $peeledBackupManifest = $backupManifest;
        foreach ($this->configuration->getPeelSections() as $section) {
            unset($peeledBackupManifest[$section]);
        }

        if ($manifest === $peeledBackupManifest) {
            return self::PEELED;
        }

        return self::MODIFIED;
    }
}
