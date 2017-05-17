<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Security\Voter;

use Doctrine\Common\Annotations\AnnotationReader;
use MedlabMG\YoushidoGraphQLExtendedBundle\Annotation\SecurityGraphQL;
use MedlabMG\YoushidoGraphQLExtendedBundle\Resolver\AbstractFieldMutation;
use MedlabMG\YoushidoGraphQLExtendedBundle\Resolver\AbstractFieldQuery;
use MedlabMG\YoushidoGraphQLExtendedBundle\Resolver\AbstractResolverField;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Parser\Ast\Mutation;
use Youshido\GraphQL\Parser\Ast\Query;

/**
 * This voter search SecurityGraphQL annotation and validate if its a valid user
 */
class GenericAnnotationVoter extends Voter
{
    /** @var String */
    public $kernelRootDir;

    /** @var String */
    public $kernelEnvironment;

    /** @var ContainerInterface */
    public $container;

    /** @var AbstractFieldQuery[]  */
    private $queryClasses = [];

    /** @var AbstractFieldMutation[]  */
    private $mutationClasses = [];

    /**
     * GenericAnnotationVoter constructor.
     * @param $kernelRootDir
     * @param String $kernelEnvironment
     * @param ContainerInterface $container
     */
    public function __construct($kernelRootDir, $kernelEnvironment, ContainerInterface $container)
    {
        $this->kernelRootDir     = $kernelRootDir;
        $this->kernelEnvironment = $kernelEnvironment;
        $this->container         = $container;
    }


    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed $subject The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports($attribute, $subject)
    {
        if ($attribute !== 'RESOLVE_ROOT_OPERATION') {
            return false;
        }

        return true;
    }

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     * It is safe to assume that $attribute and $subject already passed the "supports()" method check.
     *
     * @param string $attribute
     * @param Query $subject
     * @param TokenInterface $token
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $needed = $this->getGroupsRequired($subject->getName(), $subject instanceof Mutation);

        return $this->isAccessGranted($needed);
    }

    /**
     * This all logic is required to assoc the request with class resolver
     *
     * @param $attribute
     * @param $isMutation
     * @return array
     */
    private function getGroupsRequired($attribute, $isMutation)
    {
        if ($isMutation) {
            $roles = $this->createCache('mutation', $this->mutationClasses);
        }else {
            $roles = $this->createCache('query', $this->queryClasses);
        }

        if (!isset($roles[$attribute])) {
            return [];
        }

        return $roles[$attribute]['groups'];
    }

    /**
     * @param string $cacheName
     * @param AbstractResolverField $resolvers
     * @return array
     */
    private function createCache($cacheName = 'query', $resolvers)
    {
        if ($this->kernelEnvironment === 'prod' && file_exists($this->getFileTempCache($cacheName))) {
            return require $this->getFileTempCache($cacheName);
        }

        $arrayToCache = [];

        foreach ($resolvers as $resolver) {

            $groups = [];

            $annotationReader = new AnnotationReader();
            if ($annotationReader = $annotationReader->getClassAnnotation(
                new \ReflectionClass(get_class($resolver)) ,
                SecurityGraphQL::class
            )
            ) {
                $groups = $annotationReader->groups;
            }

            $arrayToCache[$resolver->getName()] = ['class' => get_class($resolver), 'groups' => $groups];
        }

        file_put_contents(
            $this->getFileTempCache($cacheName),
            "<?php\nreturn " . var_export($arrayToCache, true) . ';'
        );

        return $arrayToCache;
    }

    private function isAccessGranted($needed)
    {
        if (!$needed) {
            return true;
        }

        // loaded from container to avoid circular reference
        return $this->container->get('security.authorization_checker')->isGranted($needed);
    }

    private function getFileTempCache($name)
    {
        return $this->kernelRootDir."/../var/cache/".$this->kernelEnvironment."/graphql_$name.tmp";
    }

    public function addQueryClass($queryClass)
    {
        $this->queryClasses[$queryClass->getName()]= $queryClass;
    }

    public function addMutationClass($mutationClass)
    {
        $this->mutationClasses [$mutationClass->getName()]= $mutationClass;
    }
}