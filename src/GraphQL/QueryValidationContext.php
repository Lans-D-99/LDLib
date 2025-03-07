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

class QueryValidationContext extends \GraphQL\Validator\QueryValidationContext {
    public int $nErrors = 0;
    #[\Override]
    public function reportError(Error $error): void {
        $this->nErrors++;
        if ($this->nErrors < ((int)($_SERVER['LD_GRAPHQL_MAX_ERRORS']??50))) $this->errors[] = $error;
    }
}
?>