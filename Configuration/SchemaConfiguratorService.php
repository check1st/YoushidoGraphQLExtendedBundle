<?php


namespace MedlabMG\YoushidoGraphQLExtendedBundle\Configuration;


use JMS\DiExtraBundle\Annotation as DI;
use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\Object\AbstractObjectType;


class SchemaConfiguratorService extends AbstractSchema
{
    /** @var QueryConfiguratorService */
    public $queryService;

    /** @var MutationConfiguratorService */
    public $mutationService;

    public function __construct($queryService, $mutationService)
    {
        $this->queryService    = $queryService;
        $this->mutationService = $mutationService;

        parent::__construct([]);
    }

    public function build(SchemaConfig $config)
    {
        $config
            ->setMutation($this->mutationService)
            ->setQuery($this->queryService);
    }
}
