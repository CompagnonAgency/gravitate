<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Errors\Errors;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ErrorServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $errors = new Errors;
        $container['errors'] = $errors;
    }
}
