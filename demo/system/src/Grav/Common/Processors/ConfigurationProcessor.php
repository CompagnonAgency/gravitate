<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class ConfigurationProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = '_config';
    public $title = 'Configuration';

    public function process() {
      	$this->container['config']->init();
      	return $this->container['plugins']->setup();
    }

}
