Add a virtual column
=======================

You can add a virtual or aggregate column (contact(a,b), max(a) etc.).
Always add a column with an alias.

## Usage

```php
<?php
use APY\DataGridBundle\Grid\Column\TextColumn;
...
$tableAlias = $grid->getSource()->getTableAlias();
$queryBuilderFn = function ($queryBuilder) use($tableAlias) {
    ...
    $queryBuilder->select('a,b,concat(a,b) as virtualColumn');
    ...

};
$grid->getSource()->manipulateQuery($queryBuilderFn);
...
$column = new TextColumn(array('id' => 'virtualColumn', 'field'=>'virtualColumn' ,'title' => 'virtualColumn','isManualField'=>true, 'source' => $grid->getSource(), 'filterable' => true, 'sortable' => true));
$grid->addColumn($column,'asc');

```
**Note**: ...

Presents value of nested entities
=======================

Presents value of nested entities, regardless of the level of nesting

## Usage
```php
<?php

$tableAlias = $grid->getSource()->getTableAlias();
$queryBuilderFn = function ($queryBuilder) use($tableAlias) {

    $queryBuilder->resetDQLPart('select');
    $queryBuilder->resetDQLPart('join');
    $queryBuilder->select($tableAlias.'.id,'.'_level2.name as level2::name,'.'_level3.name as level3::name');
    
    $queryBuilder->leftJoin("$tableAlias.level2","_level2");
    $queryBuilder->leftJoin("_status.level3","_level3");

};
$grid->getSource()->manipulateQuery($queryBuilderFn);
}

protected function configureColumn($grid)
{

                                         
$column = new TextColumn(array('id' => 'level2.name', 'field'=>'level2.name' ,'title' => 'level2.name', 'source' => $grid->getSource(), 'filterable' => true, 'sortable' => true));
$grid->addColumn($column,$columnOrder=null);

$column2 = new TextColumn(array('id' => 'level3.name', 'field'=>'level3.name' ,'title' => 'level3.name', 'source' => $grid->getSource(), 'filterable' => true, 'sortable' => true));
$grid->addColumn($column2,$columnOrder=null);
...
