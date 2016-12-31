<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class SiteSetupProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = '_setup';
    public $title = 'Site Setup';

    public function process() {
      	$this->container['setup']->init();
      	$this->container['streams'];
    }

}
