<?php
/*****************************************************************************
 * This file is part of LDLib and subject to the Version 2.0 of the          *
 * Apache License, you may not use this file except in compliance            *
 * with the License. You may obtain a copy of the License at :               *
 *                                                                           *
 *                http://www.apache.org/licenses/LICENSE-2.0                 *
 *                                                                           *
 * Unless required by applicable law or agreed to in writing, software       *
 * distributed under the License is distributed on an "AS IS" BASIS,         *
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.  *
 * See the License for the specific language governing permissions and       *
 * limitations under the License.                                            *
 *                                                                           *
 *                Author: Lans.D <lans.d.99@protonmail.com>                  *
 *                                                                           *
 *****************************************************************************/
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
use GraphQL\Language\AST\FieldNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

class Executor extends ReferenceExecutor {
    public static ExecutionContext|array $exeContext2;

    public static function create2(PromiseAdapter $promiseAdapter, Schema $schema, DocumentNode $documentNode, $rootValue,
        $contextValue, array $variableValues, ?string $operationName, callable $fieldResolver, callable $argsMapper):ExecutorImplementation {
        self::$exeContext2 = parent::buildExecutionContext(
            $schema,
            $documentNode,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName,
            $fieldResolver,
            $argsMapper ?? \GraphQL\Executor\Executor::getDefaultArgsMapper(),
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
    protected function executeFields(ObjectType $parentType, $rootValue, array $path, array $unaliasedPath, \ArrayObject $fields, $contextValue) {
        $containsPromise = false;
        $results = [];
        foreach ($fields as $responseName => $fieldNodes) {
            $fieldPath = $path;
            $fieldPath[] = $responseName;
            $result = $this->resolveField($parentType, $rootValue, $fieldNodes, $responseName, $fieldPath, $unaliasedPath, ($contextValue instanceof ScopedContext ? $contextValue->clone() : $contextValue));

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

    #[\Override]
    protected function resolveField(
        ObjectType $parentType,
        $rootValue,
        \ArrayObject $fieldNodes,
        string $responseName,
        array $path,
        array $unaliasedPath,
        $contextValue
    ) {
        $exeContext = $this->exeContext;

        $fieldNode = $fieldNodes[0];
        \assert($fieldNode instanceof FieldNode, '$fieldNodes is non-empty');

        $fieldName = $fieldNode->name->value;
        $fieldDef = $this->getFieldDef($exeContext->schema, $parentType, $fieldName);
        if ($fieldDef === null || ! $fieldDef->isVisible()) {
            return static::$UNDEFINED;
        }

        $path[] = $responseName;
        $unaliasedPath[] = $fieldName;

        $returnType = $fieldDef->getType();
        // The resolve function's optional 3rd argument is a context value that
        // is provided to every resolve function within an execution. It is commonly
        // used to represent an authenticated user, or request-specific caches.
        // The resolve function's optional 4th argument is a collection of
        // information about the current execution state.
        $info = new ResolveInfo(
            $fieldDef,
            $fieldNodes,
            $parentType,
            $path,
            $exeContext->schema,
            $exeContext->fragments,
            $exeContext->rootValue,
            $exeContext->operation,
            $exeContext->variableValues,
            $unaliasedPath
        );

        $resolveFn = $fieldDef->resolveFn
            ?? $parentType->resolveFieldFn
            ?? $this->exeContext->fieldResolver;

        $argsMapper = $fieldDef->argsMapper
            ?? $parentType->argsMapper
            ?? $this->exeContext->argsMapper;

        $contextValue->start($path);
        // Get the resolve function, regardless of if its result is normal
        // or abrupt (error).
        $result = $this->resolveFieldValueOrError(
            $fieldDef,
            $fieldNode,
            $resolveFn,
            $argsMapper,
            $rootValue,
            $info,
            $contextValue
        );

        return $this->completeValueCatchingError(
            $returnType,
            $fieldNodes,
            $info,
            $path,
            $unaliasedPath,
            $result,
            $contextValue
        );
    }

    #[\Override]
    protected function completeValueCatchingError(
        Type $returnType,
        \ArrayObject $fieldNodes,
        ResolveInfo $info,
        array $path,
        array $unaliasedPath,
        $result,
        $contextValue
    ) {
        // Otherwise, error protection is applied, logging the error and resolving
        // a null value for this field if one is encountered.
        try {
            $promise = $this->getPromise($result);
            if ($promise !== null) {
                $contextValue->end($info->path,'promA');
                $completed = $promise->then(fn (&$resolved) => $this->completeValue($returnType, $fieldNodes, $info, $path, $unaliasedPath, $resolved, $contextValue));
            } else {
                $contextValue->end($info->path,'A');
                $completed = $this->completeValue($returnType, $fieldNodes, $info, $path, $unaliasedPath, $result, $contextValue);
            }

            $promise = $this->getPromise($completed);
            if ($promise !== null) {
                $contextValue->end($info->path,'promB');
                return $promise->then(null, function ($error) use ($fieldNodes, $path, $unaliasedPath, $returnType): void {
                    $this->handleFieldError($error, $fieldNodes, $path, $unaliasedPath, $returnType);
                });
            }

            $contextValue->end($info->path,'B');
            return $completed;
        } catch (\Throwable $err) {
            $this->handleFieldError($err, $fieldNodes, $path, $unaliasedPath, $returnType);

            return null;
        }
    }
}
?>