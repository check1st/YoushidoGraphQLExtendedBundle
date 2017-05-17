<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Controller;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class GraphQLExplorerController extends \Youshido\GraphQLBundle\Controller\GraphQLExplorerController
{
    /**
     * @Cache(expires="tomorrow", public=true)
     * @Route("/graphql/explorer", name="youshido_graphql_graphqlexplorer_explorer")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function explorerAction()
    {
        return parent::explorerAction();
    }
}