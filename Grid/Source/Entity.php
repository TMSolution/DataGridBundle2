<?php

/*
 * This file is part of the DataGridBundle.
 *
 * (c) Abhoryo <abhoryo@free.fr>
 * (c) Stanislav Turza
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TMSolution\DataGridBundle\Grid\Source;

use TMSolution\DataGridBundle\Grid\Column\Column;
use TMSolution\DataGridBundle\Grid\Column\DateTimeColumn;
use APY\DataGridBundle\Grid\Rows;
use APY\DataGridBundle\Grid\Row;
use APY\DataGridBundle\Grid\Source\Entity as ApySourceEntity;
//use APY\DataGridBundle\Grid\Source\Source as ApySourceEntity;
use APY\DataGridBundle\Grid\Helper\ORMCountWalker;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\HttpKernel\Kernel;
use TMSolution\DataGridBundle\Grid\Mapping\Metadata\Metadata;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Doctrine\ORM\Query\ResultSetMapping;

class Entity extends ApySourceEntity
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $manager;

    /**
     * @var \Doctrine\ORM\QueryBuilder
     */
    protected $query;

    /**
     * @var \Doctrine\ORM\QueryBuilder
     */
    protected $querySelectfromSource;

    /**
     * @var string e.g Vendor\Bundle\Entity\Page
     */
    protected $class;

    /**
     * @var string e.g Cms:Page
     */
    protected $entityName;

    /**
     * @var string e.g mydatabase
     */
    protected $managerName;

    /**
     * @var \APY\DataGridBundle\Grid\Mapping\Metadata\Metadata
     */
    protected $metadata;

    /**
     * @var \Doctrine\ORM\Mapping\ClassMetadata
     */
    protected $ormMetadata;

    /**
     * @var array
     */
    protected $joins;

    /**
     * @var string
     */
    protected $group;

    /**
     * @var string
     */
    protected $groupBy;

    /**
     * @var string
     */
    protected $groupById = false;

    /**
     * @var array
     */
    protected $hints;

    /**
     * The QueryBuilder that will be used to start generating query for the DataGrid
     * You can override this if the querybuilder is constructed in a business-specific way
     * by an external controller/service/repository and you wish to re-use it for the datagrid.
     * Typical use-case involves an external repository creating complex default restriction (i.e. multi-tenancy etc)
     * which then will be expanded on by the datagrid
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * The table alias that will be used in the query to fetch actual data
     * @var string
     */
    protected $tableAlias;

    /**
     * The table alias that will be used in the query to fetch actual data
     * @var string
     */
    protected $fields = array();
    protected $model = null;
    protected $reflectionClass;
    protected $totalCount1 = 3;
    protected $excludedColumns = [];

    /**
     * Legacy way of accessing the default alias (before it became possible to change it)
     * Please use $entity->getTableAlias() now instead of $entity::TABLE_ALIAS
     * @deprecated
     */
    const TABLE_ALIAS = '_a';

    protected $types = [
        "string" => "text",
        "text" => "text",
        "blob" => "text",
        "integer" => "number",
        "smallint" => "number",
        "bigint" => "number",
        "decimal" => "number",
        "float" => "number",
        "duble" => "number",
        "boolean" => "number",
        "datetime" => "datetime",
        "datetimetz" => "datetime",
        "date" => "datetime",
        "time" => "datetime",
        "array" => "array",
        "simple_array" => "array",
        "json_array" => "array",
    ];

    /**
     * @param string $entityName e.g Cms:Page
     * @param string $managerName e.g. mydatabase
     */
    /*
      public function __construct($entityName, $group = 'default', $managerName = null) {
      $this->entityName = $entityName;
      $this->managerName = $managerName;
      $this->joins = array();
      $this->group = $group;
      $this->hints = array();
      $this->setTableAlias(self::TABLE_ALIAS);
      } */

    public function setExcludedColumns($excludedColumns)
    {
        $this->excludedColumns = $excludedColumns;
    }

    public function getExcludedColumns()
    {
        return $this->excludedColumns;
    }

    public function __construct(\Core\ModelBundle\Model\Model $model, $group = 'default', $managerName = null, $metadata = null)
    {
        $this->model = $model;
        $this->entityName = $model->getEntityClass();
        $this->managerName = $managerName;
        $this->joins = array();
        $this->group = $group;
        $this->hints = array();
        $this->setTableAlias(self::TABLE_ALIAS);
        $this->metadata = $metadata;
        $this->reflectionClass = $this->model->getMetadata()->getReflectionClass();
        $this->createMetadata();
    }

    /**
     * Return model.
     * 
     * @return \Core\ModelBundle\Model\Model Model
     * @author Krzysiek Piasecki
     */
    public function getModel()
    {
        return $this->model;
    }

    public function initialise($container)
    {
        $doctrine = $container->get('doctrine');
        $this->manager = version_compare(Kernel::VERSION, '2.1.0', '>=') ? $doctrine->getManager($this->managerName) : $doctrine->getManager($this->managerName);
        $this->ormMetadata = $this->manager->getClassMetadata($this->entityName);

        $this->class = $this->ormMetadata->getReflectionClass()->name;


        /*
          if(empty($this->metadata)){
          $mapping = $container->get('grid.mapping.manager');

          $mapping->addDriver($this, -1);
          $this->metadata = $mapping->getMetadata($this->class, $this->group);
          } */

        $this->groupBy = $this->metadata->getGroupBy();
    }

    public function createQueryFilters($grid)
    {



        foreach ($this->metadata->getFields() as $field) {

            if (strstr($field, '.')) {
                $grid->getColumn($field)
                        ->setFilterType('select')
                        ->setSelectFrom('query')
                        ->setSortable(true);
            }
        }
    }

    /*
      public function setMetadata(\APY\DataGridBundle\Grid\Mapping\Metadata\Metadata $metadata){
      $this->metadata=$metadata;
      } */

    /**
     * @param \APY\DataGridBundle\Grid\Column\Column $column
     * @return string
     */
    /* protected function getFieldName($column, $withAlias = false) {
      $name = $column->getField();





      if (strpos($name, '.') !== false) {
      $previousParent = '';

      $elements = explode('.', $name);
      while ($element = array_shift($elements)) {
      if (count($elements) > 0) {
      $parent = ($previousParent == '') ? $this->getTableAlias() : $previousParent;
      $previousParent .= '_' . $element;




      $joinType = $column->getJoinType();



      $this->joins[$previousParent] = array('field' => $parent . '.' . $element, 'type' => $joinType);
      } else {
      $name = $previousParent . '.' . $element;
      }
      }

      $alias = str_replace('.', '::', $column->getId());
      } elseif (strpos($name, ':') !== false) {
      $previousParent = $this->getTableAlias();
      $alias = $name;
      } else {
      return $this->getTableAlias() . '.' . $name;
      }

      // Aggregate dql functions
      $matches = array();
      if ($column->hasDQLFunction($matches)) {
      if (strtolower($matches['parameters']) == 'distinct') {
      $functionWithParameters = $matches['function'] . '(DISTINCT ' . $previousParent . '.' . $matches['field'] . ')';
      } else {
      $parameters = '';
      if ($matches['parameters'] !== '') {
      $parameters = ', ' . (is_numeric($matches['parameters']) ? $matches['parameters'] : "'" . $matches['parameters'] . "'");
      }

      $functionWithParameters = $matches['function'] . '(' . $previousParent . '.' . $matches['field'] . $parameters . ')';
      }

      if ($withAlias) {
      // Group by the primary field of the previous entity
      $this->query->addGroupBy($previousParent);
      $this->querySelectfromSource->addGroupBy($previousParent);

      return "$functionWithParameters as $alias";
      }

      return $alias;
      }

      if ($withAlias) {
      return "$name as $alias";
      }

      if (strpos($name, '.') !== false) {
      return '_'.$name;
      }
      return $this->getTableAlias().'.'.$name;
      }
     */

    /**
     * @param string $fieldName
     * @return string
     */
    protected function getGroupByFieldName($fieldName)
    {
        if (strpos($fieldName, '.') !== false) {
            $previousParent = '';

            $elements = explode('.', $fieldName);
            while ($element = array_shift($elements)) {
                if (count($elements) > 0) {
                    $previousParent .= '_' . $element;
                } else {
                    $name = $previousParent . '.' . $element;
                }
            }
        } else {
            if (($pos = strpos($fieldName, ':')) !== false) {
                $fieldName = substr($fieldName, 0, $pos);
            }

            return $this->getTableAlias() . '.' . $fieldName;
        }

        return $name;
    }

    /**
     * @param \TMSolution\DataGridBundle\Grid\Columns $columns
     * @return null
     */
    public function getColumns($columns)
    {
        foreach ($this->metadata->getColumnsFromMapping($columns) as $column) {
            $columns->addColumn($column);
        }
    }

    protected function normalizeOperator($operator)
    {


        switch ($operator) {
            //case Column::OPERATOR_REGEXP:
            case Column::OPERATOR_LIKE:
            case Column::OPERATOR_LLIKE:
            case Column::OPERATOR_RLIKE:
            case Column::OPERATOR_NLIKE:
                return 'like';
            default:
                return $operator;
        }
    }

    protected function normalizeValue($operator, $value)
    {


        switch ($operator) {
            //case Column::OPERATOR_REGEXP:
            case Column::OPERATOR_LIKE:
            case Column::OPERATOR_NLIKE:
                if ($value instanceof \DateTime) {
                    return $value->format('Y-m-d') . "%";
                } else {
                    return "%$value%";
                }

            case Column::OPERATOR_LLIKE:
                return "%$value";
            case Column::OPERATOR_RLIKE:
                return "$value%";
            case Column::OPERATOR_BTW:
            case Column::OPERATOR_BTWE:
            default:
                return $value;
        }
    }

    /**
     * Sets the initial QueryBuilder for this DataGrid
     * @param QueryBuilder $queryBuilder
     */
    public function initQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = clone $queryBuilder;

        //Try to guess the new root alias and apply it to our queries+        
        //as the external querybuilder almost certainly is not used our default alias
        $externalTableAliases = $this->queryBuilder->getRootAliases();
        if (count($externalTableAliases)) {
            $this->setTableAlias($externalTableAliases[0]);
        }
    }

    /**
     * @return QueryBuilder
     */
    protected function getQueryBuilder()
    {
        //If a custom QB has been provided, use that
        //Otherwise create our own basic one
        if ($this->queryBuilder instanceof QueryBuilder) {
            $qb = $this->queryBuilder;
        } else {
            $qb = $this->manager->createQueryBuilder($this->class);
            $qb->from($this->class, $this->getTableAlias());
        }

        return $qb;
    }

    /**
     * 
     * @param type $columns
     * @param type $page
     * @param type $limit
     * @param type $maxResults
     * @param type $gridDataJunction
     * @return \APY\DataGridBundle\Grid\Rows
     */
    public function execute($columns, $page = 0, $limit = 0, $maxResults = null, $gridDataJunction = Column::DATA_CONJUNCTION)
    {

        $this->query = $this->getQueryBuilder();
        $this->querySelectfromSource = clone $this->query;
        $bindIndex = 123;
        $serializeColumns = array();
        $where = $gridDataJunction === Column::DATA_CONJUNCTION ? $this->query->expr()->andx() : $this->query->expr()->orx();




        foreach ($columns as $column) {
            if (!in_array($column->getId(), $this->excludedColumns)) {

                if ($column->getIsManualField() === false) {
                    $fieldName = $this->getFieldName($column, true);
                    $this->query->addSelect($fieldName);
                    $this->querySelectfromSource->addSelect($fieldName);
                }

                if ($column->isSorted()) {

                    //dump($this->getFieldName($column));
                    $this->query->orderBy($this->getFieldName($column), $column->getOrder());
                }

                if ($column->isFiltered()) {

                    // Some attributes of the column can be changed in this function
                    $filters = $column->getFilters('entity');
       
                    $isDisjunction = $column->getDataJunction() === Column::DATA_DISJUNCTION;

                    $hasHavingClause = $column->hasDQLFunction() || $column->getIsAggregate();
                    

                    $sub = $isDisjunction ? $this->query->expr()->orx() : ($hasHavingClause ? $this->query->expr()->andx() : $where);

                    foreach ($filters as $filter) {


                        // \Doctrine\Common\Util\Debug::dump($column);

                        $operator = $this->normalizeOperator($filter->getOperator());


                        $q = $this->query->expr()->$operator($this->getFieldName($column, false), "?$bindIndex");

                        if ($filter->getOperator() == Column::OPERATOR_NLIKE) {
                            $q = $this->query->expr()->not($q);
                        }
                        if ($filter->getValue() /* !== null */) {
                            $sub->add($q);


                           
                            $this->query->setParameter($bindIndex++, $this->normalizeValue($filter->getOperator(), $filter->getValue()));
                        }
                    }
             

                    if ($hasHavingClause) {
                        $this->query->andHaving($sub);
                    } elseif ($isDisjunction) {
                        $where->add($sub);
                    }
                }
                if ($column->getType() === 'array') {
                    $serializeColumns[] = $column->getId();
                }
            }
        }



        if ($where->count() > 0) {

            //Using ->andWhere here to make sure we preserve any other where clauses present in the query builder
            //the other where clauses may have come from an external builder
            $this->query->andWhere($where);
        }

        foreach ($this->joins as $alias => $field) {
            if (null !== $field['type'] && strtolower($field['type']) === 'inner') {
                $join = 'join';
            } else {
                $join = 'leftJoin';
            }

            $this->query->$join($field['field'], $alias);
            $this->querySelectfromSource->$join($field['field'], $alias);
        }

        if ($page > 0) {
            $this->query->setFirstResult($page * $limit);
        }

        if ($limit > 0) {
            if ($maxResults !== null && ($maxResults - $page * $limit < $limit)) {
                $limit = $maxResults - $page * $limit;
            }

            $this->query->setMaxResults($limit);
        } elseif ($maxResults !== null) {
            $this->query->setMaxResults($maxResults);
        }



        if (!empty($this->groupBy)) {
            $this->query->resetDQLPart('groupBy');
            $this->querySelectfromSource->resetDQLPart('groupBy');

            foreach ($this->groupBy as $field) {

                $this->query->addGroupBy($this->getGroupByFieldName($field));
                $this->querySelectfromSource->addGroupBy($this->getGroupByFieldName($field));
            }
        }



        if ($this->groupById === true) {

            $this->query->addGroupBy($this->getGroupByFieldName('id'));
        }

        //call overridden prepareQuery or associated closure
        $this->prepareQuery($this->query);

        $query = $this->query->getQuery();






        foreach ($this->hints as $hintKey => $hintValue) {
            $query->setHint($hintKey, $hintValue);
        }

//die($query->getSQL());

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, 'TMSolution\DataGridBundle\Walker\MysqlWalker');
        $query->setHint("mysqlWalker.count", true);

        $items = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        $this->prepareTotalCount();
