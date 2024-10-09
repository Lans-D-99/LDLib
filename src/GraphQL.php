<?php
namespace LDLib\GraphQL;

use GraphQL\Error\ClientAware;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\ProvidesExtensions;
use GraphQL\Executor\Executor;
use GraphQL\Language\SourceLocation;
use LDLib\Swoole\SwoolePromise;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\DisableIntrospection;
use LDLib\DataFetcher\DataFetcher;
use LDLib\TypedException;
use LDLib\Context\IHTTPContext;
use LDLib\Context\IOAuthContext;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\Swoole\SwoolePromiseAdapter;
use LDLib\Context\IWSContext;
use LDLib\GraphQL\Limiter;
use LDLib\GraphQL\MutationLimiter;
use LDLib\GraphQL\QueryComplexity;
use LDLib\Server\ServerContext;
use LDLib\Server\WorkerContext;
use LDLib\Utils\Utils;

class GraphQL {
    public static Schema $schema;
    public static string $generatorClassName;
    public static ?\Closure $defaultResolver;
    public static ?\Closure $errorFormatter;
    public static ?\Closure $errorHandler;
    public static ?array $rules;
    public static bool $built = false;

    public static function init(Schema $schema, ?string $generatorClassName=null, ?\Closure $defaultResolver=null, ?\Closure $errorFormatter=null, ?\Closure $errorHandler=null, ?callable $rules=null) {
        self::$schema = $schema;
        self::$generatorClassName = $generatorClassName;
        self::$defaultResolver = $defaultResolver;
        self::$errorFormatter = $errorFormatter;
        self::$errorHandler = $errorHandler;
        self::$rules = $rules;
    }

