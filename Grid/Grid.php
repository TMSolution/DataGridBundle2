<?php

namespace TMSolution\DataGridBundle\Grid;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use APY\DataGridBundle\Grid\Action\MassActionInterface;
use TMSolution\DataGridBundle\Grid\Action\RowActionInterface;
use TMSolution\DataGridBundle\Grid\Column\Column;
use APY\DataGridBundle\Grid\Column\MassActionColumn;
use TMSolution\DataGridBundle\Grid\Column\ActionsColumn;
use APY\DataGridBundle\Grid\Source\Source;
use APY\DataGridBundle\Grid\Export\ExportInterface;
use TMSolution\DataGridBundle\Grid\Column\DateTimeColumn;
use APY\DataGridBundle\Grid\Grid AS BaseGrid;

class Grid extends BaseGrid
{

    //TMSolution - ilość prezentowanych kolumn filtra
    protected $numberPresentedFilterColumn = 3;
    protected $bootstrapColumnSize = 4;
    protected $boldColumns = array();
    protected $activeColumns = array();
    protected $extraFilter = false;
    protected $resetSessionData = false;
    protected $disablePager = false;

    // protected $showFilters = false;

    /**
     * @param \Symfony\Component\DependencyInjection\Container $container
     * @param string $id set if you are using more then one grid inside controller
     */
    public function __construct($container, $id = '')
    {
        
        
        parent::__construct($container, $id);
        
      
    }
    
    /*UWAGA ZMIENIANE SET*/
    protected function set($key, $data)
    {
        // Only the filters values are removed from the session
        
        
        if (isset($data['from']) && ((is_string($data['from']) && $data['from'] === '') || (is_array($data['from']) && count($data['from']) == 0))) {
            if (array_key_exists($key, $this->sessionData)) {
                unset($this->sessionData[$key]);
            }
        } elseif ($data !== null) {
            $this->sessionData[$key] = $data;
        }
    }
    
     protected function processSessionData()
    {
        // Filters 
        
        foreach ($this->columns as $column) {
            if (($data = $this->get($column->getId())) !== null) {
                $column->setData($data);
               
            }
        }

        // Page
        if (($page = $this->get(self::REQUEST_QUERY_PAGE)) !== null) {
            $this->setPage($page);
        } else {
            $this->setPage(0);
        }

        // Order
        if (($order = $this->get(self::REQUEST_QUERY_ORDER)) !== null) {
            list($columnId, $columnOrder) = explode('|', $order);

            $this->columns->getColumnById($columnId)->setOrder($columnOrder);
        }

        // Limit
        if (($limit = $this->get(self::REQUEST_QUERY_LIMIT)) !== null) {
            $this->limit = $limit;
        } else {
            $this->limit = key($this->limits);
        }
    }

    public function setBoldColumns($columnsNames)
    {
        $this->boldColumns = $columnsNames;
    }

    public function setActiveColumns($activeColumns)
    {

        $this->activeColumns = $activeColumns;
    }

    /*
     * Metoda ustawia ilość column filtra
     */

    public function setNumberPresentedFilterColumn($value)
    {
        $this->numberPresentedFilterColumn = $value;
        $this->setBootstrapColumnSize();
    }

    public function getNumberPresentedFilterColumn()
    {
        return $this->numberPresentedFilterColumn;
    }

    public function setBootstrapColumnSize()
    {
        $this->bootstrapColumnSize = floor(12 / $this->numberPresentedFilterColumn);
    }

    public function getBootstrapColumnSize()
    {
        return $this->bootstrapColumnSize;
    }

    public function setDisablePager($disablePager)
    {
        $this->disablePager = $disablePager;
    }

   
    
    public function getDisablePager()
    {
        return $this->disablePager;
    }
  

    /*
     * Metoda zwraca ilość wierszy w kolumnie filtra
     */

    public function filterRowsPerColumn()
    {
        $counter = 0;
        foreach ($this->columns as $column) {
            if ($column->isFilterable() && $column->getId() !== '__actions') {
                $counter++;
            }
        }
        return ceil($counter / $this->numberPresentedFilterColumn);
    }

    /* Metoda ukrywa kolumny z filtra
     * @param columns array('columnname','columnname'...)
     */

    public function setHideFilters($columns = array())
    {
        foreach ($columns as $column) {
            $this->getColumn($column)->setFilterable(false);
        }
    }

