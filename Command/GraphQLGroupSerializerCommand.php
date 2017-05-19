<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Command;

use Doctrine\Common\Annotations\AnnotationReader;
use JMS\Serializer\Annotation\Groups;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GraphQLGroupSerializerCommand extends ContainerAwareCommand
{
    /** @var OutputInterface  */
    private $output;

    /** @var bool  */
    private $dump;

    const GROUP_TO_INSERT = 'GraphQL';
    const ANNOTATION_NAME = '@Serializer\Groups';
    const ANNOTATION_TO_INSERT = "     * @Serializer\\Groups({%s})\n";
    const GROUPS_TO_INSERT = "\"Default\", \"GraphQL\"";
    const IMPORT_REQUIRED = 'use JMS\Serializer\Annotation as Serializer;';

    protected function configure()
    {
        $this
            ->setName('graphql:serializer:add_group')
            ->setDescription('Add group')
            ->addArgument('classNameSpace', InputArgument::REQUIRED, "Class")
            ->addOption('dump', 'd', InputOption::VALUE_NONE, 'not save only print')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->dump   = $input->getOption('dump');

        $this->executeByClass($input->getArgument('classNameSpace'));
    }

    public function executeByClass($classNameSpace)
    {
        $defaultNameSpace = $this->getContainer()->getParameter('medlab.graphql.entity_path_default');

        if (!class_exists($classNameSpace)) {
            $defaultNameSpace = $defaultNameSpace .'\\'. $classNameSpace;
            if (!class_exists($defaultNameSpace)) {
                throw new \RuntimeException("Class doesn't exist '$classNameSpace'");
            }
            $classNameSpace = $defaultNameSpace;
        }

        list($propertiesToInsert, $propertiesToAddGroup) = $this->propertiesToInsertGroup($classNameSpace);
        $fileText = $this->getTextClassWithGroupsAdded($classNameSpace, $propertiesToInsert, $propertiesToAddGroup);

        if (!$fileText) {
            $this->output->writeln("<comment>[!] File not updated, $classNameSpace</comment>");
            return 0;
        }

        if ($this->dump) {
            $this->output->writeln($fileText);
            return 0;
        }

        file_put_contents($this->getAbsolutePath($classNameSpace), $fileText);
        $this->output->writeln("<info>Updated $classNameSpace</info>");
    }

    /**
     * @param $classNameSpace
     * @return array
     */
    private function propertiesToInsertGroup($classNameSpace)
    {
        $reflectionClass = new \ReflectionClass($classNameSpace);
        $properties = $reflectionClass->getProperties();
        $reader = new AnnotationReader();

        $propertiesToInsert   = [];
        $propertiesToAddGroup = [];

        foreach ($properties as $reflectionProperty) {
            /** @var Groups $groups */
            if ($groups = $reader->getPropertyAnnotation($reflectionProperty, Groups::class)) {

                if (in_array('GraphQL', $groups->groups)) {
                    continue;
                }

                $propertiesToAddGroup []= $reflectionProperty;
                $this->output->writeln('Property To update groups '. $reflectionProperty->getName(), OutputInterface::VERBOSITY_VERBOSE);
                continue;
            }

            $this->output->writeln('Property Detected '. $reflectionProperty->getName() , OutputInterface::VERBOSITY_VERBOSE);
            $propertiesToInsert []= $reflectionProperty;
        }

        return [$propertiesToInsert, $propertiesToAddGroup];
    }

    /**
     * @param $classEntity
     * @param \ReflectionProperty[] $propertiesReflection
     * @param \ReflectionProperty[] $propertiesToUpdateGroups
     * @return string|null
     */
    public function getTextClassWithGroupsAdded($classEntity, $propertiesReflection, $propertiesToUpdateGroups)
    {
        if (!$propertiesReflection) {
            return null;
        }

        $handle = fopen($this->getAbsolutePath($classEntity), "r");
        $newFileArr = [];

        $iNewFile = $iProperty = $iPropertyGroup = 0;

        $currentProperty = $propertiesReflection[$iProperty++];

        $currentPropertyUpdateGroup = null;

        if (isset($propertiesToUpdateGroups[$iPropertyGroup])) {
            $currentPropertyUpdateGroup = $propertiesToUpdateGroups[$iPropertyGroup++];
        }

        while (($line = fgets($handle)) !== false) {
            if($currentProperty && preg_match('/\$'.$currentProperty->getName().'/', $line)) {

                $closeAnnotation = $newFileArr[$iNewFile-1];
                $newFileArr[$iNewFile-1]= sprintf(self::ANNOTATION_TO_INSERT, self::GROUPS_TO_INSERT);
                $newFileArr[$iNewFile++]= $closeAnnotation;

                $currentProperty = null;
                if (isset($propertiesReflection[$iProperty])) {
                    $currentProperty = $propertiesReflection[$iProperty++];
                }
            }

            if($currentPropertyUpdateGroup && preg_match('/\$'.$currentPropertyUpdateGroup->getName().'/', $line)) {

                if (!$serializationAnnotationBefore = $this->getLastSerializeGroupAndDelete($newFileArr)) {
                    throw new \RuntimeException('Serialization annotation not found, from property '.$currentPropertyUpdateGroup->getName());
                }

                $groupsBefore = $this->getGroupsFromAnnotation($serializationAnnotationBefore);
                $closeAnnotation = $newFileArr[$iNewFile-1];
                $newFileArr[$iNewFile-1]= sprintf(self::ANNOTATION_TO_INSERT, $groupsBefore.', "'.self::GROUP_TO_INSERT.'"');
                $newFileArr[$iNewFile++]= $closeAnnotation;

                $currentPropertyUpdateGroup = null;
                if (isset($propertiesToUpdateGroups[$iPropertyGroup])) {
                    $currentPropertyUpdateGroup = $propertiesToUpdateGroups[$iPropertyGroup++];
                    $this->output->writeln('Searching now for Group '. $currentPropertyUpdateGroup->getName(), OutputInterface::VERBOSITY_VERBOSE);
                }
            }

            $newFileArr[$iNewFile++]= $line;
        }

        $newFileArr = $this->addImportIfItsRequired($newFileArr);

        $newFile = '';

        foreach ($newFileArr as $line) {
            $newFile .= $line;
        }

        return $newFile;
    }

    private function getLastSerializeGroupAndDelete(&$arrFile)
    {
        for ($i = count($arrFile)-1; $i> 0; $i--) {
            if (preg_match('/'.preg_quote(self::ANNOTATION_NAME).'/', $arrFile[$i])) {
                $value = $arrFile[$i];
                unset($arrFile[$i]);
                return $value;
            }
        }

        return null;
    }

    private function getGroupsFromAnnotation($annotationBefore)
    {
        preg_match('/\{(.+?)\}/', $annotationBefore, $matches);
        return $matches[1];
    }

    private function getAbsolutePath($classEntity)
    {
        return $this->getContainer()->getParameter('kernel.root_dir') .
            '/../src/'. str_replace('\\','/', $classEntity) .'.php';
    }

    private function addImportIfItsRequired($newFileArr)
    {
        $importExist = array_filter($newFileArr, function($var) { return preg_match("/".preg_quote(self::IMPORT_REQUIRED)."/", $var); });

        if ($importExist) {
            $this->output->writeln('Import was inserted before', OutputInterface::VERBOSITY_VERBOSE);
            return $newFileArr;
        }

        $nameSpaceFound = false;
        $countAfterFound = 0;
        $countAfterFoundToInsert = 3;

        foreach ($newFileArr as $index => $line) {
            if (!$nameSpaceFound && preg_match('/namespace/', $line)) {
                $nameSpaceFound = true;
            }

            if ($nameSpaceFound) {
                $countAfterFound++;
            }

            if ($countAfterFound === $countAfterFoundToInsert) {
                array_splice($newFileArr, $index, 0, self::IMPORT_REQUIRED."\n\n");
                return $newFileArr;
            }
        }

        throw new \RuntimeException('Cant import serializer');
    }
}
