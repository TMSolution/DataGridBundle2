<?php

namespace TMSolution\DataGridBundle\GridConfig;

use TMSolution\DataGridBundle\Grid\Action\RowAction;
use TMSolution\DataGridBundle\Grid\Column\NumberColumn;
use TMSolution\DataGridBundle\Grid\Column\TextColumn;

class GridConfig {

    protected $container;
    protected $request;
    protected $analizedFieldsInfo;
    protected $objectName;
    protected $model;
    protected $manyToManyRelationExists = false;

    public function __construct($container) {
        $this->container = $container;
    }
    
    
   protected function init()
   {
        $this->request = $this->getContainer()->get('request');
       
        $this->objectName = $this->request->get('objectName');
        
        $this->model = $this->getContainer()->get('model_factory')->getModel($this->objectName);
        $this->analizedFieldsInfo = $this->analizeFieldsInfo($this->model->getFieldsInfo());

       
   }
    

    public function buildGrid($grid, $routePrefix) {

        $this->init();
        $this->manipulateQuery($grid);
        $this->configureColumns($grid);
        $this->configureRowButton($grid, $routePrefix);
        return $grid;
    }

    public function getContainer() {
        return $this->container;
    }

    protected function analizeFieldsInfo($fieldsInfo) {


        foreach ($fieldsInfo as $key => $value) {


            if (array_key_exists("association", $fieldsInfo[$key]) && ( $fieldsInfo[$key]["association"] == "ManyToOne" || $fieldsInfo[$key]["association"] == "OneToOne" )) {

                if ($fieldsInfo[$key]["association"] == "ManyToMany") {
                    $this->manyToManyRelationExists = true;
                }


                $model = $this->getContainer()->get("model_factory")->getModel($fieldsInfo[$key]["object_name"]);
                if ($model->checkPropertyByName("name")) {
                    $fieldsInfo[$key]["default_field"] = "name";
                    $fieldsInfo[$key]["default_field_type"] = "Text";
                } else {
                    $fieldsInfo[$key]["default_field"] = "id";
                    $fieldsInfo[$key]["default_field_type"] = "Number";
                }
            } elseif (array_key_exists("association", $fieldsInfo[$key]) && ( $fieldsInfo[$key]["association"] == "ManyToMany" || $fieldsInfo[$key]["association"] == "OneToMany" )) {
                unset($fieldsInfo[$key]);
            }
        }

        return $fieldsInfo;
    }

    protected function getColumnTitle($objectName, $defaultName = null, $parentObject = null) {


        $entityReflection = new \ReflectionClass($objectName);
        $entityNamespace = $entityReflection->getNamespaceName();
        $objectName = $entityReflection->getShortName();


        if ($parentObject) {
            $parentEntityReflection = new \ReflectionClass($parentObject);
            $parentEntityNamespace = $parentEntityReflection->getNamespaceName();
            $parentObjectName = $parentEntityReflection->getShortName();
        }

        $lowerNameSpaceForTranslate = str_replace('bundle.entity', '', str_replace('\\', '.', strtolower($entityNamespace)));
        if ($defaultName && !$parentObject) {

            return "{$lowerNameSpaceForTranslate}." . strtolower($objectName) . ".{$defaultName}";
        } else {
            if ($parentObject) {
                return "{$lowerNameSpaceForTranslate}." . strtolower("$parentObjectName.") . lcfirst($objectName) . ".{$defaultName}";
            } else {
                return "{$lowerNameSpaceForTranslate}." . strtolower($objectName);
            }
        }
    }

    
    protected function calculateRealId($fieldName)
    {
        if($fieldName=='name')
        {
            $fieldName='id';
        }
        return $fieldName;
    }
    
    
    protected function configureColumns($grid) {

        $first = true;
        $fields = [];


        foreach ($this->analizedFieldsInfo as $field => $fieldParam) {

            if (array_key_exists('association', $fieldParam) && ($fieldParam['association'] == 'ManyToOne' || $fieldParam['association'] == 'OneToOne' )) {
                
                
                
                
                $realId= $field.'.'.$this->calculateRealID($fieldParam['default_field']);
                
                $fieldType = 'TMSolution\\DataGridBundle\\Grid\\Column\\' . $fieldParam["default_field_type"] . "Column";
                $column = new $fieldType(array('id' =>$realId, 'field' => "{$field}.{$fieldParam['default_field']}", 'title' => "{$field}.{$fieldParam['default_field']}", 'source' => $grid->getSource(), 'filterable' => true, 'sortable' => true,'options'=>['eq']));
                $column->setFilterType('select');
                $column->setSelectExpanded(FALSE);

                $column->setTitle($this->getColumnTitle($fieldParam['object_name'], $fieldParam['default_field'], $this->objectName));

                $objectName = $this->getContainer()->get('classmapperservice')->getEntityName($fieldParam['object_name']);
                $column->setSafe(false); // not convert html entities
                $column->manipulateRenderCell(function($value, $row) use ($field, $fieldParam, $objectName) {

                    if ($value) {

                        $route = $this->getContainer()->get('router')->generate('core_prototype_defaultcontroller_read', array(
                            "id" => $row->getField($field . '.id'),
                            "containerName" => "container",
                            "actionId" => "default",
                            'entityName' => $objectName
                        ));
                        
                        $templating = $this->getContainer()->get('templating');
                        $link = $templating->render("TMSolutionDataGridBundle::grid.column.template.twig", ['value'=>strip_tags($value), 'route'=>$route]);
                        
                        return $link;
                    }
                });



                $grid->addColumn($column, $columnOrder = null);
                $fields[] = $realId;
            } else {
                $column = $grid->getColumn($field);
                $column->setTitle($this->getColumnTitle($this->objectName, $field));
                $fields[] = "{$field}";
            }

            if ($first) {
                $grid->setDefaultOrder($field, 'asc');
                $first = false;
            }
        }




        $grid->setVisibleColumns($fields);
        $grid->setColumnsOrder($fields);
    }

