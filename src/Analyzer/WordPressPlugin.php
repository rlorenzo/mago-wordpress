<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Analyzer;

use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;
use Rlorenzo\MagoWordPress\Analyzer\Providers\ContainerReturnTypeProvider;

/**
 * @internal
 */
final class WordPressPlugin implements Plugin
{
    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'wordpress/framework',
            name: 'Acme Framework',
            description: 'Understands Acme framework conventions.',
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->enableProviderMemoization();
        $registry->registerMethodReturnTypeProvider(new ContainerReturnTypeProvider());
    }
}
