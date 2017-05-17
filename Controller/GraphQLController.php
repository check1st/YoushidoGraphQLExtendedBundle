<?php


namespace MedlabMG\YoushidoGraphQLExtendedBundle\Controller;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Youshido\GraphQLBundle\Execution\Processor;

class GraphQLController extends \Youshido\GraphQLBundle\Controller\GraphQLController
{
    /**
     * @Route("/graphql", name="youshido_graphql_graphql_default")
     *
     * @throws \Youshido\GraphQL\Exception\ConfigurationException
     *
     * @return JsonResponse
     */
    public function defaultAction()
    {
        if ($this->get('request_stack')->getCurrentRequest()->getMethod() == 'OPTIONS') {
            return $this->createEmptyResponse();
        }

        list($query, $variables) = $this->getPayload();

        if (!$this->get('service_container')->initialized('graphql.schema')) {
            $schema = $this->container->get($this->getParameter('graphql_extended.schema'));
            $this->get('service_container')->set('graphql.schema', $schema);
        }

        /** @var Processor $processor */
        $processor = $this->get('graphql.processor');
        $processor->processPayload($query, $variables);

        $response = new JsonResponse($processor->getResponseData(), 200, $this->getParameter('graphql.response.headers'));

        if ($this->getParameter('graphql.response.json_pretty')) {
            $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRETTY_PRINT);
        }

        return $response;
    }

    private function createEmptyResponse()
    {
        return new JsonResponse([], 200, $this->getParameter('graphql.response.headers'));
    }

    private function getPayload()
    {
        $request   = $this->get('request_stack')->getCurrentRequest();
        $query     = $request->get('query', null);
        $variables = $request->get('variables', []);

        $variables = is_string($variables) ? json_decode($variables, true) ?: [] : [];

        $content = $request->getContent();
        if (!empty($content)) {
            if ($request->headers->has('Content-Type') && 'application/graphql' == $request->headers->get('Content-Type')) {
                $query = $content;
            } else {
                $params = json_decode($content, true);

                if ($params) {
                    $query = isset($params['query']) ? $params['query'] : $query;

                    if (isset($params['variables'])) {
                        if (is_string($params['variables'])) {
                            $variables = json_decode($params['variables'], true) ?: $variables;
                        } else {
                            $variables = $params['variables'];
                        }

                        $variables = is_array($variables) ? $variables : [];
                    }
                }
            }
        }

        return [$query, $variables];
    }
}