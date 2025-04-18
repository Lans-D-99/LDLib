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
namespace LDLib\Swoole;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use LDLib\DataFetcher\DataFetcher;
use LDLib\Server\WorkerContext;
use Swoole\Coroutine\WaitGroup;

class SwoolePromiseAdapter implements PromiseAdapter {
    public function isThenable(mixed $value):bool {
        return $value instanceof SwoolePromise;
    }

    public function convertThenable(mixed $thenable):Promise {
        return new Promise($thenable, $this);
    }

    public function then(Promise $promise, ?callable $onFulfilled=null, ?callable $onRejected=null):Promise {
        return new Promise($promise->adoptedPromise->then($onFulfilled,$onRejected),$this);
    }

    public function create(callable $resolver):Promise {
        throw new \LogicException('Not implemented.');
    }

    public function createFulfilled(mixed $value = null):Promise {
        return new Promise(SwoolePromise::create(PromiseState::Fulfilled, $value), $this);
    }

    public function createRejected(\Throwable $reason):Promise {
        return new Promise(SwoolePromise::create(PromiseState::Rejected, $reason), $this);
    }

    public function all(iterable $promisesOrValues):Promise {
        $pdo = WorkerContext::$pdoConnectionPool->get();
        $valkey = WorkerContext::$valkeyConnectionPool->get();
        DataFetcher::exec($pdo,$valkey);
        $pdo->toPool(); $valkey->toPool();

        $res = [];
        $wg = new WaitGroup();
        $i = -1;
        $resolveArray = false;
        foreach ($promisesOrValues as $porv) {
            $i++;
            if ($porv instanceof Promise) {
                assert($porv->adoptedPromise instanceof SwoolePromise);

                $wg->add();
                go(function() use($porv,$wg,&$res,$i,&$resolveArray) {
                    assert($porv->adoptedPromise instanceof SwoolePromise);
                    $porv->adoptedPromise->resolve(true,1);
                    $res[$i] = $porv->adoptedPromise->result;
                    if (!$resolveArray && $res[$i] instanceof Promise && $res[$i]->adoptedPromise instanceof SwoolePromise)
                        $resolveArray = true;
                    $wg->done();
                });
            } else {
                $res[$i] = $porv;
            }
        }
        $wg->wait();
        ksort($res);

        if ($resolveArray) {
            $p = $this->all($res);
            assert($p->adoptedPromise instanceof SwoolePromise);
            $p->adoptedPromise->resolve(true);
            $res = $p->adoptedPromise->result;
        }

        foreach($res as &$t) if ($t instanceof \Throwable) throw $t;

        return new Promise(new SwoolePromise(fn() => $res), $this);
    }
}