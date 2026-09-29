<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class ManifestValidator
{
    private Configuration $configuration;
    private string $composerBinary;

    public function __construct(Configuration $configuration, string $composerBinary = 'composer')
    {
        $this->configuration = $configuration;
        $this->composerBinary = $composerBinary;
    }

    /**
     * Validates the peeled manifest against the unpeeled backup manifest and via composer validate.
     *
     * @throws RuntimeException
     */
    public function validate(string $manifestPath, string $backupPath): void
    {
        if (!file_exists($backupPath)) {
            throw new RuntimeException(
                "Backup file {$backupPath} does not exist. Run the peel command before releasing.",
            );
        }

        $peeledManifest = $this->decodeManifest($manifestPath);
        $backupManifest = $this->decodeManifest($backupPath);

        $violations = [];
        $peelSections = $this->configuration->getPeelSections();

        foreach ($peelSections as $section) {
            if (!array_key_exists($section, $peeledManifest)) {
                continue;
            }

            $violations[] = "Section '{$section}' has not been peeled.";
        }

        $sections = array_unique([...array_keys($backupManifest), ...array_keys($peeledManifest)]);

        foreach ($sections as $section) {
            if (in_array($section, $peelSections, true)) {
                continue;
            }

            if (!array_key_exists($section, $peeledManifest)) {
                $violations[] = "Section '{$section}' is missing.";
                continue;
            }

            if (!array_key_exists($section, $backupManifest)) {
                $violations[] = "Section '{$section}' is not present in the backup.";
                continue;
            }

            if ($peeledManifest[$section] === $backupManifest[$section]) {
                continue;
            }

            $violations[] = "Section '{$section}' differs from the backup.";
        }

        if ($violations !== []) {
            throw new RuntimeException(
                'The peeled composer.json is invalid:' . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $violations),
            );
        }

        $this->runComposerValidate($manifestPath);
    }

    private function runComposerValidate(string $manifestPath): void
    {
        exec(escapeshellarg($this->composerBinary) . ' --version 2>&1', $output, $resultCode);
        if ($resultCode !== 0) {
            throw new RuntimeException('Composer is not available or not installed.');
        }

        $command = [$this->composerBinary, 'validate', '--no-check-lock', '--no-interaction', $manifestPath];
        $escapedCommand = implode(' ', array_map('escapeshellarg', $command));

        $output = [];
        exec($escapedCommand . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0) {
            throw new RuntimeException(
                'The peeled composer.json failed composer validate:' . PHP_EOL . implode(PHP_EOL, $output),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeManifest(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Manifest {$path} does not exist.");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read manifest {$path}.");
        }

        $manifest = json_decode($content, associative: true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifest)) {
            throw new RuntimeException("Manifest {$path} does not contain a valid JSON object.");
        }

        /** @var array<string, mixed> $manifest */
        return $manifest;
    }
}
