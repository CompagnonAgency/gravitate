<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\Event\Event;

class Twig
{
    /**
     * @var \Twig_Environment
     */
    public $twig;

    /**
     * @var array
     */
    public $twig_vars = [];

    /**
     * @var array
     */
    public $twig_paths;

    /**
     * @var string
     */
    public $template;

    /**
     * @var Grav
     */
    protected $grav;

    /**
     * @var \Twig_Loader_Filesystem
     */
    protected $loader;

    /**
     * @var \Twig_Loader_Array
     */
    protected $loaderArray;


    protected $autoescape;

    /**
     * Constructor
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->twig_paths = [];
    }

    /**
     * Twig initialization that sets the twig loader chain, then the environment, then extensions
     * and also the base set of twig vars
     */
    public function init()
    {
        if (!isset($this->twig)) {
            /** @var Config $config */
            $config = $this->grav['config'];
            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];

            /** @var Language $language */
            $language = $this->grav['language'];

            $active_language = $language->getActive();

            $path_append = rtrim($this->grav['pages']->base(), '/');
            if ($language->getDefault() != $active_language || $config->get('system.languages.include_default_lang') === true) {
                $path_append .= $active_language ? '/' . $active_language : '';
            }

            // handle language templates if available
            if ($language->enabled()) {
                $lang_templates = $locator->findResource('theme://templates/' . ($active_language ? $active_language : $language->getDefault()));
                if ($lang_templates) {
                    $this->twig_paths[] = $lang_templates;
                }
            }

            $this->twig_paths = array_merge($this->twig_paths, $locator->findResources('theme://templates'));

            $this->grav->fireEvent('onTwigTemplatePaths');

            $this->loader = new \Twig_Loader_Filesystem($this->twig_paths);
            $this->loaderArray = new \Twig_Loader_Array([]);
            $loader_chain = new \Twig_Loader_Chain([$this->loaderArray, $this->loader]);

            $params = $config->get('system.twig');
            if (!empty($params['cache'])) {
                $cachePath = $locator->findResource('cache://twig', true, true);
                $params['cache'] = new \Twig_Cache_Filesystem($cachePath, \Twig_Cache_Filesystem::FORCE_BYTECODE_INVALIDATION);
            }

            if (!empty($this->autoescape)) {
                $params['autoescape'] = $this->autoescape;
            }

            $this->twig = new TwigEnvironment($loader_chain, $params);

            if ($config->get('system.twig.undefined_functions')) {
                $this->twig->registerUndefinedFunctionCallback(function ($name) {
                    if (function_exists($name)) {
                        return new \Twig_Function_Function($name);
                    }

                    return new \Twig_Function_Function(function () {
                    });
                });
            }

            if ($config->get('system.twig.undefined_filters')) {
                $this->twig->registerUndefinedFilterCallback(function ($name) {
                    if (function_exists($name)) {
                        return new \Twig_Filter_Function($name);
                    }

                    return new \Twig_Filter_Function(function () {
                    });
                });
            }

            $this->grav->fireEvent('onTwigInitialized');

            // set default date format if set in config
            if ($config->get('system.pages.dateformat.long')) {
                $this->twig->getExtension('core')->setDateFormat($config->get('system.pages.dateformat.long'));
            }
            // enable the debug extension if required
            if ($config->get('system.twig.debug')) {
                $this->twig->addExtension(new \Twig_Extension_Debug());
            }
            $this->twig->addExtension(new TwigExtension());

            $this->grav->fireEvent('onTwigExtensions');

            // Set some standard variables for twig
            $this->twig_vars = $this->twig_vars + [
                    'config'            => $config,
                    'uri'               => $this->grav['uri'],
                    'base_dir'          => rtrim(ROOT_DIR, '/'),
                    'base_url'          => $this->grav['base_url'] . $path_append,
                    'base_url_simple'   => $this->grav['base_url'],
                    'base_url_absolute' => $this->grav['base_url_absolute'] . $path_append,
                    'base_url_relative' => $this->grav['base_url_relative'] . $path_append,
                    'theme_dir'         => $locator->findResource('theme://'),
                    'theme_url'         => $this->grav['base_url'] . '/' . $locator->findResource('theme://', false),
                    'site'              => $config->get('site'),
                    'assets'            => $this->grav['assets'],
                    'taxonomy'          => $this->grav['taxonomy'],
                    'browser'           => $this->grav['browser'],
                ];
        }
    }

    /**
     * @return \Twig_Environment
     */
    public function twig()
    {
        return $this->twig;
    }

    /**
     * @return \Twig_Loader_Filesystem
     */
    public function loader()
    {
        return $this->loader;
    }

    /**
     * Adds or overrides a template.
     *
     * @param string $name     The template name
     * @param string $template The template source
     */
    public function setTemplate($name, $template)
    {
        $this->loaderArray->setTemplate($name, $template);
    }

    /**
     * Twig process that renders a page item. It supports two variations:
     * 1) Handles modular pages by rendering a specific page based on its modular twig template
     * 2) Renders individual page items for twig processing before the site rendering
     *
     * @param  Page   $item    The page item to render
     * @param  string $content Optional content override
     *
     * @return string          The rendered output
     * @throws \Twig_Error_Loader
     */
    public function processPage(Page $item, $content = null)
    {
        $content = $content !== null ? $content : $item->content();

        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigPageVariables', new Event(['page' => $item]));
        $twig_vars = $this->twig_vars;

        $twig_vars['page'] = $item;
        $twig_vars['media'] = $item->media();
        $twig_vars['header'] = $item->header();

        $local_twig = clone($this->twig);
        $modular_twig = $item->modularTwig();
        $process_twig = isset($item->header()->process['twig']) ? $item->header()->process['twig'] : false;

        $output = '';

        try {
            // Process Modular Twig
            if ($modular_twig) {
                $twig_vars['content'] = $content;
                $template = $item->template() . TEMPLATE_EXT;
                $output = $content = $local_twig->render($template, $twig_vars);
            }

            // Process in-page Twig
            if (!$modular_twig || ($modular_twig && $process_twig)) {
                $name = '@Page:' . $item->path();
                $this->setTemplate($name, $content);
                $output = $local_twig->render($name, $twig_vars);
            }
        } catch (\Twig_Error_Loader $e) {
            throw new \RuntimeException($e->getRawMessage(), 404, $e);
        }

        return $output;
    }

    /**
     * Process a Twig template directly by using a template name
     * and optional array of variables
     *
     * @param string $template template to render with
     * @param array  $vars     Optional variables
     *
     * @return string
     */
    public function processTemplate($template, $vars = [])
    {
        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigTemplateVariables');
        $vars += $this->twig_vars;

        try {
            $output = $this->twig->render($template, $vars);
        } catch (\Twig_Error_Loader $e) {
            throw new \RuntimeException($e->getRawMessage(), 404, $e);
        }

        return $output;

    }


    /**
     * Process a Twig template directly by using a Twig string
     * and optional array of variables
     *
     * @param string $string string to render.
     * @param array  $vars   Optional variables
     *
     * @return string
     */
    public function processString($string, array $vars = [])
    {
        // override the twig header vars for local resolution
        $this->grav->fireEvent('onTwigStringVariables');
        $vars += $this->twig_vars;

        $name = '@Var:' . $string;
        $this->setTemplate($name, $string);

        try {
            $output = $this->twig->render($name, $vars);
        } catch (\Twig_Error_Loader $e) {
            throw new \RuntimeException($e->getRawMessage(), 404, $e);
        }

        return $output;
    }

    /**
     * Twig process that renders the site layout. This is the main twig process that renders the overall
     * page and handles all the layout for the site display.
     *
     * @param string $format Output format (defaults to HTML).
     *
     * @return string the rendered output
     * @throws \RuntimeException
     */
    public function processSite($format = null)
    {
        // set the page now its been processed
        $this->grav->fireEvent('onTwigSiteVariables');
        $pages = $this->grav['pages'];
        $page = $this->grav['page'];
        $content = $page->content();

        $twig_vars = $this->twig_vars;

        $twig_vars['pages'] = $pages->root();
        $twig_vars['page'] = $page;
        $twig_vars['header'] = $page->header();
        $twig_vars['media'] = $page->media();
        $twig_vars['content'] = $content;
        $ext = '.' . ($format ? $format : 'html') . TWIG_EXT;

        // determine if params are set, if so disable twig cache
        $params = $this->grav['uri']->params(null, true);
        if (!empty($params)) {
            $this->twig->setCache(false);
        }

        // Get Twig template layout
        $template = $this->template($page->template() . $ext);

        try {
            $output = $this->twig->render($template, $twig_vars);
        } catch (\Twig_Error_Loader $e) {
            $error_msg = $e->getMessage();
            // Try html version of this template if initial template was NOT html
            if ($ext != '.html' . TWIG_EXT) {
                try {
                    $output = $this->twig->render($page->template() . '.html' . TWIG_EXT, $twig_vars);
                } catch (\Twig_Error_Loader $e) {
                    throw new \RuntimeException($error_msg, 400, $e);
                }
            } else {
                throw new \RuntimeException($error_msg, 400, $e);
            }
        }

        return $output;
    }

    /**
     * Simple helper method to get the twig template if it has already been set, else return
     * the one being passed in
     *
     * @param  string $template the template name
     *
     * @return string           the template name
     */
    public function template($template)
    {
        if (isset($this->template)) {
            return $this->template;
        } else {
            return $template;
        }
    }

    /**
     * Overrides the autoescape setting
     *
     * @param boolean $state
     */
    public function setAutoescape($state) {
        $this->autoescape = (bool) $state;
    }
}