//        $oids = array();
//        foreach ($items as $item) {
//
//            $oid = ObjectIdentity::fromDomainObject($item);
//            $oids[] = $oid;
//        }
//
//        $this->aclProvider->findAcls($oids); // preload Acls from database
//
//        foreach ($items as $item) {
//            if (false === $this->securityContext->isGranted('VIEW', $item)) {
//                // denied
//                throw new AccessDeniedException();
//            }
//        }
        // var_dump($items);
        $repository = $this->manager->getRepository($this->entityName);

        // Force the primary field to get the entity in the manipulatorRow
        $primaryColumnId = null;
        foreach ($columns as $column) {
            if ($column->isPrimary()) {
                $primaryColumnId = $column->getId();

                break;
            }
        }

        // hydrate result
        $result = new Rows();

        foreach ($items as $item) {
            $row = new Row();

            foreach ($item as $key => $value) {
                $key = str_replace('::', '.', $key);

                if (in_array($key, $serializeColumns) && is_string($value)) {
                    //echo $value."\n";
                    @$value = unserialize($value);
                }

                $row->setField($key, $value);
            }

            $row->setPrimaryField($primaryColumnId);

            //Setting the representative repository for entity retrieving
            $row->setRepository($repository);

            //call overridden prepareRow or associated closure
            if (($modifiedRow = $this->prepareRow($row)) != null) {
                $result->addRow($modifiedRow);
            }
        }



        return $result;
    }

    public function getTotalCount($maxResults = null)
    {



        //return 10;
        // From Doctrine\ORM\Tools\Pagination\Paginator::count()
        /* $countQuery = $this->query->getQuery();



          if (!$countQuery->getHint(ORMCountWalker::HINT_DISTINCT)) {
          $countQuery->setHint(ORMCountWalker::HINT_DISTINCT, true);
          }
          //$countQuery->setHint(Query::HINT_CUSTOM_TREE_WALKERS, array('APY\DataGridBundle\Grid\Helper\ORMCountWalker'));
          $countQuery->setFirstResult(null)->setMaxResults($maxResults);

          try {

          $data = $countQuery->getResult();


          //$data = array_map('current', $data);
          //$count = array_sum($data);
          $count = count($data);

          //echo "<pre>";
          } catch (NoResultException $e) {

          $count = 0;
          }
          ( */
        return $this->totalCount;
    }

    public function prepareTotalCount()
    {

        $sql = 'SELECT FOUND_ROWS() AS foundRows';
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('foundRows', 'foundRows');
        $query = $this->manager->createNativeQuery($sql, $rsm);

        $foundRows = $query->getResult();
        $results['foundRows'] = $foundRows[0]['foundRows'];

        // echo "<pre>";
        // \Doctrine\Common\Util\Debug::dump($results);
        // echo "</pre>";
        // exit;
        //die("daj".$results['foundRows']);



        $this->totalCount = (int) $results['foundRows'];
        //  echo "<h1>".$this->totalCount."</h1>";
    }

    /* public function getTotalCount($maxResults = null)
      {


      die($maxResults);


      $paginator = new Paginator($this->query->getQuery(), $fetchJoinCollection = true);

      return  count($paginator);
      } */

    public function getFieldsMetadata($class, $group = 'default')
    {
        $result = array();


        foreach ($this->ormMetadata->getFieldNames() as $name) {





            $mapping = $this->ormMetadata->getFieldMapping($name);
            //  $mapping['isAssociation']=false;




            $values = array('title' => $name, 'source' => true);

            if (isset($mapping['fieldName'])) {
                $values['field'] = $mapping['fieldName'];
                $values['id'] = $mapping['fieldName'];
            }

            if (isset($mapping['id']) && $mapping['id'] == 'id') {
                $values['primary'] = true;
            }




            switch ($mapping['type']) {
                case 'string':
                case 'text':
                    $values['type'] = 'text';
                    break;
                case 'integer':
                case 'smallint':
                case 'bigint':
                case 'float':
                case 'decimal':
                    $values['type'] = 'number';
                    break;
                case 'boolean':
                    $values['type'] = 'boolean';
                    break;
                case 'date':
                    $values['type'] = 'date';
                    break;
                case 'datetime':
                    $values['type'] = 'datetime';
                    break;
                case 'time':
                    $values['type'] = 'time';
                    break;
                case 'array':
                case 'object':
                    $values['type'] = 'array';
                    break;
            }

            $result[$name] = $values;
        }

        return $result;
    }

    protected function calculateAssociacion($field)
    {

        $fieldNameArr = explode('.', $field);
        if (count($fieldNameArr) == 2) {
            return $fieldNameArr;
        } else {
            return false;
        }
    }

    public function populateSelectFilters($columns, $loop = false)
    {

        foreach ($columns as $column) {
            $selectFrom = $column->getSelectFrom();

            if ($column->isFilterable() && $column->getFilterType() === 'select' && ($selectFrom === 'source' || $selectFrom === 'query')) {

                // For negative operators, show all values
                if ($selectFrom === 'query') {
                    foreach ($column->getFilters('entity') as $filter) {

                        if (in_array($filter->getOperator(), array(Column::OPERATOR_NEQ, Column::OPERATOR_NLIKE))) {
                            $selectFrom = 'source';
                            break;
                        }
                    }
                }

                // Dynamic from query or not ?
                // $query = ($selectFrom === 'source') ? clone $this->querySelectfromSource : clone $this->querySelectfromSource;




                $filedNameArr = $this->calculateAssociacion($column->getField());
                $values = array();
                if ($filedNameArr) {

                    $association = $this->fields[$filedNameArr[0]];




                    if ($association) {
                        $qb = $this->manager->createQueryBuilder();
                        $query = $qb->select('u.' . $filedNameArr[1])->from($association["targetEntity"], 'u')->getQuery();


                        /*
                          $query = $qb->select()->getQuery()->getSQL();
                          $query = $query->select($this->getFieldName($column, true))->fo
                          // ->distinct()

                          ->orderBy($this->getFieldName($column), 'asc')
                          ->setFirstResult(null)
                          ->setMaxResults(null)
                          ->getQuery();

                          die($query->getSQL()); */

                        $result = $query->getResult();

                        foreach ($result as $row) {
                            $value = $row[$filedNameArr[1]];

                            switch ($column->getType()) {
                                case 'array':
                                    if (is_string($value)) {
                                        $value = unserialize($value);
                                    }
                                    foreach ($value as $val) {
                                        $values[$val] = $val;
                                    }
                                    break;
                                case 'number':
                                    $values[$value] = $column->getDisplayedValue($value);
                                    break;
                                case 'datetime':
                                case 'date':
                                case 'time':
                                    $displayedValue = $column->getDisplayedValue($value);
                                    $values[$displayedValue] = $displayedValue;
                                    break;
                                default:
                                    $values[$value] = $value;
                            }
                        }
                    }
                }
                /*


                  echo "<pre>";
                  \Doctrine\Common\Util\Debug::dump($this->fields[]);
                  echo "</pre>";
                  exit;

                  //

                  echo $this->getFieldName($column, true).'<br/>';

                  $query = $query->select($this->getFieldName($column, true))
                  // ->distinct()

                  ->orderBy($this->getFieldName($column), 'asc')
                  ->setFirstResult(null)
                  ->setMaxResults(null)
                  ->getQuery();

                  die($query->getSQL());

                  $result   =   $query->getResult();
                 */

                //$result =[];
                // It avoids to have no result when the other columns are filtered
                if ($selectFrom === 'query' && empty($values) && $loop === false) {
                    $column->setSelectFrom('source');
                    $this->populateSelectFilters($columns, true);
                } else {
                    if ($column->getType() == 'array') {
                        natcasesort($values);
                    }

                    $column->setValues($values);
                }
            }
        }
    }

    public function delete(array $ids)
    {
        $repository = $this->getRepository();

        foreach ($ids as $id) {
            $object = $repository->find($id);

            if (!$object) {
                throw new \Exception(sprintf('No %s found for id %s', $this->entityName, $id));
            }

            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function getRepository()
    {
        return $this->manager->getRepository($this->entityName);
    }

    public function getHash()
    {
        return $this->entityName;
    }

    public function addHint($key, $value)
    {
        $this->hints[$key] = $value;
    }

    public function clearHints()
    {
        $this->hints = array();
    }

    /**
     *  Set groupby column
     *  @param string $groupBy GroupBy column
     */
    public function setGroupBy($groupBy)
    {
        $this->groupBy = $groupBy;
    }

    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @param string $tableAlias
     */
    public function setTableAlias($tableAlias)
    {
        $this->tableAlias = $tableAlias;
    }

    /**
     * @return string
     */
    public function getTableAlias()
    {
        return $this->tableAlias;
    }

    protected function initializeFields()
    {


        if (empty($this->fields)) {
            foreach ($this->reflectionClass->getProperties() as $property) {



                if ($this->model->getMetadata()->hasField($property->name) && $this->checkProperty($property->name)) {

                    $mapping = $this->model->getMetadata()->getFieldMapping($property->name);

                    $this->fields[$mapping['fieldName']] = array('fieldName' => $mapping['fieldName'], 'isAssociation' => false, 'mapping' => $mapping, 'targetEntity' => null);
                } else {
                    if ($this->model->getMetadata()->hasAssociation($property->name)) {


                        $mapping = $this->model->getMetadata()->getAssociationMapping($property->name);


                        $this->fields[$mapping['fieldName']] = array('fieldName' => $mapping['fieldName'], 'isAssociation' => true, 'mapping' => $mapping, 'targetEntity' => $mapping['targetEntity']);
                    }
                }
            }
        }

        return $this->fields;
    }

    public function getField($name)
    {
        $this->initializeFields();
        return $this->fields[$name];
    }

    public function hasAssociatedObjectField($associatedFieldName, $fieldName)
    {
        $associatedField = $this->getField($associatedFieldName);
        $metadata = $this->model->getManager()->getClassMetadata($associatedField["mapping"]["targetEntity"]);
        if ($metadata->hasField($fieldName)) {
            return true;
        }
        return false;
    }

    /**
     * Zwraca tablicę metadata do konfiguracji grida.
     * UWAGA! To nie jest Metadata z Doctrina
     */
    public function createMetadata()
    {


        $fields = $this->initializeFields();
        $this->metadata = new Metadata($this);
        $fieldsNames = array();
        $fieldsMappings = array();

        foreach ($fields as $field) {

            // var_dump($field);

            if ($field["isAssociation"] === true) {
                /*

                  if ($this->hasAssociatedObjectField($field['fieldName'], "name")) {

                  $fieldName = $field['fieldName'] . ".name";
                  $fieldsNames[] = $fieldName;

                  $type = 'text';

                  if ($field['mapping']['type'] == ClassMetadata::MANY_TO_MANY) {
                  $this->groupById = true;
                  }

                  $fieldsMappings[$fieldName] = array("title" => $fieldName, "source" => true, "field" => $fieldName, "id" => $fieldName, "type" => $type, 'associated' => true);
                  } */
            } else {


                $fieldName = $field['fieldName'];
                $fieldsNames[] = $field['fieldName'];

                // echo $fieldName." = ".$field['mapping']['type']."\n";
                $fieldMapping = array("title" => $fieldName, "source" => true, "field" => $fieldName, "id" => $fieldName, "type" => $this->types[$field['mapping']['type']], 'associated' => false);

                if ($fieldName == 'id') {
                    $fieldMapping["primary"] = true;
                }
                $fieldsMappings[$fieldName] = $fieldMapping;
            }
        }



        $this->metadata->setFields($fieldsNames);
        $this->metadata->setFieldsMappings($fieldsMappings);
        $this->metadata->setGroupBy(array());
        return $this->metadata;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    private function camelize($string)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) {
            return ('.' === $match[1] ? '_' : '') . strtoupper($match[2]);
        }, $string);
    }

    private function checkProperty($property)
    {

        $camelProp = $this->camelize($property);
        //$this->reflectionClass = new \ReflectionClass($object);
        $getter = 'get' . $camelProp;
        // $setter = 'set' . $camelProp;
        $isser = 'is' . $camelProp;
        $hasser = 'has' . $camelProp;
        $classHasProperty = $this->reflectionClass->hasProperty($property);

        // var_dump($getter);

        if ($this->reflectionClass->hasMethod($getter) && $this->reflectionClass->getMethod($getter)->isPublic()) {
            return true;
        } elseif ($this->reflectionClass->hasMethod($isser) && $this->reflectionClass->getMethod($isser)->isPublic()) {
            return true;
        } elseif ($this->reflectionClass->hasMethod($hasser) && $this->reflectionClass->getMethod($hasser)->isPublic()) {
            return true;
        } elseif ($this->reflectionClass->hasMethod('__get') && $this->reflectionClass->getMethod('__get')->isPublic()) {
            return true;
        } elseif ($classHasProperty && $this->reflectionClass->getProperty($property)->isPublic()) {
            return true;
        }

        return false;
    }

}
