<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\TestCase;
use Rlorenzo\MagoWordPress\WordPressExtension;

final class WordPressExtensionTest extends TestCase
{
    public function testFactoryOwnsStableRegistration(): void
    {
        $extension = WordPressExtension::create();

        self::assertSame('rlorenzo/mago-wordpress', $extension->identifier);
        self::assertSame('Mago WordPress Extension', $extension->name);
        self::assertSame('0.1.0', $extension->version);
        self::assertCount(1, $extension->linterRules);
        self::assertCount(1, $extension->analyzerPlugins);
        self::assertNull($extension->workerReducer);
    }
}
