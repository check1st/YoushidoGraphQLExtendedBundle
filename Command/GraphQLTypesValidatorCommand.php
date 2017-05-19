<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Command;

use MedlabMG\YoushidoGraphQLExtendedBundle\Resolver\AbstractResolverField;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Youshido\GraphQL\Config\Object\ObjectTypeConfig;

class GraphQLTypesValidatorCommand extends ContainerAwareCommand
{
    /** @var OutputInterface  */
    private $output;

    /** @var bool */
    private $hasErrors = false;

    /** @var string  */
    private $entityPath = null;

    /** @var string  */
    private $graphQLTypePath = null;

    protected function configure()
    {
        $this
            ->setName('graphql:type:validator')
            ->addOption('entityPath', null, InputOption::VALUE_OPTIONAL)
            ->addOption('graphQLTypePath', 'y', InputOption::VALUE_OPTIONAL)
            ->setDescription('Validate if types are equal than response')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output      = $output;

        $this->graphQLTypePath = $input->getOption('graphQLTypePath') ?: $this->getContainer()->getParameter(
            'medlab.graphql.types_path_default'
        );

        $this->entityPath = $input->getOption('entityPath') ?: $this->getContainer()->getParameter(
            'medlab.graphql.entity_path_default'
        );

        $this->executeByClass();
    }

    public function executeByClass()
    {
        $kernelRootDir = $this->getContainer()->getParameter('kernel.root_dir');

        $pathToSearchTypes = $kernelRootDir . '/../src/'.(str_replace('\\', '/', $this->graphQLTypePath)).'/*.php';

        foreach (glob($pathToSearchTypes) as $fileName) {
            $className      = str_replace('.php', '', basename($fileName));
            $classNameSpace = $this->graphQLTypePath . '\\'. $className;

            $this->verifyClass($classNameSpace);
        }

        if ($this->hasErrors) {
            $this->output->writeln(
                "\n<ERROR>[GraphQL Types] KO </ERROR>"
            );
            return 1;
        }

        $this->output->writeln("<info>[GraphQL Types] OK - All required fields are same than response</info>");
    }

    public function verifyClass($classGraphQLTypeNameSpace)
    {
        $class = new $classGraphQLTypeNameSpace();

        $objectTypeConfig =  new ObjectTypeConfig(['name' => 'test']);
        $class->build($objectTypeConfig);

        if (!$entityClass = $this->guessEntityAndCreateFromGraphQLType($classGraphQLTypeNameSpace)) {
            $this->output->writeln("<comment>[SKIP] $classGraphQLTypeNameSpace (entity not assoc)</comment>");
            return false;
        }

        $result = AbstractResolverField::serializeToArray($entityClass, $objectTypeConfig->getFields());
        $this->verifyResultWithFieldsRequired($result, array_keys($objectTypeConfig->getFields()), $classGraphQLTypeNameSpace);

        return true;
    }

    private function guessEntityAndCreateFromGraphQLType($nameSpacesGraphQLType)
    {
        $defaultEntityNameSpace = $this->entityPath . "\\";

        $className = basename(str_replace(['\\', 'Type'], ['/', ''], $nameSpacesGraphQLType));

        $nameSpaceEntity = $defaultEntityNameSpace.$className;

        if (!class_exists($nameSpaceEntity)) {
            return null;
        }

        return new $nameSpaceEntity();
    }

    private function verifyResultWithFieldsRequired(array $result, array $fieldsRequired, $classType)
    {
        $resultArrayKeys = array_keys($result);

        $diff = [];

        foreach ($fieldsRequired as $d) {
            if (!in_array($d, $resultArrayKeys)) {
                $diff[] = "$d";
            }
        }

        if ($diff) {
            $this->output->writeln("\n<error>Inconsistencies $classType</error>\n");
            $this->output->writeln(print_r($diff, true));
            $this->hasErrors = true;
        }

    }
}
