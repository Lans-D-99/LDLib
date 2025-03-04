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
use LDLib\Cache\LDValkey;
use LDLib\Logger\Logger;

class GraphQLPrimary extends \GraphQL\GraphQL {
    #[\Override]
    public static function promiseToExecute(
        PromiseAdapter $promiseAdapter,
        SchemaType $schema,
        $source,
        $rootValue = null,
        $context = null,
        ?array $variableValues = null,
        ?string $operationName = null,
        ?callable $fieldResolver = null,
        ?array $validationRules = null,
    ): Promise {
        try {
            $queryHash = null;
            try {
                $queryHash = crc32($source);
                if (is_array($variableValues)) foreach ($variableValues as $v) $queryHash = crc32($queryHash. (is_array($v) ? json_encode($v) : (string)$v));
            } catch (\Exception $e) { Logger::logThrowable($e); }
            
            $valkey = $queryHash != null ? new LDValkey() : null;
            if ($valkey?->get("validQueries:$queryHash") === '1') {
                $newRules = [];
                foreach ($validationRules as $rule) if ($rule instanceof QueryComplexity) $newRules[] = $rule;
                $validationRules = $newRules;
            } 

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
            
            $documentNode = $source instanceof DocumentNode
                ? $source
                : Parser::parse(new Source($source, 'GraphQL'));
            $validationErrors = DocumentValidator::validate($schema, $documentNode, $validationRules);
            if ($validationErrors !== []) {
                return $promiseAdapter->createFulfilled(
                    new ExecutionResult(null, $validationErrors)
                );
            }
            $valkey?->set("validQueries:$queryHash",'1',['EX' => 3600]);

            $p = Executor::promiseToExecute(
                $promiseAdapter,
                $schema,
                $documentNode,
                $rootValue,
                $context,
                $variableValues,
                $operationName,
                $fieldResolver
            );

            return $p; 
        } catch (Error $e) {
            return $promiseAdapter->createFulfilled(
                new ExecutionResult(null, [$e])
            );
        }
    }
}
?>