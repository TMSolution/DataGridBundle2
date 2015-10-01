<?php

namespace TMSolution\DataGridBundle\Walker;

use \Doctrine\ORM\Query\SqlWalker;

class MysqlWalker extends SqlWalker
{

    /**
     * Walks down a SelectClause AST node, thereby generating the appropriate SQL.
     *
     * @param $selectClause
     * @return string The SQL.
     */
    public function walkSelectClause($selectClause)
    {
        $sql = parent::walkSelectClause($selectClause);

        if ($this->getQuery()->getHint('mysqlWalker.count') === true) {
           
                $sql = str_replace('SELECT', 'SELECT SQL_CALC_FOUND_ROWS', $sql);
            
        }

        return $sql;
    }

}
