<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Markdown\Parsedown;

trait ParsedownHtmlTrait
{
    /**
     * @var \Grav\Common\Markdown\Parsedown
     */
    protected $parsedown = null;

    /**
     * Return HTML markup from the medium.
     *
     * @param string $title
     * @param string $alt
     * @param string $class
     * @param string $id
     * @param bool $reset
     * @return string
     */
    public function html($title = null, $alt = null, $class = null, $id = null, $reset = true)
    {
        $element = $this->parsedownElement($title, $alt, $class, $id, $reset);

        if (!$this->parsedown) {
            $this->parsedown = new Parsedown(null, null);
        }

        return $this->parsedown->elementToHtml($element);
    }
}
