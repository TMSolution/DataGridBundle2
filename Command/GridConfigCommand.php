<?php

/**
 * Copyright (c) 2014, TMSolution
 * All rights reserved.
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */

namespace TMSolution\DataGridBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;
use ReflectionClass;
use LogicException;
use UnexpectedValueException;

/**
 * GridConfigCommand generates widget class and his template.
 * @author Mariusz Piela <mariuszpiela@gmail.com>
 */
class GridConfigCommand extends ContainerAwareCommand
{

    protected $manyToManyRelationExists;
    protected $directory;
    protected $namespace;
    
    protected function configure()
    {
        $this->setName('datagrid:generate:grid:config')
                ->setDescription("Generate widget and template\r\n use --associated to create associated Grid Config")
                ->addArgument('entity', InputArgument::REQUIRED, 'Insert entity class name')
                ->addArgument('path', InputArgument::OPTIONAL, 'Insert path')
                ->addOption('associated', null, InputOption::VALUE_NONE, 'Insert associated param');

        $this->setHelp(<<<EOT
The <info>%command.name%</info> generuje pliki konfiguracyjne dla gridów.

W parametrze entity podajemy pełną nazwę encji.            
<info>%command.name% entity </info>

Alternatywnie można podać ścieżkę do folderu:

<info>%command.name% entity path </info>

Dla elementów podrzędnych podajemy parametr --associated

<info>%command.name% entity --associated</info>

EOT
        );
    }

    protected function getEntityName($input)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $entityName = str_replace('/', '\\', $input->getArgument('entity'));
        if (($position = strpos($entityName, ':')) !== false) {
            $entityName = $doctrine->getAliasNamespace(substr($entityName, 0, $position)) . '\\' . substr($entityName, $position + 1);
        }

        return $entityName;
    }

    protected function getClassPath($entityName)
    {
        $manager = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));
        $classPath = $manager->getClassMetadata($entityName)->getPath();
        return $classPath;
    }

    

    protected function createDirectory($classPath, $entityNamespace, $objectName, $path)
    {

        //    die($entityNamespace);
        if ($path) {
            $path = DIRECTORY_SEPARATOR . $path;
        }

        $this->directory = str_replace("\\", DIRECTORY_SEPARATOR, ($classPath . "\\" . $entityNamespace));
        $this->directory = $this->replaceLast("Entity", "Config". $path . DIRECTORY_SEPARATOR . $objectName, $this->directory);
       
        if (is_dir($this->directory) == false) {
            if (mkdir($this->directory, 0777, TRUE) == false) {
                throw new UnexpectedValueException("Creating directory failed");
            }
        }
    }
    
    protected function createNameSpace($entityNamespace, $objectName, $path)
    {

        //    die($entityNamespace);
        if ($path) {
            $path = DIRECTORY_SEPARATOR . $path;
        }

        $this->namespace = str_replace("\\", DIRECTORY_SEPARATOR, $entityNamespace);
        $this->namespace = $this->replaceLast("Entity", "Config" .$path. DIRECTORY_SEPARATOR . $objectName, $this->namespace);
        
        
    }

    protected function calculateFileName($entityReflection)
    {

        $fileName = $this->replaceLast("Entity", "Config", $entityReflection->getFileName());
        return $fileName;
    }

    protected function isFileNameBusy($fileName)
    {
        if (file_exists($fileName) == true) {
            throw new LogicException("File " . $fileName . " exists!");
        }
        return false;
    }

    protected function replaceLast($search, $replace, $subject)
    {
        $position = strrpos($subject, $search);
        if ($position !== false) {
            $subject = \substr_replace($subject, $replace, $position, strlen($search));
        }
        return $subject;
    }

    protected function analizeFieldName($fieldsInfo)
    {


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

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $entityName = $this->getEntityName($input);
        $model = $this->getContainer()->get("model_factory")->getModel($entityName);
        $fieldsInfo = $this->analizeFieldName($model->getFieldsInfo());
        $classPath = $this->getClassPath($entityName);
        $entityReflection = new ReflectionClass($entityName);
        $entityNamespace = $entityReflection->getNamespaceName();
        $objectName = $entityReflection->getShortName();
        $path = $input->getArgument('path');
        $this->createDirectory($classPath, $entityNamespace, $objectName, $path);
        $this->createNameSpace($entityNamespace, $objectName, $path);
        
        $fileName = $this->directory.DIRECTORY_SEPARATOR.'GridConfig.php';

        $objectName = $entityReflection->getShortName();
        $templating = $this->getContainer()->get('templating');

dump($objectName);
dump($fieldsInfo);
//exit;
       
        $this->isFileNameBusy($fileName);


        $associated = true === $input->getOption('associated');

        $renderedConfig = $templating->render("TMSolutionDataGridBundle:Command:gridconfig.template.twig", [
            "namespace" => $entityNamespace,
            "entityName" => $entityName,
            "objectName" => $objectName,
            "lcObjectName" => lcfirst($objectName),
            "fieldsInfo" => $fieldsInfo,
            "gridConfigNamespaceName" => $this->namespace,
            "associated" => $associated
        ]);

        file_put_contents($fileName, $renderedConfig);
        $output->writeln("Grid config generated");
    }

}
