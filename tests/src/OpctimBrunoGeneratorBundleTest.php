<?php
declare(strict_types=1);

namespace Opctim\BrunoGeneratorBundle\Tests;

use Opctim\BrunoGeneratorBundle\DependencyInjection\OpctimBrunoGeneratorExtension;
use Opctim\BrunoGeneratorBundle\OpctimBrunoGeneratorBundle;
use PHPUnit\Framework\TestCase;

class OpctimBrunoGeneratorBundleTest extends TestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new OpctimBrunoGeneratorBundle();

        self::assertInstanceOf(OpctimBrunoGeneratorExtension::class, $bundle->getContainerExtension());
    }
}