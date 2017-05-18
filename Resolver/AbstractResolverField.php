<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Resolver;

use Doctrine\Common\Annotations\AnnotationReader;
use JMS\Serializer\Handler\DateHandler;
use JMS\Serializer\Handler\HandlerRegistry;
use JMS\Serializer\Naming\SerializedNameAnnotationStrategy;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use MedlabMG\MedlabBundle\Entity\User;
use MedlabMG\MedlabBundle\Exception\MedLabValidationEntityException;
use MedlabMG\YoushidoGraphQLExtendedBundle\Annotation\SecurityGraphQL;
use MedlabMG\YoushidoGraphQLExtendedBundle\Exception\GraphQLValidatorException;
use MedlabMG\YoushidoGraphQLExtendedBundle\Resolver\ParamBag;
use MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\ExclusionStrategy\ReadGraphQLFields;
use MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\Naming\CamelCaseNamingStrategy;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQLBundle\Field\AbstractContainerAwareField;

abstract class AbstractResolverField extends AbstractContainerAwareField
{
    /** @var ContainerInterface */
    protected $container;

    /** @var  ConstraintViolationList */
    protected $violations;

    /** @var ParamBag */
    protected $args;

    /** @var SerializerBuilder */
    static public $serializer;

    protected function buildParent(FieldConfig $config)
    {

    }

    public final function build(FieldConfig $config)
    {
        $this->buildParent($config);
        $annotationReader = new AnnotationReader();
        /** @var SecurityGraphQL $annotation */
        $annotation = $annotationReader->getClassAnnotation(new \ReflectionClass(static::class), SecurityGraphQL::class);

        if ($annotation) {
            $newDescription = $config->get('description') . "\n\n__One of these roles are required__: \n";
            foreach ($annotation->groups as $group) {
                $newDescription .= "\n * *$group*";
            }
            $config->setDescription($newDescription);
        }
    }

    abstract protected function resolveParent($value, ResolveInfo $info);

    public final function resolve($value, array $args, ResolveInfo $info)
    {
        $this->violations = new ConstraintViolationList();

        $this->args = new ParamBag($args);

        try{
            $result = $this->resolveParent($value, $info);
        }catch (MedLabValidationEntityException $e){
            $this->throwFormViolationsException($e->getViolationList());
        }

        if (is_scalar($result)) {
            return $result;
        }

        return self::serializeToArray($result, $info->getFieldASTList());
    }

    static public function serializeToArray($elementToSerialize, $fieldsRequired)
    {
        if (!self::$serializer) {
            self::$serializer = SerializerBuilder::create()

                ->configureHandlers(function (HandlerRegistry $handlerRegistry){
                    $handlerRegistry->registerSubscribingHandler(new DateHandler('D, d M Y H:i:s O'));
                })
                ->setPropertyNamingStrategy(new SerializedNameAnnotationStrategy(new CamelCaseNamingStrategy()))
                ->build();

        }

        $context = new SerializationContext();
        $context
            ->setGroups('GraphQL')
            ->setSerializeNull(true)
            ->addExclusionStrategy(new ReadGraphQLFields($fieldsRequired))
        ;

        return self::$serializer->toArray($elementToSerialize, $context);
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEM()
    {
        return $this->container->get('doctrine.orm.default_entity_manager');
    }

    /**
     * @return ValidatorInterface
     */
    public function getValidator()
    {
        return $this->container->get('validator');
    }

    /**
     * Get a user from the Security Token Storage.
     *
     * @return User
     *
     * @throws \LogicException If SecurityBundle is not available
     *
     * @see TokenInterface::getUser()
     */
    protected function getUser()
    {
        if (!$this->container->has('security.token_storage')) {
            throw new \LogicException('The SecurityBundle is not registered in your application.');
        }

        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        if ($user && $this->container->getParameter('kernel.environment') === 'test') {
            // This "refresh" is required because doctrine can't get current user correctly in our tests
            $user = $this->getEM()->getRepository("MedlabMGMedlabBundle:User")->find($user->getId());
        }

        return $user;
    }

    protected function createFormApi($type, $data = null, array $options = array())
    {
        return $this->container->get('form.factory')->createNamed('', $type, $data, $options);
    }

    protected function getParameter($parameter)
    {
        return $this->container->getParameter($parameter);
    }

    /**
     * Gets a container service by its id.
     *
     * @param string $id The service id
     *
     * @return object The service
     */
    protected function get($serviceName)
    {
        return $this->container->get($serviceName);
    }

    protected function addViolation($message, $inputName, $code=null, $valueInserted = null)
    {
        $this->violations->add(
            new ConstraintViolation(
                $message,
                '',
                [],
                null,
                $inputName,
                $valueInserted ?: $this->args->get($inputName),
                null,
                $code
            )
        );
    }

    protected function verifyCurrentErrors($violations = null)
    {
        $violations = $violations ?: $this->violations;


        if ($violations->count() > 0 ) {
            $this->throwFormViolationsException($this->violations);
        }
    }

    /**
     * @param $violations
     * @throws GraphQLValidatorException
     */
    protected function throwFormViolationsException($violations)
    {
        throw new GraphQLValidatorException($this->getName(), $violations);
    }
}