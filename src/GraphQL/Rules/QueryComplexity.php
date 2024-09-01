<?php
namespace LDLib\GraphQL;

class QueryComplexity extends \GraphQL\Validator\Rules\QueryComplexity {
    public function __construct(int $maxQueryComplexity) {
        $this->queryComplexity = 0;
        parent::__construct($maxQueryComplexity);
    }
}
?>