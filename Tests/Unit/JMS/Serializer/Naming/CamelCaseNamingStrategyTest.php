<?php

namespace Tests\Unit\JMS\Serializer\Naming;

use MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\Naming\CamelCaseNamingStrategy;


class CamelCaseNamingStrategyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider providerTestOk
     */
    public function testOk($fieldTest, $resultShouldBe)
    {
        $c = new CamelCaseNamingStrategy();
        $result = $c->translateNameByString($fieldTest);

        $this->assertEquals($resultShouldBe, $result);
    }

    public function providerTestOk()
    {
        return [
            ['state_id', 'stateId'],
            ['State_id', 'stateId'],
            ['state.id', 'stateId'],
        ];
    }
}
