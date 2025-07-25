<?php

namespace PhpMonsters\Larapay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Larapay Facade
 *
 * @package PhpMonsters\Larapay\Facades
 */
class Larapay extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'larapay';
    }
}
