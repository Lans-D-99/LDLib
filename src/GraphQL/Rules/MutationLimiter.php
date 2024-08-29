<?php
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