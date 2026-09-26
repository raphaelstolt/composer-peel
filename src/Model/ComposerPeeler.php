<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class ComposerPeeler
{
    private Configuration $configuration;

    public function __construct(?Configuration $configuration = null)
    {
        $this->configuration = $configuration ?? new Configuration();
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function peel(): void
    {
        $manifestPath = getcwd() . '/composer.json';

        $manifestContent = $this->readManifest($manifestPath);
        $manifest = $this->decodeManifest($manifestContent);

        if ($this->configuration->isBackupEnabled()) {
            if (file_put_contents($this->configuration->getBackupPath(), $manifestContent) === false) {
                throw new RuntimeException('Could not write backup file.');
            }
        }

        $manifest = $this->removeDevelopmentSections($manifest);

        $this->writeManifest($manifestPath, $manifest);
    }

    public function simulatePeel(): DryRunResult
    {
        $manifestPath = getcwd() . '/composer.json';

        $manifestContent = $this->readManifest($manifestPath);
        $originalSize = strlen($manifestContent);

        $manifest = $this->decodeManifest($manifestContent);

        $sectionsToRemove = $this->configuration->getPeelSections();

        $removedSections = [];
        foreach ($sectionsToRemove as $section) {
            if (!array_key_exists($section, $manifest)) {
                continue;
            }

            $removedSections[] = $section;
        }

        $manifest = $this->removeDevelopmentSections($manifest);

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        $newManifestContent = (string) json_encode($manifest, $flags);
        $projectedSize = strlen($newManifestContent) + 1; // +1 for the newline appended on write

        return new DryRunResult($removedSections, $originalSize, $projectedSize);
    }

    private function readManifest(string $manifestPath): string
    {
        if (!file_exists($manifestPath)) {
            throw new RuntimeException('composer.json not found in current directory.');
        }

        $manifestContent = file_get_contents($manifestPath);
        if ($manifestContent === false) {
            throw new RuntimeException('Could not read composer.json.');
        }

        return $manifestContent;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeManifest(string $manifestContent): array
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($manifestContent, associative: true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in composer.json: ' . json_last_error_msg());
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function removeDevelopmentSections(array $manifest): array
    {
        $sectionsToRemove = $this->configuration->getPeelSections();

        foreach ($sectionsToRemove as $section) {
            if (!array_key_exists($section, $manifest)) {
                continue;
            }

            unset($manifest[$section]);
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeManifest(string $manifestPath, array $manifest): void
    {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        $newManifestContent = json_encode($manifest, $flags);

        if ($newManifestContent === false) {
            throw new RuntimeException('Could not encode new composer.json.');
        }

        if (file_put_contents($manifestPath, $newManifestContent . "\n") === false) {
            throw new RuntimeException('Could not write modified composer.json.');
        }
    }
}
