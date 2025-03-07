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
namespace LDLib;

use ArrayAccess;

class PaginationVals {
    public ?string $sortBy = null;
    public array $data = [];

    private string $s;

    public function __construct(
        public ?int $first,
        public ?int $last,
        public ?string $after,
        public ?string $before,
        public bool $requestPageCount=false,
        public bool $lastPageSpecialBehavior=false,
        public int $skipPages=0
    ) {
        if (($first == null || $first < 0) && ($last == null || $last < 0)) throw new \InvalidArgumentException("Invalid arguments.");
    }

    public function getString() {
        return self::toString($this->first,$this->last,$this->after,$this->before,$this->skipPages,$this->sortBy,$this->data,$this->requestPageCount,$this->lastPageSpecialBehavior);
    }

    public static function toString(
        ?int $first, ?int $last, ?string $after, ?string $before, int $skipPages,
        ?string $sortBy, ?array $data, bool $requestPageCount=false, bool $lastPageSpecialBehavior=false
    ):string {
        $s = '';
        if ($first != null && $first > 0) $s .= "f-{$first}";
        else if ($last != null && $last > 0) $s .= "l-{$last}";
        else throw new \InvalidArgumentException("Invalid arguments.");

        if ($after != null) $s .= "-a-{$after}";
        else if ($before != null) $s .= "-b-{$before}";

        if ($skipPages != 0) $s .= "-skipPages-{$skipPages}";

        if ($sortBy!=null) $s .= "-sort-$sortBy";

        if ($requestPageCount) $s .= '-rqPageCount';
        if ($lastPageSpecialBehavior) $s .= '-lastPageSpecialBehavior';

        if ($data != null) $s .= '-data-'.implode('|',$data);

        return $s;
    }

    public static function fromArray(array $a) {
        return new self($a['first']??null,$a['last']??null,$a['after']??null,$a['before']??null,$a['requestPageCount']??false,$a['lastPageSpecialBehavior']??false,$a['skipPages']??0);
    }
}

class PageInfo implements ArrayAccess {
    public function __construct(
        public readonly ?string $startCursor,
        public readonly ?string $endCursor,
        public readonly bool $hasPreviousPage,
        public readonly bool $hasNextPage,
        public readonly ?int $pageCount,
        public readonly ?int $currPage,
        public readonly ?int $itemsCount
    ) { }

    public function offsetSet($offset, $value):void {

    }

    public function offsetExists($offset): bool {
        return isset($this->$offset);
    }

    public function offsetUnset($offset): void {

    }

    public function offsetGet($offset): mixed {
        return isset($this->$offset) ? $this->$offset : null;
    }
}

enum SuccessType {
    case SUCCESS;
    case PARTIAL_SUCCESS;
}

enum ErrorType {
    case AWS_ERROR;
    case DATABASE_ERROR;
    case VALKEY_ERROR;
    case DBLOCK_TAKEN;
    case DUPLICATE;
    case EXPIRED;
    case FILE_OPERATION_ERROR;
    case INVALID;
    case INVALID_CONTEXT;
    case INVALID_DATA;
    case LIMIT_REACHED;
    case MISSING_DATA;
    case NOT_AUTHENTICATED;
    case NOT_ENOUGH_PRIVILEGES;
    case NOT_FOUND;
    case NOT_IMPLEMENTED;
    case PROHIBITED;
    case USELESS;

    case UNKNOWN;
}

class TypedException extends \Exception implements \GraphQL\Error\ClientAware {
    private ErrorType $errorType;

    public function __construct(string $message, ErrorType $errorType, $code=0, ?\Throwable $previous = null) {
        $this->errorType = $errorType;
        parent::__construct($message, $code, $previous);
    }

    public function getErrorType():ErrorType { return $this->errorType; }

    public function isClientSafe():bool { return true; }
}

class OperationResult {
    public string $resultMsg = '';

    public function __construct(public SuccessType|ErrorType $resultType, ?string $resultMsg = null, public array $fieldsData = [], public array $data = []) {
        if ($resultMsg != null) $this->resultMsg = $resultMsg;
        else switch ($resultType) {
            case ErrorType::INVALID_DATA: $this->resultMsg = 'Invalid data.'; break;
            case ErrorType::NOT_AUTHENTICATED: $this->resultMsg = 'User not authenticated.'; break;
            case ErrorType::NOT_ENOUGH_PRIVILEGES: $this->resultMsg = 'User not authorized.'; break;
            default: $this->resultMsg = $resultType instanceof ErrorType ? 'Something went wrong.' : 'No problem detected.'; break;
        }
    }
}

class WSMessage {
    public function __construct(public array $tags=[], public string $message='') { }
}
?>