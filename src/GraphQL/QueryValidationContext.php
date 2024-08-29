<?php
namespace LDLib\GraphQL;

use GraphQL\Error\Error;

class QueryValidationContext extends \GraphQL\Validator\QueryValidationContext {
    public int $nErrors = 0;
    #[\Override]
    public function reportError(Error $error): void {
        $this->nErrors++;
        if ($this->nErrors < ((int)($_SERVER['LD_GRAPHQL_MAX_ERRORS']??50))) $this->errors[] = $error;
    }
}
?>