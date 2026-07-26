<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Format;

final class FormatTest extends CIUnitTestCase
{
    public function testJsonEncodeDepthIsDefinedForModernFrameworkFormatters(): void
    {
        $config = new Format();

        $this->assertSame(512, $config->jsonEncodeDepth);
    }
}
