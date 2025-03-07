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

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use Swoole\Coroutine\WaitGroup;

class DocumentValidator extends \GraphQL\Validator\DocumentValidator {
    public static function validate(Schema $schema, DocumentNode $ast, ?array $rules=null, ?TypeInfo $typeInfo=null):array {
        $rules ??= static::allRules();
        if ($rules === []) return [];
        $typeInfo ??= new TypeInfo($schema);
        $context = new QueryValidationContext($schema, $ast, $typeInfo);

        // LD_GRAPHQL_LIMITER always exec first
        $skipLimiter = false;
        if (((int)($_SERVER['LD_GRAPHQL_LIMITER']??0)) > 0) {
            Visitor::visit($ast, Visitor::visitWithTypeInfo($typeInfo,Visitor::visitInParallel([(new Limiter($_SERVER['LD_GRAPHQL_LIMITER']))->getVisitor($context)])));
            $errs = $context->getErrors();
            if (count($errs) > 0) return $errs;
            $skipLimiter = true;
        }

        // Exec other validations
        $wg = new WaitGroup();
        foreach ($rules as $rule) {
            if ($rule instanceof Limiter && $skipLimiter) continue;
            $wg->add();
            go(function() use($ast,$typeInfo,$rule,$context,$wg) {
                try {
                    Visitor::visit($ast, Visitor::visitWithTypeInfo($typeInfo,Visitor::visitInParallel([$rule->getVisitor($context)])));
                } catch (\GraphQL\Error\Error $e) {
                    $context->reportError($e);
                }
                $wg->done();
            });
        }
        if (!$wg->wait(2)) return [new \GraphQL\Error\Error('Query validation takes too long.')];

        return $context->getErrors();
    }
}
?>