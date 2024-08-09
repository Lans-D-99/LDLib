<?php
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