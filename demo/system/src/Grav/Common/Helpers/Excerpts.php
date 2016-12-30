<?php
/**
 * @package    Grav.Common.Helpers
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Page\Medium\Medium;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class Excerpts
{
    /**
     * Process Grav image media URL from HTML tag
     *
     * @param $html         HTML tag e.g. `<img src="image.jpg" />`
     * @param $page         The current page object
     * @return string       Returns final HTML string
     */
    public static function processImageHtml($html, $page)
    {
        $excerpt = static::getExcerptFromHtml($html, 'img');

        $original_src = $excerpt['element']['attributes']['src'];
        $excerpt['element']['attributes']['href'] = $original_src;

        $excerpt = static::processLinkExcerpt($excerpt, $page, 'image');

        $excerpt['element']['attributes']['src'] = $excerpt['element']['attributes']['href'];
        unset ($excerpt['element']['attributes']['href']);

        $excerpt = static::processImageExcerpt($excerpt, $page);

        $excerpt['element']['attributes']['data-src'] = $original_src;

        $html = static::getHtmlFromExcerpt($excerpt);

        return $html;
    }

    /**
     * Get an Excerpt array from a chunk of HTML
     *
     * @param $html         Chunk of HTML
     * @param $tag          a tag, for example `img`
     * @return array|null   returns nested array excerpt
     */
    public static function getExcerptFromHtml($html, $tag)
    {
        $doc = new \DOMDocument();
        $doc->loadHtml($html);
        $images = $doc->getElementsByTagName($tag);
        $excerpt = null;

        foreach ($images as $image) {
            $attributes = [];
            foreach ($image->attributes as $name => $value) {
                $attributes[$name] = $value->value;
            }
            $excerpt = [
                'element' => [
                    'name'       => $image->tagName,
                    'attributes' => $attributes
                ]
            ];
        }

        return $excerpt;
    }

    /**
     * Rebuild HTML tag from an excerpt array
     *
     * @param $excerpt
     * @return string
     */
    public static function getHtmlFromExcerpt($excerpt)
    {
        $element = $excerpt['element'];
        $html = '<'.$element['name'];

        if (isset($element['attributes'])) {
            foreach ($element['attributes'] as $name => $value) {
                if ($value === null) {
                    continue;
                }
                $html .= ' '.$name.'="'.$value.'"';
            }
        }

        if (isset($element['text'])) {
            $html .= '>';
            $html .= $element['text'];
            $html .= '</'.$element['name'].'>';
        } else {
            $html .= ' />';
        }

        return $html;
    }

    /**
     * Process a Link excerpt
     *
     * @param $excerpt
     * @param $page
     * @param string $type
     * @return mixed
     */
    public static function processLinkExcerpt($excerpt, $page, $type = 'link')
    {
        $url = htmlspecialchars_decode(urldecode($excerpt['element']['attributes']['href']));

        $url_parts = static::parseUrl($url);

        // If there is a query, then parse it and build action calls.
        if (isset($url_parts['query'])) {
            $actions = array_reduce(explode('&', $url_parts['query']), function ($carry, $item) {
                $parts = explode('=', $item, 2);
                $value = isset($parts[1]) ? rawurldecode($parts[1]) : true;
                $carry[$parts[0]] = $value;

                return $carry;
            }, []);

            // Valid attributes supported.
            $valid_attributes = ['rel', 'target', 'id', 'class', 'classes'];

            // Unless told to not process, go through actions.
            if (array_key_exists('noprocess', $actions)) {
                unset($actions['noprocess']);
            } else {
                // Loop through actions for the image and call them.
                foreach ($actions as $attrib => $value) {
                    $key = $attrib;

                    if (in_array($attrib, $valid_attributes)) {
                        // support both class and classes.
                        if ($attrib == 'classes') {
                            $attrib = 'class';
                        }
                        $excerpt['element']['attributes'][$attrib] = str_replace(',', ' ', $value);
                        unset($actions[$key]);
                    }
                }
            }

            $url_parts['query'] = http_build_query($actions, null, '&', PHP_QUERY_RFC3986);
        }

        // If no query elements left, unset query.
        if (empty($url_parts['query'])) {
            unset ($url_parts['query']);
        }

        // Set path to / if not set.
        if (empty($url_parts['path'])) {
            $url_parts['path'] = '';
        }

        // If scheme isn't http(s)..
        if (!empty($url_parts['scheme']) && !in_array($url_parts['scheme'], ['http', 'https'])) {
            // Handle custom streams.
            if ($type !== 'image' && !empty($url_parts['stream']) && !empty($url_parts['path'])) {
                $url_parts['path'] = Grav::instance()['base_url_relative'] . '/' . static::resolveStream("{$url_parts['scheme']}://{$url_parts['path']}");
                unset($url_parts['stream'], $url_parts['scheme']);

                $excerpt['element']['attributes']['href'] = Uri::buildUrl($url_parts);
            }

            return $excerpt;
        }

        // Handle paths and such.
        $url_parts = Uri::convertUrl($page, $url_parts, $type);

        // Build the URL from the component parts and set it on the element.
        $excerpt['element']['attributes']['href'] = Uri::buildUrl($url_parts);

        return $excerpt;
    }

    /**
     * Process an image excerpt
     *
     * @param $excerpt
     * @param $page
     * @return mixed
     */
    public static function processImageExcerpt($excerpt, $page)
    {
        $url = htmlspecialchars_decode(urldecode($excerpt['element']['attributes']['src']));
        $url_parts = static::parseUrl($url);

        $media = null;
        $filename = null;

        if (!empty($url_parts['stream'])) {
            $filename = $url_parts['scheme'] . '://' . (isset($url_parts['path']) ? $url_parts['path'] : '');

            $media = $page->media();

        } else {
            // File is also local if scheme is http(s) and host matches.
            $local_file = isset($url_parts['path'])
                && (empty($url_parts['scheme']) || in_array($url_parts['scheme'], ['http', 'https']))
                && (empty($url_parts['host']) || $url_parts['host'] == Grav::instance()['uri']->host());

            if ($local_file) {
                $filename = basename($url_parts['path']);
                $folder = dirname($url_parts['path']);

                // Get the local path to page media if possible.
                if ($folder === $page->url(false, false, false)) {
                    // Get the media objects for this page.
                    $media = $page->media();
                } else {
                    // see if this is an external page to this one
                    $base_url = rtrim(Grav::instance()['base_url_relative'] . Grav::instance()['pages']->base(), '/');
                    $page_route = '/' . ltrim(str_replace($base_url, '', $folder), '/');

                    $ext_page = Grav::instance()['pages']->dispatch($page_route, true);
                    if ($ext_page) {
                        $media = $ext_page->media();
                    } else {
                        Grav::instance()->fireEvent('onMediaLocate', new Event(['route' => $page_route, 'media' => &$media]));
                    }
                }
            }
        }

        // If there is a media file that matches the path referenced..
        if ($media && $filename && isset($media[$filename])) {
            // Get the medium object.
            /** @var Medium $medium */
            $medium = $media[$filename];

            // Process operations
            $medium = static::processMediaActions($medium, $url_parts);

            $alt = isset($excerpt['element']['attributes']['alt']) ? $excerpt['element']['attributes']['alt'] : '';
            $title = isset($excerpt['element']['attributes']['title']) ? $excerpt['element']['attributes']['title'] : '';
            $class = isset($excerpt['element']['attributes']['class']) ? $excerpt['element']['attributes']['class'] : '';
            $id = isset($excerpt['element']['attributes']['id']) ? $excerpt['element']['attributes']['id'] : '';

            $excerpt['element'] = $medium->parseDownElement($title, $alt, $class, $id, true);

        } else {
            // Not a current page media file, see if it needs converting to relative.
            $excerpt['element']['attributes']['src'] = Uri::buildUrl($url_parts);
        }

        return $excerpt;
    }

    /**
     * Process media actions
     *
     * @param $medium
     * @param $url
     * @return mixed
     */
    public static function processMediaActions($medium, $url)
    {
        if (!is_array($url)) {
            $url_parts = parse_url($url);
        } else {
            $url_parts = $url;
        }

        $actions = [];

        // if there is a query, then parse it and build action calls
        if (isset($url_parts['query'])) {
            $actions = array_reduce(explode('&', $url_parts['query']), function ($carry, $item) {
                $parts = explode('=', $item, 2);
                $value = isset($parts[1]) ? $parts[1] : null;
                $carry[] = ['method' => $parts[0], 'params' => $value];

                return $carry;
            }, []);
        }

        if (Grav::instance()['config']->get('system.images.auto_fix_orientation')) {
            $actions[] = ['method' => 'fixOrientation', 'params' => ''];
        }

        // loop through actions for the image and call them
        foreach ($actions as $action) {
            $matches = [];

            if (preg_match('/\[(.*)\]/', $action['params'], $matches)) {
                $args = [explode(',', $matches[1])];
            } else {
                $args = explode(',', $action['params']);
            }

            $medium = call_user_func_array([$medium, $action['method']], $args);
        }

        if (isset($url_parts['fragment'])) {
            $medium->urlHash($url_parts['fragment']);
        }

        return $medium;
    }

    /**
     * Variation of parse_url() which works also with local streams.
     *
     * @param string $url
     * @return array|bool
     */
    protected static function parseUrl($url)
    {
        $url_parts = parse_url($url);

        if (isset($url_parts['scheme'])) {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];

            // Special handling for the streams.
            if ($locator->schemeExists($url_parts['scheme'])) {
                if (isset($url_parts['host'])) {
                    // Merge host and path into a path.
                    $url_parts['path'] = $url_parts['host'] . (isset($url_parts['path']) ? '/' . $url_parts['path'] : '');
                    unset($url_parts['host']);
                }

                $url_parts['stream'] = true;
            }
        }

        return $url_parts;
    }

    protected static function resolveStream($url)
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        return $locator->isStream($url) ? ($locator->findResource($url, false) ?: $locator->findResource($url, false, true)) : $url;
    }
}
