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

use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\VisitorStop;
use GraphQL\Validator\QueryValidationContext;

class MutationLimiter extends ValidationRule {
    protected int $mutationCount = 0;

    public function __construct(protected int $maxMutations=5) {}

    public function getVisitor(QueryValidationContext $context):array {
        return [
            NodeKind::FIELD => function() use($context) {
                $parentName = $context->getParentType()?->toString();
                if ($parentName == 'Mutation') {
                    if (++$this->mutationCount > $this->maxMutations) {
                        $context->reportError(new Error("Mutation limit reached. ({$this->maxMutations})"));
                        return new VisitorStop();
                    }
                }
            }
        ];
    }
}
?>