    protected function manipulateQuery($grid) {




        $tableAlias = $grid->getSource()->getTableAlias();

        $analizedFieldsInfo = $this->analizedFieldsInfo;
        $queryBuilderFn = function ($queryBuilder) use($tableAlias, $grid, $analizedFieldsInfo) {



            $queryBuilder->resetDQLPart('select');
            $queryBuilder->resetDQLPart('join');

            $fields = [];

            foreach ($analizedFieldsInfo as $field => $fieldParam) {

                if (array_key_exists('association', $fieldParam) && ($fieldParam['association'] == 'ManyToOne' || $fieldParam['association'] == 'OneToOne' )) {
                    $fields[] = "_{$field}.{$fieldParam['default_field']} as {$field}::{$fieldParam['default_field']}";
                    if($fieldParam['default_field']!='id'){
                        $fields[] = "_{$field}.id as {$field}::id";
                    }
                } else {

                    $fields[] = "{$tableAlias}.{$field}";
                }
            }


            $fieldsSql = implode(',', $fields);

            $queryBuilder->select($fieldsSql);

            foreach ($analizedFieldsInfo as $field => $fieldParam) {

                if (array_key_exists('association', $fieldParam) && ($fieldParam['association'] == 'ManyToOne' || $fieldParam['association'] == 'OneToOne' )) {

                    $queryBuilder->leftJoin("$tableAlias.{$field}", "_{$field}");
                }
            }


            if ($this->manyToManyRelationExists) {
                $queryBuilder->addGroupBy($tableAlias . '.id');
            }
        };








        $grid->getSource()->manipulateQuery($queryBuilderFn);
    }

    protected function configureRowButton($grid, $routePrefix) {


        $parametersArr = $this->request->attributes->all();
        $parameters = ["id", "containerName" => "container", "actionId" => "default"];
        $parameters = array_merge($parameters, $parametersArr["_route_params"]);



        $rowAction = new RowAction('glyphicon glyphicon-eye-open', $routePrefix . '_read', false, null, ['id' => 'button-id', 'class' => 'button-class lazy-loaded', 'data-original-title' => 'Show']);
        $rowAction->setRouteParameters($parameters);
        $grid->addRowAction($rowAction);

        $rowAction = new RowAction('glyphicon glyphicon-edit', $routePrefix . '_update', false, null, ['id' => 'button-id', 'class' => 'button-class lazy-loaded', 'data-original-title' => 'Edit']);
        $rowAction->setRouteParameters($parameters);
        $grid->addRowAction($rowAction);

        $rowAction = new RowAction('glyphicon glyphicon-remove', $routePrefix . '_delete', false, null, ['id' => 'button-id', 'class' => 'button-class lazy-loaded', 'data-original-title' => 'Delete']);
        $rowAction->setRouteParameters($parameters);
        $grid->addRowAction($rowAction);
    }

}
