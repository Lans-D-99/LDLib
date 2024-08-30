<?php
namespace LDLib\GraphQL;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use Swoole\Coroutine\WaitGroup;

class DocumentValidator extends \GraphQL\Validator\DocumentValidator {
    public static function validate(Schema $schema, DocumentNode $ast, array $rules=null, TypeInfo $typeInfo=null):array {
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