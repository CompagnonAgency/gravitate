<?php
/**
 * @package    Grav.Common.Processors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Processors;

class DebuggerInitProcessor extends ProcessorBase implements ProcessorInterface {

    public $id = '_debugger';
    public $title = 'Init Debugger';

    public function process() {
      	$this->container['debugger']->init();
    }

}
