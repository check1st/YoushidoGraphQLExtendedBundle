<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Command;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Metadata\PropertyMetadata;
use MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\Naming\CamelCaseNamingStrategy;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\CompositeTypeInterface;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\DateTimeTzType;
use Youshido\GraphQL\Type\Scalar\FloatType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class GraphQLTypesGeneratorCommand extends ContainerAwareCommand
{
    /** @var OutputInterface  */
    private $output;

    /** @var bool  */
    private $forceOption;

    /** @var bool  */
    private $dump;

    /** @var bool  */
    private $recursive;

    /** @var array  */
    private $recursiveClassesVisited = [];

    protected function configure()
    {
        $this
            ->setName('graphql:type:generator')
            ->setDescription('GraphQL generator Types')
            ->addArgument('classNameSpace', InputArgument::REQUIRED, "Class")
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'remove if exist a file')
            ->addOption('dump', 'd', InputOption::VALUE_NONE, 'not save only print')
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Create all classes related')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output      = $output;
        $this->forceOption = $input->getOption('force');
        $this->dump        = $input->getOption('dump');
        $this->recursive   = $input->getOption('recursive');

        $this->executeByClass($input->getArgument('classNameSpace'));
    }

    public function executeByClass($classNameSpace)
    {
        if (in_array($classNameSpace, $this->recursiveClassesVisited)) {
            return;
        }

        $this->recursiveClassesVisited []= $classNameSpace;

        $this->output->writeln("\n<question>Loading $classNameSpace</question>\n");
        $defaultNameSpace = $this->getContainer()->getParameter('medlab.graphql.entity_path_default');

        if (!class_exists($classNameSpace)) {
            if (!class_exists($defaultNameSpace . $classNameSpace)) {
                throw new \RuntimeException("Class doesn't exist '$classNameSpace'");
            }
            $classNameSpace = $defaultNameSpace . $classNameSpace;
        }

        $arrayWithDefinitions = $this->getDefinitionFromEntity($classNameSpace);

        list($className, $nameSpace) = $this->getClassNameFromNamespace($classNameSpace);

        $absolutePath = $this->getContainer()->getParameter('kernel.root_dir') .
            '/../src/'. str_replace('\\','/', $nameSpace) . '/' . $className .'.php';

        if (!$this->dump && file_exists($absolutePath)){
            if (!$this->forceOption) {
                throw new \RuntimeException("file exist to overwrite add -f option");
            }
        }

        $template = $this->createTemplate($arrayWithDefinitions, $nameSpace, $className);
        if ($this->dump){
            $this->output->writeln("\n$template");
            return;
        }

        file_put_contents($absolutePath, $template);
        $this->output->writeln("\n<info>[+] - $absolutePath</info>");
    }

    public function getDefinitionFromEntity($classEntity)
    {
        $camelCaseNamingStrategy = new CamelCaseNamingStrategy();
        $reflectionClass = new \ReflectionClass($classEntity);
        $propertiesReflection = $reflectionClass->getProperties();

        $definitionType = [];

        foreach ($propertiesReflection as $propertyReflection) {

            if (!$this->isValidProperty($propertyReflection)) {
                continue;
            }

            $serializeName = $camelCaseNamingStrategy->translateName(
                new PropertyMetadata($classEntity, $propertyReflection->getName())
            );
            list($typeDoctrine, $isNullable, $isArray) = $this->getPropertyTypeFromPropertyReflection($classEntity, $propertyReflection);

            if (!$typeDoctrine) {
                continue;
            }

            $type = $this->getMetadataParseToGraphQLTypes($typeDoctrine);

            $definitionType[$serializeName] = [$type, $isNullable, $isArray];
        }

        return $definitionType ;
    }

    private function isValidProperty(\ReflectionProperty $reflectionProperty)
    {
        $reader = new AnnotationReader();

        /** @var Groups $groups */
        if (!$groups = $reader->getPropertyAnnotation($reflectionProperty, Groups::class)) {
            return false;
        }

        if (!in_array('GraphQL', $groups->groups)) {
            return false;
        }

        return true;
    }

    private function getPropertyTypeFromPropertyReflection($classEntity, \ReflectionProperty $propertyReflection)
    {
        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        $metadata = $em->getClassMetadata($classEntity);
        $type     = $metadata->getTypeOfField($propertyReflection->getName());

        $isArray = false;
        try{
            $isNullable = $em->getClassMetadata($classEntity)->isNullable($propertyReflection->getName());
        }catch (\Exception $e){
            $isNullable = true;
        }

        if (!$type && $metadata->hasAssociation($propertyReflection->getName())) {
            if ($infoAssoc = $metadata->getAssociationMapping($propertyReflection->getName())) {

                $type = new $infoAssoc['targetEntity'];

                if ($infoAssoc['type'] == ClassMetadataInfo::ONE_TO_MANY || $infoAssoc['type'] == ClassMetadataInfo::MANY_TO_MANY) {
                    $isArray = true;

                }else {

                    if (isset($infoAssoc['joinColumns']) && isset($infoAssoc['joinColumns'][0]) && isset($infoAssoc['joinColumns'][0]['nullable'])) {
                        $isNullable = $infoAssoc['joinColumns'][0]['nullable'];
                    }else{

                        if ($infoAssoc['isOwningSide']) {
                            $isNullable = false;
                        }

                    }
                }
            }
        }

        if (!$type) {
            $this->output->writeln("<error>Can't guess type from $classEntity->".$propertyReflection->getName()."</error>");
        }

        return [$type, $isNullable, $isArray];
    }

    private function getMetadataParseToGraphQLTypes($type)
    {
        if (is_object($type)) {
            return $type;
        }

        switch ($type) {
            case 'string':
                return new StringType();
            case 'date':
            case 'datetime':
                return new DateTimeTzType();
            case 'integer':
                return new IntType();
            case 'float':
                return new FloatType();
            case 'boolean':
                return new BooleanType();
            case 'array':
                return new ListType( new StringType() );
        }

        throw new \RuntimeException('Unknown type: '.$type);
    }

    private function createTemplate($arrayWithDefinitions, $nameSpace ,$className)
    {
        $definitionText = '';
        $i = 0;
        $imports=[];

        $padLength = 0;
        $keys = array_keys($arrayWithDefinitions);
        array_walk($keys, function($el) use (&$padLength) {
            if (strlen($el) > $padLength) {
                $padLength = strlen($el);
            }
        });

        $padLength += 2;

        foreach ($arrayWithDefinitions as $key => $arrayWithDefinition){

            list($type, $isNullable, $isArray) = $arrayWithDefinition;
            $extraDecoratorClass = $decoratorClass = '';

            if ($type instanceof ListType) {
                $extraDecoratorClass = $this->getGuestRightClass($type);
                $type = $type->getTypeOf();
            }

            if ($type instanceof CompositeTypeInterface) {
                $decoratorClass = $this->getGuestRightClass($type);
                $type = $type->getTypeOf();
            }

            $class = $this->getGuestRightClass($type);

            if (!in_array($class, $imports)) {
                $imports []= $class;
            }

            $decoratorClassShortName      = substr($decoratorClass, strrpos($decoratorClass, '\\') + 1);
            $extraDecoratorClassShortName = substr($extraDecoratorClass, strrpos($extraDecoratorClass, '\\') + 1);
            $classNameShortName           = substr($class, strrpos($class, '\\') + 1);


            if ($decoratorClassShortName) {
                $finalLoadClass = "new $decoratorClassShortName(new $classNameShortName())";
            }else{
                $finalLoadClass = "new $classNameShortName()";
            }

            if ($extraDecoratorClassShortName) {
                $finalLoadClass = "new $extraDecoratorClass($extraDecoratorClassShortName)";
            }

            if ($isArray) {
                $finalLoadClass = "new ListType($finalLoadClass)";
            }

            if (!$isNullable && !$isArray) {
                $finalLoadClass = "new NonNullType($finalLoadClass)";
            }


            $definitionText .= ($i > 0 ? "\n" : '')."            ".str_pad("'".$key."'", $padLength)." => $finalLoadClass,";
            $i++;
        }

        $importText = '';
        foreach ($imports as $import) {
            $importText .= "use $import;\n";
        }

        return <<<EOT
<?php

namespace $nameSpace;


use Youshido\GraphQL\Config\Object\ObjectTypeConfig;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\ListType\ListType;
$importText

class $className extends AbstractObjectType
{
    /**
     * @param ObjectTypeConfig \$config
     * @return void
     */
    public function build(\$config)
    {
        \$config->addFields([
$definitionText
        ]);
    }
}

EOT;

    }

    private function getGuestRightClass($class)
    {
        if ($class instanceof AbstractType) {
            return get_class($class);
        }

        list(, , $nameSpaceClass) = $this->getClassNameFromNamespace(get_class($class));

        if (!class_exists($nameSpaceClass)) {
            if ($this->recursive) {
                $this->executeByClass(get_class($class));
            }else{
                $this->output->writeln("<comment>[!] - $nameSpaceClass require to be created</comment>");
            }
        }


        return $nameSpaceClass;
    }

    private function getClassNameFromNamespace($classNameSpace)
    {
        $reflectionClass = new \ReflectionClass($classNameSpace);

        $className = $reflectionClass->getShortName().'Type';
        $nameSpace = 'MedlabMG\\MedlabBundle\\GraphQL\\Type';

        return [
            $className,
            $nameSpace,
            $nameSpace . '\\' . $className,
        ];
    }
}
