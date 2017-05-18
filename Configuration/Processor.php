<?php


namespace MedlabMG\YoushidoGraphQLExtendedBundle\Configuration;


use MedlabMG\YoushidoGraphQLExtendedBundle\Exception\GraphQLValidatorException;
use MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\Naming\CamelCaseNamingStrategy;
use Symfony\Component\Validator\ConstraintViolationInterface;

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

                    /** @var ConstraintViolationInterface $violation */
                    foreach ($error->getViolationList() as $violation ) {
                        $key = $camelCase->translateNameByString( $violation->getPropertyPath());
                        if (!isset($errorInside[$key])) {
                            $errorInside[$key] = [];
                        }

                        $errorInside[$key] [] = [
                            'message' => $violation->getMessage(),
                            'code'    => $violation->getCode()
                        ];
                    }

                    $errors[$error->getResolverName()] = $errorInside;

                }else{
                    /** @var $error \Exception */
                    $errors[] = [ 'message' => $error->getMessage()];
                }
            }

            $result['errors'] = $errors;
        }

        return $result;
    }

}