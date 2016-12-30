<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Setup;
use Pimple\Container;
use RocketTheme\Toolbox\DI\ServiceProviderInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\StreamWrapper\ReadOnlyStream;
use RocketTheme\Toolbox\StreamWrapper\Stream;
use RocketTheme\Toolbox\StreamWrapper\StreamBuilder;

class StreamsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['locator'] = function($c) {
            $locator = new UniformResourceLocator(GRAV_ROOT);

            /** @var Setup $setup */
            $setup = $c['setup'];
            $setup->initializeLocator($locator);

            return $locator;
        };

        $container['streams'] = function($c) {
            /** @var Setup $setup */
            $setup = $c['setup'];

            /** @var UniformResourceLocator $locator */
            $locator = $c['locator'];

            // Set locator to both streams.
            Stream::setLocator($locator);
            ReadOnlyStream::setLocator($locator);

            return new StreamBuilder($setup->getStreams());
        };
    }
}
