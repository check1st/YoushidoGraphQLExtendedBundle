<?php


namespace MedlabMG\YoushidoGraphQLExtendedBundle\Configuration;


use MedlabMG\YoushidoGraphQLExtendedBundle\Exception\GraphQLValidatorException;
use MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\Naming\CamelCaseNamingStrategy;

class Processor extends \Youshido\GraphQLBundle\Execution\Processor
{

    public function getResponseData()
    {
        $result = [];

        if (!empty($this->data)) {
            $result['data'] = $this->data;
        }

        $errors = [];
        if ($this->executionContext->hasErrors()) {

            foreach ($this->executionContext->getErrors() as $error) {

                if ($error instanceof GraphQLValidatorException) {

                    $camelCase = new CamelCaseNamingStrategy();
                    $errorInside = [];

                    foreach ($error->getViolationList() as $violation ) {
                        $errorInside[$camelCase->translateNameByString( $violation->getPropertyPath())] = $violation->getMessage() ;
                    }

                    $errors[$error->getResolverName()] = $errorInside;

                }else{
                    /** @var $error \Exception */
                    $errors[] = $error->getMessage();
                }
            }

            $result['errors'] = $errors;
        }

        return $result;
    }

}