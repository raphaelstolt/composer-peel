<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ReleaseManager;

class ReleaseManagerTest extends TestCase
{
    public function testReleaseThrowsExceptionIfBackupDisabled(): void
    {
        $config = new Configuration();
        $config->setBackupEnabled(false);

        $validator = $this->createStub(\Stolt\ComposerPeel\Model\ReleaseVersionValidator::class);
        $manager = new ReleaseManager($config, $validator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Release workflow requires backup to be enabled.');

        $manager->release('v1.0.0');
    }

    public function testReleaseThrowsExceptionIfTagIsNotValidSemver(): void
    {
        $config = new Configuration();

        $validator = $this->createMock(\Stolt\ComposerPeel\Model\ReleaseVersionValidator::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with('invalid-tag')
            ->willThrowException(
                new RuntimeException("The provided Git tag 'invalid-tag' is not a valid semantic version."),
            );

        $manager = new ReleaseManager($config, $validator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The provided Git tag 'invalid-tag' is not a valid semantic version.");

        $manager->release('invalid-tag');
    }
}
