<?php

namespace Bolt\Extension\Cooperaj\MenuCompat;

use Bolt\BaseExtension;

class Extension extends BaseExtension
{
    /**
     * Extension name
     *
     * @var string
     */
    const NAME = "MenuCompat";

    /**
     * Extension's service container
     *
     * @var string
     */
    const CONTAINER = 'extensions.MenuCompat';

    public function getName()
    {
        return Extension::NAME;
    }

    public function initialize()
    {

    }
}






