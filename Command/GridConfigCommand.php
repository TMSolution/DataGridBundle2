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
                ->addArgument('configBundle', InputArgument::REQUIRED, 'Insert config bundle name or entity path')
                ->addArgument('entity', InputArgument::REQUIRED, 'Insert entity class name')
                ->addArgument('path', InputArgument::OPTIONAL, 'Insert path')
                ->addArgument('rootFolder', InputArgument::OPTIONAL, 'Insert form type path')
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


    protected function createDirectory($classPath, $entityNamespace,$configEntityName, $objectName, $path, $rootFolder)
    {

        if ($path) {
            $entityNamespace = $entityNamespace . DIRECTORY_SEPARATOR . $path;
        }

        $directory = $this->replaceLast("Entity", "Config\\" . $rootFolder, str_replace("\\", DIRECTORY_SEPARATOR, ($classPath . "\\" . $entityNamespace)));

        if (is_dir($directory) == false) {
            if (mkdir($directory, 0777, true) == false) {
                throw new UnexpectedValueException("Creating directory failed: " . $directory);
            }
        }


        return $directory;
    }

    protected function createNameSpace($entityNamespace, $objectName, $path, $rootFolder,$configEntityName)
    {
/*
        if ($path) {
            $path = DIRECTORY_SEPARATOR . $path;
        }

        if ($rootFolder) {
            $rootFolder = DIRECTORY_SEPARATOR . $rootFolder;
        }



        $this->namespace = str_replace("\\", DIRECTORY_SEPARATOR, $configEntityName);
        
        $this->namespace = $this->replaceLast("Entity", "Config" . $rootFolder . $path , $this->namespace);

        dump( $this->namespace);
        
        return $this->namespace;
        
        */
        
        
        
        
        
        
        
        $directory = "Config\\".$rootFolder;
        if ($path) {
            $directory = str_replace(DIRECTORY_SEPARATOR, "\\", "Config\\".$rootFolder."\\" . $path);
        }
        $entityNameArr = explode("\\", str_replace("Entity", $directory, $configEntityName/*$entityName*/));
        unset($entityNameArr[count($entityNameArr) - 1]);
        
        $this->namespace=implode("\\", $entityNameArr);
        return $this->namespace;
        
        
        
        
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

    protected function addFile($fieldsInfo, $entityName, $path, $output, $associated = false, $rootFolder,$configEntityName)
    {

        $confgEntityReflection = new ReflectionClass($configEntityName);
        $configEntityNamespace = $confgEntityReflection->getNamespaceName();
        
        $classPath = $this->getClassPath($configEntityName);
        $entityReflection = new ReflectionClass($entityName);
        $entityNamespace = $entityReflection->getNamespaceName();
        $objectName = $entityReflection->getShortName();
        $lowerNameSpaceForTranslate = str_replace('bundle.entity', '', str_replace('\\', '.', strtolower($entityNamespace)));
        
        $this->directory = $this->createDirectory($classPath, /*$entityNamespace*/$configEntityNamespace,$configEntityName, $objectName, $path, $rootFolder);
        $namespace = $this->createNameSpace($entityNamespace, $objectName, $path, $rootFolder,$configEntityName);

        $fileName = $this->directory . DIRECTORY_SEPARATOR . 'GridConfig.php';

        $templating = $this->getContainer()->get('templating');
        $this->isFileNameBusy($fileName);

        $renderedConfig = $templating->render("TMSolutionDataGridBundle:Command:gridconfig.template.twig", [
            "namespace" => $entityNamespace,
            "entityName" => $entityName,
            "objectName" => $objectName,
            "lcObjectName" => lcfirst($objectName),
            "fieldsInfo" => $fieldsInfo,
            "gridConfigNamespaceName" => $namespace,
            "associated" => $associated,
            "lowerNameSpaceForTranslate" => $lowerNameSpaceForTranslate
        ]);

        file_put_contents($fileName, $renderedConfig);
        $output->writeln(sprintf("Grid config generated for <info>%s</info>", $entityName));
    }

    protected function runAssociatedObjects($fieldsInfo, $analyzeFieldsInfo, $entityName, $rootPath, $rootFolder, $output,$configEntityName)
    {
        $associations = [];
        foreach ($fieldsInfo as $key => $value) {

            $associationTypes = ["OneToMany", "ManyToMany"];
            $field = $fieldsInfo[$key];
            if (array_key_exists("association", $field) && in_array($field["association"], $associationTypes)) {

          
                $model = $this->getContainer()->get("model_factory")->getModel($value['object_name']);
                $assocObjectFieldsInfo = $model->getFieldsInfo();
                $assocObjectAnalyzeFieldsInfo = $this->analizeFieldName($assocObjectFieldsInfo);



                $arr = explode('\\', $value['object_name']);
                $path = array_pop($arr);

                $this->addFile($assocObjectAnalyzeFieldsInfo, $value['object_name'], $rootPath . DIRECTORY_SEPARATOR . $path, $output, TRUE, $rootFolder,$configEntityName);
            }
        }
    }
    
    protected function getConfigEntityName($input,$output)
    {
        $manager = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));
                
       
        try {

            $configBundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('configBundle'));
            $configBundleMetadata = $manager->getBundleMetadata($configBundle);
            $configMetadata = $configBundleMetadata->getMetadata();
            $configEntityName = $configMetadata[0]->getName();
        } catch (\InvalidArgumentException $e) {
            try {
                $configModel = $this->getContainer()->get("model_factory")->getModel($input->getArgument('configBundle'));
                $configMetadata = $configModel->getMetadata();
                $configEntityName = $configMetadata->getName();
            } catch (\Exception $e) {
                $output->writeln("<error>Argument configBundle:\"" . $input->getArgument('configBundle') . "\" not exist.</error>");
                exit;
            }
        }

        
        if (!$configEntityName) {
            $output->writeln("<error>Argument configEntityName not exist.</error>");
            exit;
        }
        
        return $configEntityName;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $entityName = $this->getEntityName($input);
        $path = $input->getArgument('path');
        $rootFolder = $input->getArgument('rootFolder');
        $associated = true === $input->getOption('associated');
        $model = $this->getContainer()->get("model_factory")->getModel($entityName);
        $fieldsInfo = $model->getFieldsInfo();
        $analyzeFieldsInfo = $this->analizeFieldName($model->getFieldsInfo());
        
        //configNameSpace
        $configEntityName=$this->getConfigEntityName($input,$output);

        $this->addFile($analyzeFieldsInfo, $entityName, $path, $output, FALSE, $rootFolder,$configEntityName);

        //generate assoc form types
        if (true === $input->getOption('associated')) {
            $this->runAssociatedObjects($fieldsInfo, $analyzeFieldsInfo, $entityName, $path, $rootFolder, $output,$configEntityName);
        }
    }

}