    /**
     * Pokazuje tylko wybrane filtry.
     * 
     * @author Krzysztof Piasecki
     * @param array $columns Lista kolumn
     * @throws \InvalidArgumentException
     */
    public function setShowFilters(array $columns = [])
    {
        if (count($columns) == 0) {
            throw new \InvalidArgumentException("Array of filters is empty");
        }
        foreach ($this->columns as $col) {
            if (in_array($col->getId(), $columns) == false) {
                $col->setFilterable(false);
            }
        };
    }

    /* Metoda ukrywa kolumny z filtra
     * wymaga wpisu w annotations:
     * @GRID\Column(field="$columnName.$fieldName", visible="true")
     * 
     * @param columns array('columnname'=>'fieldname','columnname'=>'fieldname'...)
     */

    public function setQueryFilters($columns = array())
    {
        foreach ($columns as $columnName => $fieldName) {
            $this->getColumn($columnName . '.' . $fieldName)
                    ->setFilterType('select')
                    ->setSelectFrom('query')
                    ->setSortable(false);
        }
    }

    public function setSource(Source $source)
    {
        if ($this->source !== null) {
            throw new \InvalidArgumentException('The source of the grid is already set.');
        }

        $this->source = $source;

        $this->source->initialise($this->container);

        // Get columns from the source

        $this->source->getColumns($this->columns);

        $this->source->createQueryFilters($this);


        return $this;
    }

    public function setNonORMSource(Source $source)
    {
        if ($this->source !== null) {
            throw new \InvalidArugmentException('The source is allready set');
        }

        $this->source = $source;

        $this->source->initialise($this->container);
        $this->source->getColumns($this->columns);
        return $this;
    }

    public function setExtraFilterOn()
    {
        $this->extraFilter = true;
    }

    public function setExtraFilterOff()
    {
        $this->extraFilter = false;
    }

    public function isExtraFilterOn()
    {
        return $this->extraFilter;
    }

    public function resetSessionData()
    {

        $this->createHash();
        $this->session->remove($this->hash);
        $this->newSession = true;
        $this->sessionData = array();
    }

    /**
     * Prepare Grid for Drawing
     *
     * @return self
     *
     * @throws \Exception
     */
    protected function prepare()
    {


        if ($this->prepared) {

            return $this;
        }

        if ($this->source->isDataLoaded()) {

            $this->rows = $this->source->executeFromData($this->columns->getIterator(true), $this->page, $this->limit, $this->maxResults);
        } else {

            $this->rows = $this->source->execute($this->columns->getIterator(true), $this->page, $this->limit, $this->maxResults, $this->dataJunction);
        }



        //echo $this->totalCount;
        // die('-');


        if (!$this->rows instanceof \APY\DataGridBundle\Grid\Rows) {
            throw new \Exception('Source have to return Rows object.');
        }

        if (count($this->rows) == 0 && $this->page > 0) {
            $this->page = 0;
            $this->prepare();

            return $this;
        }

        //add row actions column
        if (count($this->rowActions) > 0) {
            foreach ($this->rowActions as $column => $rowActions) {
                if (($actionColumn = $this->columns->hasColumnById($column, true))) {
                    $actionColumn->setRowActions($rowActions);
                } else {
                    $actionColumn = new ActionsColumn($column, $this->actionsColumnTitle, $rowActions);
                    if ($this->actionsColumnSize > -1) {
                        $actionColumn->setSize($this->actionsColumnSize);
                    }

                    $this->columns->addColumn($actionColumn);
                }
            }
        }

        //add mass actions column
        if (count($this->massActions) > 0) {
            $this->columns->addColumn(new MassActionColumn(), 1);
        }

        $primaryColumnId = $this->columns->getPrimaryColumn()->getId();

        foreach ($this->rows as $row) {
            $row->setPrimaryField($primaryColumnId);
        }

        //@todo refactor autohide titles when no title is set
        if (!$this->showTitles) {
            $this->showTitles = false;
            foreach ($this->columns as $column) {
                if (!$this->showTitles) {
                    break;
                }

                if ($column->getTitle() != '') {
                    $this->showTitles = true;

                    break;
                }
            }
        }

        //get size
        if ($this->source->isDataLoaded()) {
            $this->source->populateSelectFiltersFromData($this->columns);
            $this->totalCount = $this->source->getTotalCountFromData($this->maxResults);
        } else {
            $this->source->populateSelectFilters($this->columns);
            $this->totalCount = (int) $this->source->getTotalCount($this->maxResults);
        }

        if (!is_int($this->totalCount)) {
            throw new \Exception(sprintf('Source function getTotalCount need to return integer result, returned: %s', gettype($this->totalCount)));
        }

        $this->prepared = true;

        return $this;
    }

   

}
