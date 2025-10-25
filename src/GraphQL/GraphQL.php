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
        bool $useMD5ForQueryHash = false
    ): Promise {
        try {
            $queryHash = null;
            try {
                $queryHash = $useMD5ForQueryHash ? md5($source) : crc32($source);
                if (is_array($variableValues)) {
                    $json = json_encode($variableValues);
                    if ($json === false) throw new \Exception("Couldn't json_encode variableValues. json:".print_r($variableValues,true));
                    $queryHash = $useMD5ForQueryHash ? md5($queryHash.$json) : md5($queryHash.$json);
                }
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