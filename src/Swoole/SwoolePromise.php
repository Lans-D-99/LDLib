<?php
namespace LDLib\Swoole;

use Closure;
use Swoole\Coroutine\WaitGroup;

use function Swoole\Coroutine\go;

enum PromiseState {
    case Fulfilled;
    case Rejected;
    case Pending;
    case Resolving;
}

class SwoolePromise {
    public PromiseState $state = PromiseState::Pending;
    public mixed $result = null;
    public ?SwoolePromise $parent = null;
    public SwoolePromise $oldest;

    public array $directChilds = [];
    public ?\Closure $onFinish = null;

    public mixed $startingValue;
    public string $name = '';

    protected ?\Closure $onFulfilled = null;
    protected ?\Closure $onRejected = null;

    public function __construct(public ?\Closure $executor = null) {
        $this->oldest = $this;
    }

    public static function create(PromiseState $state, mixed $result):self {
        $o = new self();
        $o->state = $state;
        $o->result = $result;
        return $o;
    }

    public function then(?callable $onFulfilled=null, ?callable $onRejected=null):SwoolePromise {
        if ($onFulfilled == null && $onRejected == null) throw new \RuntimeException('Must provide at least one chain callback.');

        $newPromise = new SwoolePromise();
        $newPromise->parent = $this;
        $newPromise->oldest =& $this->oldest;
        $newPromise->onFulfilled = $onFulfilled == null ? null :  Closure::fromCallable($onFulfilled);
        $newPromise->onRejected = $onRejected == null ? null : Closure::fromCallable($onRejected);
        $this->directChilds[] = $newPromise;
        return $newPromise;
    }

    public function resolve(bool $wait=false, int $maxDepth=1000) {
        if ($maxDepth < 0) return;

        if ($this->state !== PromiseState::Pending) return;
        if ($this->parent !== null && $this->parent->state !== PromiseState::Fulfilled && $this->parent->state !== PromiseState::Rejected) {
            $this->parent->resolve(true,0);
        }
        $this->state = PromiseState::Resolving;

        $wg = $wait ? new WaitGroup(1) : null;
        go(function() use($wait, $maxDepth, $wg) {
            if ($this->parent === null) {
                try {
                    $this->result = $this->executor === null ? $this->result : ($this->executor)($this->result);
                    $this->state = PromiseState::Fulfilled;
                } catch (\Throwable $t) {
                    $this->result = $t;
                    $this->state = PromiseState::Rejected;
                } finally {
                    $newDepth = $maxDepth-1;
                    if ($newDepth >= 0) {
                        $wg?->add(count($this->directChilds));
                        foreach ($this->directChilds as $child) go(function() use($child,$wait,$newDepth,$wg) {
                            $child->resolve($wait,$newDepth);
                            $wg?->done();
                        });
                    }
                }
            } else {
                switch ($this->parent->state) {
                    case PromiseState::Fulfilled:
                        try {
                            $this->result = $this->onFulfilled === null ? $this->parent->result : ($this->onFulfilled)($this->parent->result);
                            $this->state = PromiseState::Fulfilled;
                        } catch (\Throwable $t) {
                            $this->result = $t;
                            $this->state = PromiseState::Rejected;
                        } finally {
                            $newDepth = $maxDepth-1;
                            if ($newDepth >= 0) {
                                $wg?->add(count($this->directChilds));
                                foreach ($this->directChilds as $child) go(function() use($child,$wait,$newDepth,$wg) {
                                    $child->resolve($wait,$newDepth);
                                    $wg?->done();
                                });
                            }
                        }
                        break;
                    case PromiseState::Rejected:
                        $this->state = PromiseState::Rejected;
                        $this->result = $this->parent->result;
                        $newDepth = $maxDepth-1;
                        if ($newDepth >= 0) {
                            $wg?->add(count($this->directChilds));
                            foreach ($this->directChilds as $child) go(function() use($child,$wait,$newDepth,$wg) {
                                $child->resolve($wait,$newDepth);
                                $wg?->done();
                            });
                        }
                        break;
                }
            }
            $wg?->done();
        });
        $wg?->wait();
    }
}
?>