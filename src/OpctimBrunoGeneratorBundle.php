<?php

namespace Opctim\BrunoGeneratorBundle;

use Opctim\BrunoGeneratorBundle\DependencyInjection\OpctimBrunoGeneratorExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class OpctimBrunoGeneratorBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new OpctimBrunoGeneratorExtension();
    }

    public function getPath(): string
    {
        return __DIR__;
    }
}
