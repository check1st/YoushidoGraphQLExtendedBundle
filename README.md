YoushidoGraphQLExtendedBundle
=============================

**At this moment YoushidoGraphQLExtendedBundle is in alpha version**

This package has been created to facilitate the use of 
yousido package with the following features:

- Response entities directly and auto serialize by JMS Serializer with group "GraphQL" only asking for data required
- Easy way to create Queries and Mutations
- Command to auto generate types from entities
- Command to validate Type with your response
- Security per operation by roles declared in annotation
- Improve errors trace

Instalation
------------------------------

```bash
composer require medlabmg/youshido-graphql-extended
```

add bundles youshido and extended
 
```php
// app/AppKernel.php

$bundles = array(
    new Youshido\GraphQLBundle\GraphQLBundle(),
    new \MedlabMG\YoushidoGraphQLExtendedBundle\YoushidoGraphQLExtendedBundle(),
);
```

Enable controllers

```yaml
# app/config/routing.yml

graphql:
    resource: "@YoushidoGraphQLExtendedBundle/Controller/"
    type: annotation
```

! YoushidoGraphQLExtendedBundle is enabled :-) !

How to create a mutation resolver
------------------------------

Configure it

```yaml
# app/config/services.yml

service_name:
    class: MedlabMG/MedlabBundle/GraphQL/Mutation/Security/SecurityCreateTokenField
    tags:
          - {name: graphql.mutation}

```

And his logic..

```php
// src/MedlabMG/MedlabBundle/GraphQL/Mutation/Security/SecurityCreateTokenField.php
    
class SecurityCreateTokenField extends AbstractFieldMutation
{

    public function buildParent(FieldConfig $config)
    {
        $config
            ->setDescription('Mutation')
            ->addArguments([
                'username' => new NonNullType(new StringType()),
                'password' => new NonNullType(new StringType()),
            ])
        ;
    }

    public function resolveParent($value, ResolveInfo $info)
    {
        $user = $this->args->get('username');
        $password = $this->args->get('password');
        
        if ($user !== 'miguel')
            $this->addViolation('invalid username', 'username');
         
        if ($password !== 'pass') 
            $this->addViolation('invalid password', 'password');
            
        $this->verifyCurrentErrors();

        return 'jwt';
    }

    /**
     * @return AbstractObjectType|AbstractType
     */
    public function getType()
    {
        return new StringType();
    }

}
```

 How to create a query resolver
------------------------------

Configure it

```yaml
# app/config/services.yml

service_name:
    class: MedlabMG/MedlabBundle/GraphQL/Query/Category/CategoryAllField
    tags:
          - {name: graphql.query}

```

And his logic..

```php
    // src/MedlabMG/MedlabBundle/GraphQL/Query/Category/CategoryAllField.php
    
    class CategoryAllField extends AbstractFieldQuery
    {
    
        public function resolveParent($value, ResolveInfo $info)
        {
            return $this->getEM()->getRepository("MedlabMGMedlabBundle:Category")->findAll();
        }
    
        /**
         * @return AbstractObjectType|AbstractType
         */
        public function getType()
        {
            return new ListType(new CategoryType());
        }
    
    }
```

 Security in resolver
---------------------

Its easy add annotation in a class query or mutation 

```php

use MedlabMG\YoushidoGraphQLExtendedBundle\Resolver\AbstractFieldMutation;
use MedlabMG\YoushidoGraphQLExtendedBundle\Annotation\SecurityGraphQL;

/**
 * @SecurityGraphQL({"ROLE_GRAPHQL_STUDENT"})
 */
class StudentProfileUpdateField extends AbstractFieldMutation
{
   // ...
}
```

This annotation also is used to auto insert roles required in doc
