<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Command;

use MedlabMG\YoushidoGraphQLExtendedBundle\Resolver\AbstractResolverField;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Youshido\GraphQL\Config\Object\ObjectTypeConfig;

class GraphQLTypesValidatorCommand extends ContainerAwareCommand
{
    /** @var OutputInterface  */
    private $output;

    /** @var bool */
    private $hasErrors = false;

    protected function configure()
    {
        $this
            ->setName('graphql:type:validator')
            ->setDescription('Validate if types are equal than response')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output      = $output;

        $this->executeByClass();
    }

    public function executeByClass()
    {
        $kernelRootDir = $this->getContainer()->getParameter('kernel.root_dir');

        foreach (glob($kernelRootDir . '/../src/MedlabMG/MedlabBundle/GraphQL/Type/*.php') as $fileName) {
            $className      = str_replace('.php', '', basename($fileName));
            $classNameSpace = "\\MedlabMG\\MedlabBundle\\GraphQL\\Type\\$className";

            $this->verifyClass($classNameSpace);
        }

        if ($this->hasErrors) {
            $this->output->writeln(
                "\n<ERROR>[GraphQL Types] KO </ERROR>"
            );
            return 1;
        }

        $this->output->writeln("<info>[GraphQL Types] OK - All required fields are the same than response</info>");
    }

    public function verifyClass($classGraphQLTypeNameSpace)
    {
        $class = new $classGraphQLTypeNameSpace();

        $objectTypeConfig =  new ObjectTypeConfig(['name' => 'test']);
        $class->build($objectTypeConfig);

        $entityClass = $this->guessEntityAndCreateFromGraphQLType($classGraphQLTypeNameSpace);

        $result = AbstractResolverField::serializeToArray($entityClass, $objectTypeConfig->getFields());
        $this->verifyResultWithFieldsRequired($result, array_keys($objectTypeConfig->getFields()), $classGraphQLTypeNameSpace);
    }

    private function guessEntityAndCreateFromGraphQLType($nameSpacesGraphQLType)
    {
        $defaultGraphqlTypesNameSpace = "\\MedlabMG\\MedlabBundle\\GraphQL\\Type\\";
        $defaultEntityNameSpace = "\\MedlabMG\\MedlabBundle\\Entity\\";

        $className = str_replace([$defaultGraphqlTypesNameSpace, 'Type'], '', $nameSpacesGraphQLType);

        $nameSpaceEntity = $defaultEntityNameSpace.$className;
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
