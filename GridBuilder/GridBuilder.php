<?php

namespace TMSolution\DataGridBundle\GridBuilder;

class GridBuilder
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
