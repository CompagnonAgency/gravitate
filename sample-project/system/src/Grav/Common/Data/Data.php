<?php
/**
 * @package    Grav.Common.Data
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Data;

use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccessWithGetters;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\File\FileInterface;

class Data implements DataInterface, \ArrayAccess, \Countable, ExportInterface
{
    use NestedArrayAccessWithGetters, Countable, Export;

    protected $gettersVariable = 'items';
    protected $items;

    /**
     * @var Blueprints
     */
    protected $blueprints;

    /**
     * @var File
     */
    protected $storage;

    /**
     * @param array $items
     * @param Blueprint|callable $blueprints
     */
    public function __construct(array $items = array(), $blueprints = null)
    {
        $this->items = $items;
        $this->blueprints = $blueprints;
    }

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @example $value = $data->value('this.is.my.nested.variable');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string  $separator  Separator, defaults to '.'
     * @return mixed  Value.
     */
    public function value($name, $default = null, $separator = '.')
    {
        return $this->get($name, $default, $separator);
    }

    /**
     * Join nested values together by using blueprints.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     * @throws \RuntimeException
     */
    public function join($name, $value, $separator = '.')
    {
        $old = $this->get($name, null, $separator);
        if ($old !== null) {
            if (!is_array($old)) {
                throw new \RuntimeException('Value ' . $old);
            }
            if (is_object($value)) {
                $value = (array) $value;
            } elseif (!is_array($value)) {
                throw new \RuntimeException('Value ' . $value);
            }
            $value = $this->blueprints()->mergeData($old, $value, $name, $separator);
        }

        $this->set($name, $value, $separator);

        return $this;
    }

    /**
     * Get nested structure containing default values defined in the blueprints.
     *
     * Fields without default value are ignored in the list.

     * @return array
     */
    public function getDefaults()
    {
        return $this->blueprints()->getDefaults();
    }

    /**
     * Set default values by using blueprints.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function joinDefaults($name, $value, $separator = '.')
    {
        if (is_object($value)) {
            $value = (array) $value;
        }
        $old = $this->get($name, null, $separator);
        if ($old !== null) {
            $value = $this->blueprints()->mergeData($value, $old, $name, $separator);
        }

        $this->set($name, $value, $separator);

        return $this;
    }

    /**
     * Get value from the configuration and join it with given data.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param array   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return array
     * @throws \RuntimeException
     */
    public function getJoined($name, $value, $separator = '.')
    {
        if (is_object($value)) {
            $value = (array) $value;
        } elseif (!is_array($value)) {
            throw new \RuntimeException('Value ' . $value);
        }

        $old = $this->get($name, null, $separator);

        if ($old === null) {
            // No value set; no need to join data.
            return $value;
        }

        if (!is_array($old)) {
            throw new \RuntimeException('Value ' . $old);
        }

        // Return joined data.
        return $this->blueprints()->mergeData($old, $value, $name, $separator);
    }


    /**
     * Merge two configurations together.
     *
     * @param array $data
     * @return $this
     */
    public function merge(array $data)
    {
        $this->items = $this->blueprints()->mergeData($this->items, $data);

        return $this;
    }

    /**
     * Set default values to the configuration if variables were not set.
     *
     * @param array $data
     * @return $this
     */
    public function setDefaults(array $data)
    {
        $this->items = $this->blueprints()->mergeData($data, $this->items);

        return $this;
    }

    /**
     * Validate by blueprints.
     *
     * @return $this
     * @throws \Exception
     */
    public function validate()
    {
        $this->blueprints()->validate($this->items);

        return $this;
    }

    /**
     * @return $this
     * Filter all items by using blueprints.
     */
    public function filter()
    {
        $this->items = $this->blueprints()->filter($this->items);

        return $this;
    }

    /**
     * Get extra items which haven't been defined in blueprints.
     *
     * @return array
     */
    public function extra()
    {
        return $this->blueprints()->extra($this->items);
    }

    /**
     * Return blueprints.
     *
     * @return Blueprint
     */
    public function blueprints()
    {
        if (!$this->blueprints){
            $this->blueprints = new Blueprint;
        } elseif (is_callable($this->blueprints)) {
            // Lazy load blueprints.
            $blueprints = $this->blueprints;
            $this->blueprints = $blueprints();
        }
        return $this->blueprints;
    }

    /**
     * Save data if storage has been defined.
     */
    public function save()
    {
        $file = $this->file();
        if ($file) {
            $file->save($this->items);
        }
    }

    /**
     * Returns whether the data already exists in the storage.
     *
     * NOTE: This method does not check if the data is current.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->file()->exists();
    }

    /**
     * Return unmodified data as raw string.
     *
     * NOTE: This function only returns data which has been saved to the storage.
     *
     * @return string
     */
    public function raw()
    {
        return $this->file()->raw();
    }

    /**
     * Set or get the data storage.
     *
     * @param FileInterface $storage Optionally enter a new storage.
     * @return FileInterface
     */
    public function file(FileInterface $storage = null)
    {
        if ($storage) {
            $this->storage = $storage;
        }
        return $this->storage;
    }
}
