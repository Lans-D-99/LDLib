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

class Limiter extends ValidationRule {
    public function __construct(protected int $limiterMaxVal=100) {}

    public function getVisitor(QueryValidationContext $context):array {
        $nLimiter = 0;

        $check = static function($o,$node) use(&$nLimiter,$context) {
            if ($nLimiter > ($o->limiterMaxVal)) { $context->reportError(new Error('Limiter triggered, query cancelled.',[$node])); return true; }
            return false;
        };
        
        return [
            NodeKind::DIRECTIVE => function($node) use(&$nLimiter,$check) {
                $nLimiter++;
                if ($check($this,$node)) return new VisitorStop();
            },
            NodeKind::FIELD => function($node) use(&$nLimiter,$check) {
                $nLimiter++;
                if ($check($this,$node)) return new VisitorStop();
            }
        ];
    }
}
?>