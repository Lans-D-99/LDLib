<?php
namespace LDLib\GraphQL;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Type\Schema as SchemaType;
use GraphQL\Validator\Rules\QueryComplexity;

class GraphQLPrimary extends \GraphQL\GraphQL {
    #[\Override]
    public static function promiseToExecute(
        PromiseAdapter $promiseAdapter,
        SchemaType $schema,
        $source,
        $rootValue = null,
        $context = null,
        array $variableValues = null,
        string $operationName = null,
        callable $fieldResolver = null,
        array $validationRules = null
    ): Promise {
        try {
            $documentNode = $source instanceof DocumentNode
                ? $source
                : Parser::parse(new Source($source, 'GraphQL'));

            if ($validationRules === null) {
                $queryComplexity = DocumentValidator::getRule(QueryComplexity::class);
                assert($queryComplexity instanceof QueryComplexity, 'should not register a different rule for QueryComplexity');

                $queryComplexity->setRawVariableValues($variableValues);
            } else {
                foreach ($validationRules as $rule) {
                    if ($rule instanceof QueryComplexity) {
                        $rule->setRawVariableValues($variableValues);
                    }
                }
            }

            $validationErrors = DocumentValidator::validate($schema, $documentNode, $validationRules);

            if ($validationErrors !== []) {
                return $promiseAdapter->createFulfilled(
                    new ExecutionResult(null, $validationErrors)
                );
            }

            return Executor::promiseToExecute(
                $promiseAdapter,
                $schema,
                $documentNode,
                $rootValue,
                $context,
                $variableValues,
                $operationName,
                $fieldResolver
            );
        } catch (Error $e) {
            return $promiseAdapter->createFulfilled(
                new ExecutionResult(null, [$e])
            );
        }
    }
}
?>