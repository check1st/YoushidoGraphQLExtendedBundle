<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
final class SecurityGraphQL
{
    /** @var array<string> @Required */
    public $groups;
}