    public static function processQuery(IHTTPContext|IWSContext|IOAuthContext $context) {
        $tGraphQL = microtime(true);
        $isDebug = (bool)$_SERVER['LD_DEBUG'];
        $isHTTP = $context instanceof IHTTPContext;
        $isWS = $context instanceof IWSContext;
        $rawContent = null;
        if ($isHTTP && $context->request->header['content-type'] == 'application/json') $rawContent = $context->request->getContent();
        else if ($isWS) $rawContent = $context->frame->data;

        $respond = static function (IHTTPContext|IWSContext $context, string $msg, int $statusCode=200) use ($isHTTP,$isWS) {
            if ($isHTTP) {
                $context->response->status($statusCode);
                $context->response->end($msg);
            } else if ($isWS) @$context->server->push($context->frame->fd, $msg);
        };

        if (!self::$built) { $respond($context,json_encode(['error' => 'GraphQL not initialized.'],503)); return; }

        // Decompress data
        if ($isHTTP && is_string($rawContent)) {
            $rawContent = ServerContext::applyContentDecoding($context->request,$rawContent);
            if ($rawContent === false) { $respond($context,json_encode(['error' => "Couldn't decode data."]),400); return; }
        }

        // Valid JSON?
        $jsonInput = null;
        if (!empty($rawContent)) { $jsonInput = json_decode($rawContent,true); }
        else if ($isHTTP &&  isset($context->request->post['gqlQuery'])) { $jsonInput = json_decode($context->request->post['gqlQuery'],true); }
        else { $respond($context,'...'); return; }

        if ($jsonInput == null) { $respond($context, json_encode(['error' => 'JSON ERROR.']), 400); return; }
        else if (!is_array($jsonInput) || !is_string($jsonInput['query']??null)) { $respond($context, json_encode(['error' => 'Bad request.']), 400); return; }

        $gqlQuery = $jsonInput['query'];

        // Valid variables?
        $rawVariables = null;
        if (isset($jsonInput['variables'])) $rawVariables = $jsonInput['variables'];
        else if ($isHTTP && isset($context->request->post['gqlVariables'])) $rawVariables = $context->request->post['gqlVariables'];
        $gqlVariables = null;
        if (!empty($rawVariables)) {
            if (is_array($rawVariables)) $gqlVariables = $rawVariables;
            else $gqlVariables = json_decode($rawVariables,true);
        }

        // Operation name
        $gqlOperationName = null;
        if (is_string($jsonInput['operationName']??null)) $gqlOperationName = $jsonInput['operationName'];
        else if (is_string($context->request->post['gqlOperationName']??null)) $gqlOperationName = $context->request->post['gqlOperationName'];

        // Rules
        $user = $context->getAuthenticatedUser();
        $rules = self::$rules;
        $queryComplexityRule = new QueryComplexity($user == null ? (int)$_SERVER['LD_SEC_BASE_MAX_QUERY_COMP'] : (int)$_SERVER['LD_SEC_USERS_MAX_QUERY_COMP']);
        $rules[] = $queryComplexityRule;
        $rules[] = new MutationLimiter($user == null ? (int)$_SERVER['LD_SEC_BASE_SIMULT_MUTATION_LIMIT'] : (int)$_SERVER['LD_SEC_USERS_SIMULT_MUTATION_LIMIT']);

        Executor::setImplementationFactory(fn(...$args) => \LdLib\GraphQL\Executor::create2(...$args));
        $promise = \LDLib\GraphQL\GraphQLPrimary::promiseToExecute(new SwoolePromiseAdapter(), self::$schema, $gqlQuery, null, $context, $gqlVariables, $gqlOperationName, self::$defaultResolver, $rules);

        $promise->then(function(\GraphQL\Executor\ExecutionResult $result) use(&$isDebug, &$context, &$tGraphQL, $isHTTP, $queryComplexityRule, $user) {
            if ($isHTTP) $context->deleteUploadedFiles();
            $complexity = $queryComplexityRule->getQueryComplexity();

            $output = $result->setErrorFormatter(self::$errorFormatter)->setErrorsHandler(self::$errorHandler)->toArray($isDebug ? DebugFlag::INCLUDE_DEBUG_MESSAGE : DebugFlag::NONE);
            if ($user?->hasRole('Administrator') === true) {
                if (count($context->logs) > 0) {
                    $output['logs'] = [];
                    $i = 0;
                    foreach ($context->logs as $err) $output['logs'][$i++] = $err;
                }

                $output['dbcost'] = $context->dbcost/100;
                $output['queryComplexity'] = $queryComplexityRule->getQueryComplexity();
                $output['cache'] = [
                    'redis_get' => $context->nRedisGet,
                    'redis_set' => $context->nRedisSet
                ];
            }

            if ($context instanceof IHTTPContext) {
                go(function() use($context,$user,$complexity) {
                    $pdo = $context->getLDPDO();
                    if ($user !== null) {
                        $pdo->pdo->query("INSERT INTO sec_users_query_complexity_usage (user_id,complexity_used) VALUES ($user->id,$complexity) ON DUPLICATE KEY UPDATE complexity_used=complexity_used+VALUES(complexity_used)");
                    } else {
                        $remoteAddr = Utils::getRealRemoteAddress($context->request);
                        $pdo->pdo->query("INSERT INTO sec_query_complexity_usage (remote_address,complexity_used) VALUES ('{$remoteAddr}',$complexity) ON DUPLICATE KEY UPDATE complexity_used=complexity_used+VALUES(complexity_used)");
                    }
                    $pdo->toPool();
                });
                
                if ($user?->hasRole('Administrator') === true) {
                    $graphQLDuration = (string)((microtime(true) - $tGraphQL)*1000);
                    $output['processTime'] = "{$graphQLDuration}ms";
                    $context->addServerTimingData("graphql;dur={$graphQLDuration}ms");
                    $context->response->header('Server-Timing', $context->sServerTiming);
                    $output['pathTimings'] = $context->gqlPathTimes;
                }

                $context->response->header('content-type', 'application/json');
                $context->response->end(ServerContext::applyContentEncoding($context->request,$context->response,json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)));
            } else if ($context instanceof IWSContext) {
                if ($context->subRequest?->name === '--unsubscribing--') {
                    @$context->server->push($context->frame->fd,json_encode($output,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                } else if (isset($output['errors'])) {
                    $a = ['subscription_init' => 'failed', 'errors' => $output['errors']];
                    @$context->server->push($context->frame->fd,json_encode($a,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                } else if ($context?->subRequest == null) {
                    @$context->server->push($context->frame->fd,json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                } else {
                    $success = false;
                    try { $success = DataFetcher::storeSubscription($context, json_encode(['data' => $output['data']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); }
                    catch (\Throwable $t) { Logger::logThrowable($t); }
                    finally {
                        if (!$success) {
                            Logger::log(LogLevel::ERROR, 'GraphQL', 'storeSubscription failed');
                            @$context->server->push($context->frame->fd,json_encode(['subscription_init' => 'failed', 'error' => 'Internal error.']));
                            return;
                        }
                    }
                    @$context->server->push($context->frame->fd,json_encode(['subscription_init' => 'succeeded']));
                }
            }
        });
        assert($promise->adoptedPromise instanceof SwoolePromise);
        $promise->adoptedPromise->directChilds[0]->resolve();
    }

    public static function buildSchema() {
        if (self::$built) return;
        if (!isset(self::$schema)) throw new \ErrorException('buildSchema: schema not found.');

        self::$defaultResolver = $defaultResolver ??= fn() => null;
        self::$errorFormatter = $errorFormatter ??= function(Error $err) {
            WorkerContext::$pdoConnectionPool->fill();
            WorkerContext::$redisConnectionPool->fill();
            if ($err->getPrevious() !== null) {
                $err1Type = $err::class;
                $err2Type = $err->getPrevious() != null ? $err->getPrevious()::class : '???';
                Logger::log(LogLevel::ERROR, 'GraphQL', "Caught an error of type '{$err1Type}' '{$err2Type}'.");
                error_log($err->getPrevious()->getMessage());
                error_log(print_r($err->getPrevious()->getTraceAsString(),true));
            }

            if ($err instanceof ClientAware && $err->isClientSafe()) {
                $err2 = $err->getPrevious();
                switch (true) {
                    case $err2 instanceof TypedException:
                        $type = (fn($v):TypedException=>$v)($err2)->getErrorType()->name; break; // weird thing is for removing intelephense error mark
                    case $err2 == null:
                        $type = "GENERAL"; break;
                    default:
                        $type = "UNKNOWN"; break;
                }
                $formattedError = [
                    'type' => $type,
                    'message' => $err->getMessage()
                ];
            } else $formattedError = ['message' => "Internal server error."];

            if ($err instanceof Error) {
                $locations = array_map(static fn (SourceLocation $loc): array => $loc->toSerializableArray(), $err->getLocations());
                if ($locations !== []) $formattedError['locations'] = $locations;

                if ($err->path !== null && \count($err->path) > 0) $formattedError['path'] = $err->path;
            }

            if ($err instanceof ProvidesExtensions) {
                $extensions = $err->getExtensions();
                if (\is_array($extensions) && $extensions !== []) {
                    $formattedError['extensions'] = $extensions;
                }
            }

            return $formattedError;
        };
        self::$errorHandler = $errorHandler ??= function (array $errors, callable $formatter) { return array_map($formatter,$errors); };

        self::$rules = $rules ??= array_merge(\GraphQL\GraphQL::getStandardValidationRules(),[
            new DisableIntrospection(!((int)$_SERVER['LD_GRAPHQL_ALLOW_INTROSPECTION'] === 1))
        ]);

        try {
            if (self::$generatorClassName != null) {
                $c = new \ReflectionClass(self::$generatorClassName);
                if (!$c->implementsInterface(ISchemaGenerator::class)) throw new \Exception('No schema generator found.');
                $c->getMethod('init')->invoke(null);
            }
            self::$schema->assertValid();
        } catch (\Throwable $t) {
            if ($_SERVER['LD_DEBUG'] === '1') echo $t->getMessage().PHP_EOL;
            Logger::log(LogLevel::FATAL, 'GraphQL', $t->getMessage());
            Logger::logThrowable($t);
            return;
        }

        self::$built = true;
    }
}

interface ISchemaGenerator {
    public static function init();
}
?>