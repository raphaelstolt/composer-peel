<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ManifestComparator;

class ManifestComparatorTest extends TestCase
{
    private ManifestComparator $comparator;

    /** @var array<string, mixed> */
    private array $backupManifest = [
        'name' => 'test/package',
        'require' => ['php' => '>=8.2'],
        'require-dev' => ['phpunit/phpunit' => '^10.0'],
        'scripts' => ['test' => 'phpunit'],
    ];

    protected function setUp(): void
    {
        $this->comparator = new ManifestComparator(new Configuration());
    }

    public function testCompareDetectsPeeledManifest(): void
    {
        $manifest = ['name' => 'test/package', 'require' => ['php' => '>=8.2']];

        static::assertSame(ManifestComparator::PEELED, $this->comparator->compare($manifest, $this->backupManifest));
    }

    public function testCompareDetectsRestoredManifest(): void
    {
        static::assertSame(ManifestComparator::RESTORED, $this->comparator->compare(
            $this->backupManifest,
            $this->backupManifest,
        ));
    }

    public function testCompareDetectsModifiedRuntimeSection(): void
    {
        $manifest = ['name' => 'test/package', 'require' => ['php' => '>=8.3']];

        static::assertSame(ManifestComparator::MODIFIED, $this->comparator->compare($manifest, $this->backupManifest));
    }

    public function testCompareDetectsAddedSection(): void
    {
        $manifest = ['name' => 'test/package', 'require' => ['php' => '>=8.2'], 'license' => 'MIT'];

        static::assertSame(ManifestComparator::MODIFIED, $this->comparator->compare($manifest, $this->backupManifest));
    }

    public function testCompareDetectsPartiallyPeeledManifest(): void
    {
        $manifest = ['name' => 'test/package', 'require' => ['php' => '>=8.2'], 'scripts' => ['test' => 'phpunit']];

        static::assertSame(ManifestComparator::MODIFIED, $this->comparator->compare($manifest, $this->backupManifest));
    }

    public function testCompareRespectsConfiguredPeelSections(): void
    {
        $configuration = new Configuration();
        $configuration->setPeelSections(['require-dev']);

        $manifest = ['name' => 'test/package', 'require' => ['php' => '>=8.2'], 'scripts' => ['test' => 'phpunit']];

        static::assertSame(ManifestComparator::PEELED, (new ManifestComparator($configuration))->compare(
            $manifest,
            $this->backupManifest,
        ));
    }
}
