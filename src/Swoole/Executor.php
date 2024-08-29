<?php
namespace LDLib\GraphQL;

use GraphQL\Error\InvariantViolation;
use GraphQL\Executor\ExecutionContext;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\ExecutorImplementation;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Executor\ReferenceExecutor;
use GraphQL\Executor\ScopedContext;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class Executor extends ReferenceExecutor {
    public static ExecutionContext|array $exeContext2;

    public static function create2(PromiseAdapter $promiseAdapter, Schema $schema, DocumentNode $documentNode, $rootValue,
        $contextValue, array $variableValues, ?string $operationName, callable $fieldResolver):ExecutorImplementation {
        self::$exeContext2 = parent::buildExecutionContext(
            $schema,
            $documentNode,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName,
            $fieldResolver,
            $promiseAdapter
        );


        if (\is_array(self::$exeContext2)) {
            return new class($promiseAdapter->createFulfilled(new ExecutionResult(null, self::$exeContext2))) implements ExecutorImplementation {
                private Promise $result;

                public function __construct(Promise $result)
                {
                    $this->result = $result;
                }

                public function doExecute(): Promise
                {
                    return $this->result;
                }
            };
        }

        return new static(self::$exeContext2);
    }

    #[\Override]
    protected function executeFields(ObjectType $parentType, $rootValue, array $path, \ArrayObject $fields, $contextValue) {
        $containsPromise = false;
        $results = [];
        foreach ($fields as $responseName => $fieldNodes) {
            $fieldPath = $path;
            $fieldPath[] = $responseName;
            $result = $this->resolveField($parentType, $rootValue, $fieldNodes, $fieldPath, ($contextValue instanceof ScopedContext ? $contextValue->clone() : $contextValue));

            if ($result === static::$UNDEFINED) {
                continue;
            }

            if (! $containsPromise && $this->isPromise($result)) {
                $containsPromise = true;
            }

            $results[$responseName] = $result;
        }


        // If there are no promises, we can just return the object
        if (! $containsPromise) {
            return static::fixResultsIfEmptyArray($results);
        }

        // Otherwise, results is a map from field name to the result of resolving that
        // field, which is possibly a promise. Return a promise that will return this
        // same map, but with any promises replaced with the values they resolved to.
        $v = null;
        try {
            $v = $this->promiseForAssocArray($results);
        } catch (InvariantViolation $t) {
            $results = [null];
            self::$exeContext2->addError(new \GraphQL\Error\Error($t->getMessage(),null,null,null,$path,$t));
        } catch (\Throwable $t) {
            throw $t;
        }
        return $v;
    }
}
?>