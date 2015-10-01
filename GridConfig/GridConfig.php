<?php

namespace TMSolution\DataGridBundle\GridConfig;

class GridConfig
{
    protected $container;
    
    public function __construct($container)
    {
        $this->container=$container;
    }
    
    public function buildGrid($grid,$routePrefix)
    {
        return $grid;
    }

}
