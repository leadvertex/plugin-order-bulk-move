<?php

namespace Leadvertex\Plugin\Instance\Macros\Components;

trait OptionsSingletonTrait
{
    /** @var self */
    private static $instance;

    public static function getInstance(): self
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

}