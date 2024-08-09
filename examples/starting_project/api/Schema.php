<?php
namespace BaseWebsite\Schema;

use GraphQL\Error\{Error, InvariantViolation};
use GraphQL\Language\AST\{Node, StringValueNode};
use GraphQL\Type\Definition\{InputObjectType, InterfaceType, Type, ObjectType, PhpEnumType, ResolveInfo, ScalarType, UnionType};
use LDLib\Context\Context;
use LDLib\{ErrorType,SuccessType,PageInfo,TypedException,OperationResult, PaginationVals, WSMessage};
use LDLib\AWS\AWS;
use LDLib\Context\SubscriptionRequest;
use LDLib\Swoole\SwoolePromise;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\OAuth\OAuth;
use LDLib\Server\ServerContext;
use BaseWebsite\Auth\Auth;
use BaseWebsite\Context\HTTPContext;
use BaseWebsite\Context\OAuthContext;
use BaseWebsite\Context\WSContext;
use BaseWebsite\Cache\LocalCache;
use Swoole\Timer;
use Ds\Set;
use LDLib\GraphQL\ISchemaGenerator;

use function LDLib\Utils\ArrayTools\array_merge_recursive_distinct;

class QueryType extends ObjectType {
    public function __construct() {
        parent::__construct([
            'fields' => [
                'node' => [
                    'type' => fn() => Types::Node(),
                    'args' => [
                        'id' => Type::nonNull(Type::id())
                    ],
                    'resolve' => fn($_, $args) => $args['id'],
                    'complexity' => fn($childN) => $childN
                ],
                'viewer' => [
                    'type' => fn() => Types::RegisteredUser(),
                    'resolve' => function($o,$args,$context) {
                        $user = $context->getAuthenticatedUser();
                        if ($user == null && $context instanceof OAuthContext) $user = $context?->asUser;
                        return $user?->id;
                    },
                    'complexity' => fn($childN) => $childN + RegisteredUserType::$processComplexity
                ],
                'test' => [
                    'type' => fn() => Type::nonNull(Type::boolean()),
                    'resolve' => fn() => (bool)$_SERVER['LD_TEST'],
                    'complexity' => fn($childN) => $childN
                ],
                'userlist' => [
                    'type' => fn() => Types::getConnectionObjectType('RegisteredUser'),
                    'args' => [
                        'first' => ['type' => Type::int(), 'defaultValue' => null],
                        'last' => ['type' => Type::int(), 'defaultValue' => null],
                        'after' => ['type' => Type::string(), 'defaultValue' => null],
                        'before' => ['type' => Type::string(), 'defaultValue' => null]
                    ],
                    'resolve' => function($o,$args,$context) {
                        if ($context->getAuthenticatedUser() == null) return null;
                        $pag = new PaginationVals($args['first'],$args['last'],$args['after'],$args['before'],true);

                        $v = LocalCache::prepOrGetUsers($pag);
                        if (is_array($v)) return $v;
                        return new SwoolePromise(fn() => LocalCache::getUsers($pag));
                    },
                    'complexity' => fn($childN, $args) => ($childN + RegisteredUserType::$processComplexity) * max(1,$args['first']??0 + $args['last']??0)
                ],
                'getServiceWorkerName' => [
                    'type' => fn() => Type::nonNull(Type::string()),
                    'resolve' => fn() => $_SERVER['LD_SERVICEWORKER_NAME'],
                    'complexity' => fn($childN) => $childN
                ],
                'getS3ObjectMetadata' => [
                    'type' => fn() => Types::S3ObjectMetadata(),
                    'args' => [
                        'key' => Type::string()
                    ],
                    'resolve' => function($o,$args,$context) {
                        if ($context->getAuthenticatedUser() == null) return null;
                        $redis = $context->getLDRedis();

                        $s3Key = $args['key'];
                        $redisKey = "s3:general:metadata:$s3Key";
                        $vCache = $redis->get($redisKey);
                        if ($vCache != null) return json_decode($vCache,true);

                        $s3Client = AWS::getS3Client();
                        $res = $s3Client->getObject($_SERVER['LD_AWS_BUCKET_GENERAL'],$s3Key,'bytes=0-1');
                        if ($res['statusCode'] !== 206 && $res['statusCode'] !== 200) return null;

                        $meta = [
                            '_Key' => $s3Key,
                            'ContentLength' => $res['headers']['content-length']??null,
                            'ContentType' => $res['headers']['content-type']??null
                        ];
                        $redis->set($redisKey,json_encode($meta),172800);
                        return $meta;
                    },
                    'complexity' => fn($childN) => $childN + S3ObjectMetadataType::$processComplexity
                ],
                'workersStats' => [
                    'type' => fn() => Type::nonNull(Type::listOf(Type::nonNull(Types::WorkerStats()))),
                    'resolve' => function($o,$args,$context) {
                        if ($context->getAuthenticatedUser()?->hasRole('Administrator') !== true) return null;
                        $a = [];
                        foreach (ServerContext::$workerDatas as $k => $v) $a[] = $k;
                        return $a;
                    }
                ],
                'swooleTablesStats' => [
                    'type' => fn() => Type::listOf(Type::nonNull(Types::SwooleTableStats())),
                    'resolve' => function($o,$args,$context) {
                        if ($context->getAuthenticatedUser()?->isAdministrator() !== true) return null;
                        switch (true) {
                            case $context instanceof HTTPContext: return LocalCache::getTablesStats();
                            case $context instanceof WSContext: return LocalCache::getWSTablesStats();
                            default: return null;
                        }
                    }
                ],
            ]
        ]);
    }
}

class WebSocketMode_QueryType extends ObjectType {
    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'viewer' => [
                    'type' => fn() => Types::RegisteredUser(),
                    'resolve' => fn($o,$args,$context) => $context->getAuthenticatedUser()?->id,
                    'complexity' => fn($childN) => $childN + RegisteredUserType::$processComplexity
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class MutationType extends ObjectType {
    public static int $processComplexity = 5;

    public function __construct() {
        parent::__construct([
            'fields' => [
                /** ADMIN **/
                'reloadServer' => [
                    'type' => fn() => Type::nonNull(Types::SimpleOperation()),
                    'resolve' => function ($o,$args,$context) {
                        if (!$context->getAuthenticatedUser()?->roles?->contains('Administrator')) return new OperationResult(ErrorType::NOT_ENOUGH_PRIVILEGES);

                        Timer::after(3000,fn() => $context->server->reload());

                        return new OperationResult(SuccessType::SUCCESS);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ],
                'reindexS3' => [
                    'type' => fn() => Type::nonNull(Types::SimpleOperation()),
                    'args' => [
                        'bucketName' => Type::nonNull(Type::string()),
                        'dbName' => Type::nonNull(Type::string())
                    ],
                    'resolve' => function ($o,$args,HTTPContext $context) {
                        if (!$context->getAuthenticatedUser()?->roles?->contains('Administrator')) return new OperationResult(ErrorType::NOT_ENOUGH_PRIVILEGES);
                        $bucketName = $args['bucketName'];
                        $dbName = $args['dbName'];

                        $s3 = AWS::getS3Client();
                        $nextContinuationToken = null;
                        $pdo = $context->getLDPDO();
                        $pdo->query('START TRANSACTION');
                        $pdo->query("DELETE FROM $dbName");
                        do {
                            $res = $s3->listObjects($bucketName,100,$nextContinuationToken);
                            if (!$res->resultType instanceof SuccessType || $res->data['statusCode'] !== 200) return new OperationResult(ErrorType::UNKNOWN);
                            $xml = simplexml_load_string($res->data['data']);

                            $nextContinuationToken = (string)$xml?->NextContinuationToken;

                            foreach (($xml?->Contents??[]) as $item) {
                                $resHead = $s3->headObject($bucketName,$item->Key);
                                if (!$resHead->resultType instanceof SuccessType) {
                                    Logger::log(LogLevel::WARN, 'reindexS3', "Skipped object with key '$item->Key' (1)");
                                    continue;
                                }
                                $headers = $resHead->data['headers'];

                                $metadatas = [];
                                foreach ($headers as $k => $v) if (preg_match('/^x-amz-meta-(.*)/',$k,$m) > 0) $metadatas[$m[1]] = $v;
                                $userId = (int)($metadatas['userid']??-1);
                                if ($userId < 1) { Logger::log(LogLevel::WARN, 'reindexS3', "Skipped object with key '$item->Key' (2)"); continue; }

                                $stmt = $pdo->prepare("INSERT INTO $dbName (obj_key,size,mime_type,status,metadata) VALUES (?,?,?,?,?)");
                                $stmt->execute([$item->Key,$item->Size,$headers['content-type'],'Verified',json_encode($metadatas)]);
                            }
                        } while ($nextContinuationToken != null);
                        $pdo->query('COMMIT');

                        return new OperationResult(SuccessType::SUCCESS);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ],
                'cleanLogs' => [
                    'type' => fn() => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                    'args' => [
                        'seconds' => [ 'type' => Type::int(), 'defaultValue' => 86400*7 ],
                        'test' => [ 'type' => Type::boolean(), 'defaultValue' => false ]
                    ],
                    'resolve' => function ($o,$args,HTTPContext $context) {
                        if (!$context->getAuthenticatedUser()?->roles?->contains('Administrator')) return null;
                        return Logger::cleanLogFiles($args['seconds'],$args['test']);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity
                ],
                'cleanSwooleHTTPLogs' => [
                    'type' => fn() => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                    'args' => [
                        'seconds' => [ 'type' => Type::int(), 'defaultValue' => 86400*7 ],
                        'test' => [ 'type' => Type::boolean(), 'defaultValue' => false ]
                    ],
                    'resolve' => function ($o,$args,HTTPContext $context) {
                        if (!$context->getAuthenticatedUser()?->roles?->contains('Administrator')) return null;
                        return Logger::cleanSwooleHTTPLogFiles($args['seconds'],$args['test']);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity
                ],
                'cleanSwooleWSLogs' => [
                    'type' => fn() => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                    'args' => [
                        'seconds' => [ 'type' => Type::int(), 'defaultValue' => 86400*7 ],
                        'test' => [ 'type' => Type::boolean(), 'defaultValue' => false ]
                    ],
                    'resolve' => function ($o,$args,HTTPContext $context) {
                        if (!$context->getAuthenticatedUser()?->roles?->contains('Administrator')) return null;
                        return Logger::cleanSwooleWSLogFiles($args['seconds'],$args['test']);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity
                ],
                'cleanPHPLogs' => [
                    'type' => fn() => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                    'args' => [
                        'test' => [ 'type' => Type::boolean(), 'defaultValue' => false ]
                    ],
                    'resolve' => function ($o,$args,HTTPContext $context) {
                        if (!$context->getAuthenticatedUser()?->roles?->contains('Administrator')) return null;
                        return Logger::cleanPHPLogFiles($args['test']);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity
                ],
                /** AUTH **/
                'loginUser' => [
                    'type' => fn() => Type::nonNull(Types::getOperationObjectType('OnRegisteredUser')),
                    'args' => [
                        'username' => Type::nonNull(Type::string()),
                        'password' => Type::nonNull(Type::string()),
                        'rememberMe' => [ 'type' => Type::nonNull(Type::boolean()), 'defaultValue' => false ]
                    ],
                    'resolve' => function($o,$args,$context) {
                        if ($context->getAuthenticatedUser() != null) return new OperationResult(ErrorType::CONTEXT_INVALID);
                        return Auth::loginUser($context,$args['username'],$args['password'],$args['rememberMe']);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + RegisteredUserType::$processComplexity
                ],
                'logoutUser' => [
                    'type' => fn() => Type::nonNull(Types::SimpleOperation()),
                    'resolve' => function($o,$args,$context) {
                        $user = $context->getAuthenticatedUser();
                        if ($user == null) return new OperationResult(ErrorType::NOT_AUTHENTICATED);
                        return Auth::logoutUser($context);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ],
                'changePassword' => [
                    'type' => fn() => Types::SimpleOperation(),
                    'args' => [
                        'oldPassword' => Type::nonNull(Type::string()),
                        'newPassword' => Type::nonNull(Type::string())
                    ],
                    'resolve' => function ($o,$args,$context) {
                        $user = $context->getAuthenticatedUser();
                        if ($user == null) return new OperationResult(ErrorType::NOT_AUTHENTICATED);
                        return Auth::changePassword($context->getLDPDO(),$user->id,$args['oldPassword'],$args['newPassword']);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ],
                'logoutUserFromEverything' => [
                    'type' => fn() => Type::nonNull(Types::SimpleOperation()),
                    'resolve' => function($o,$args,Context $context) {
                        $user = $context->getAuthenticatedUser();
                        if ($user == null) return new OperationResult(ErrorType::NOT_AUTHENTICATED);
                        return Auth::logoutUserFromEverything($context->getLDPDO(), $user->id);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ],
                'registerUser' => [
                    'type' => fn() => Type::nonNull(Types::getOperationObjectType('OnRegisteredUser')),
                    'args' => [
                        'username' => Type::nonNull(Type::string()),
                        'password' => Type::nonNull(Type::string())
                    ],
                    'resolve' => function($o,$args,Context $context) {
                        $authUser = $context->getAuthenticatedUser();
                        if ($authUser != null) return new OperationResult(ErrorType::CONTEXT_INVALID, "A user is currently authenticated.");
                        return Auth::registerUser($context, $args['username'], $args['password']);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + RegisteredUserType::$processComplexity
                ],
                'selfban' => [
                    'type' => fn() => Type::nonNull(Types::SimpleOperation()),
                    'args' => [
                        'duration' => ['type' => Type::int(), 'defaultValue' => null],
                        'endDate' => ['type' => Types::DateTime(), 'defaultValue' => null]
                    ],
                    'resolve' => function ($o,$args,$context) {
                        $user = $context->getAuthenticatedUser();
                        if ($user == null) return new OperationResult(ErrorType::NOT_AUTHENTICATED);
                        if (isset($args['duration'],$args['endDate']) || ($args['duration'] == null && $args['endDate'] == null))
                            return new OperationResult(ErrorType::INVALID_DATA, 'Set either "duration" or "endDate".');

                        if ($args['duration'] != null) $endDate = new \DateTimeImmutable('@'.(strtotime('now')+$args['duration']));
                        else if ($args['endDate'] != null) $endDate = $args['endDate'];
                        else return new OperationResult(ErrorType::UNKNOWN);

                        if ($endDate->getTimestamp() > time()+604800) // 604800 = 7 days
                            return new OperationResult(ErrorType::INVALID_DATA, "The end of the ban should be at most a week from now.");

                        return $user->ban($context->getLDPDO(), $endDate, 'selfban');
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ],
                /** WebPush & Notifications **/
                'registerPushSubscription' => [
                    'type' => fn() => Type::nonNull(Types::SimpleOperation()),
                    'args' => [
                        'endpoint' => Type::nonNull(Type::string()),
                        'expirationTime' => ['type' => Type::float(), 'defaultValue' => null],
                        'userVisibleOnly'=> Type::nonNull(Type::boolean()),
                        'publicKey' => Type::nonNull(Type::string()),
                        'authToken' => Type::nonNull(Type::string())
                    ],
                    'resolve' => function ($o,$args,$context) {
                        $user = $context->getAuthenticatedUser();
                        if ($user == null) return new OperationResult(ErrorType::NOT_AUTHENTICATED);
                        $sNow = (new \DateTime('now'))->format('Y-m-d H:i:s');
                        $conn = $context->getLDPDO();

                        $stmt = $conn->prepare("SELECT * FROM push_subscriptions WHERE user_id=? AND endpoint=?");
                        $stmt->execute([$user->id,$args['endpoint']]);
                        if ($stmt->fetch() !== false) return new OperationResult(ErrorType::DUPLICATE);

                        $stmt = $conn->prepare(<<<SQL
                            INSERT INTO push_subscriptions (user_id,remote_public_key,date,endpoint,expiration_time,user_visible_only,auth_token)
                            VALUES (:userId,:remotePublicKey,:date,:endpoint,:expirationTime,:userVisibleOnly,:authToken)
                            SQL
                        );
                        $res = $stmt->execute([
                            ':userId' => $user->id,
                            ':endpoint' => $args['endpoint'],
                            ':expirationTime' => $args['expirationTime'],
                            ':userVisibleOnly' => $args['userVisibleOnly'],
                            ':remotePublicKey' => $args['publicKey'],
                            ':authToken' => $args['authToken'],
                            ':date' => $sNow
                        ]);
                        return new OperationResult($res === true ? SuccessType::SUCCESS : ErrorType::DATABASE_ERROR);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ],
                /** OAuth **/
                'oauth_registerClient' => [
                    'type' => fn() => Types::SimpleOperation(),
                    'args' => [
                        'clientName' => Type::nonNull(Type::string()),
                        'redirectURIs' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                        'website' => ['type' => Type::string(), 'defaultValue' => null],
                        'description' => ['type' => Type::string(), 'defaultValue' => null],
                        'logo' => ['type' => Type::string(), 'defaultValue' => null]
                    ],
                    'resolve' => function($o,$args,$context) {
                        $user = $context->getAuthenticatedUser();
                        if ($user == null) return new OperationResult(ErrorType::NOT_AUTHENTICATED);
                        return OAuth::registerClient($context->getLDPDO(),$user->id,$args['redirectURIs'],$args['clientName'],$args['website'],$args['description'],$args['logo']);
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ],
                'oauth_finishCodeAuthorization' => [
                    'type' => fn() => Type::nonNull(Types::SimpleOperation()),
                    'args' => [
                        'authId' => Type::nonNull(Type::string()),
                        'allowed' => Type::nonNull(Type::boolean())
                    ],
                   'resolve' => function($o,$args,$context) {
                        $user = $context->getAuthenticatedUser();
                        if ($user == null) return new OperationResult(ErrorType::NOT_AUTHENTICATED);
                        $res = OAuth::processAuthorizationRequest_code($context->getLDPDO(),$context->getLDRedis(),$user->id,$args['authId'],$args['allowed']);
                        if ($res->resultType instanceof SuccessType) $res->resultMsg = $res->data['url'];
                        return $res;
                   },
                   'complexity' => fn($childN) => $childN + self::$processComplexity + SimpleOperationType::$processComplexity
                ]
            ]
        ]);
    }
}

class SubscriptionType extends ObjectType {
    public static int $processComplexity = 50;
    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'listenToShoutings' => [
                    'type' => fn() => Type::nonNull(Types::WSSimpleMessage()),
                    'resolve' => function($o,$args,$context,$ri) {
                        if ($context instanceof WSContext) {
                            $context->subRequest = new SubscriptionRequest($ri->fieldName,['_alias' => $ri->path[count($ri->path)-1]]);
                            return new WSMessage([],'[XXX]');
                        }
                        return new WSMessage([],'Can listen to shoutings.');
                    },
                    'complexity' => fn($childN) => $childN + self::$processComplexity + WSSimpleMessageType::$processComplexity
                ]
            ],
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class WebSocketMode_MutationType extends ObjectType {
    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'unsubscribe' => [
                    'type' => fn() => Types::SimpleOperation(),
                    'args' => [
                        'names' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                        'vals' => Type::listOf(Type::string())
                    ],
                    'resolve' => function($o,$args,$context) {
                        $context->subRequest = new SubscriptionRequest('--unsubscribing--');
                        $aNames = $args['names']??[];
                        $aVals = $args['vals']??[];

                        $aRemoved = [];
                        $aNotFound = [];
                        $aInvalid = [];
                        for ($i=0; $i<count($aNames); $i++) {
                            $name = $aNames[$i];
                            $data = $aVals[$i]??null;
                            if ($data != null) $data = json_decode($data,true);
                            switch (LocalCache::removeSubscription($context, $name, $data)) {
                                case 1: $aRemoved[] = $name; break;
                                case 0: $aNotFound[] = $name; break;
                                case -1: $aInvalid[] = $name; break;
                            }
                        }

                        if (count($aRemoved) > 0) {
                            if (count($aNotFound) + count($aInvalid) === 0) return new OperationResult(SuccessType::SUCCESS, 'Unsuscribed from given names.');
                            else return new OperationResult(SuccessType::PARTIAL_SUCCESS, 'Unsubscribed from given names, except: "'.implode('"; "',array_merge($aNotFound,$aInvalid)).'".');
                        } else return new OperationResult(ErrorType::NOT_FOUND, 'Unsubscribed from none of the given names.');
                    },
                    'complexity' => fn($childN) => $childN + SimpleOperationType::$processComplexity + 200
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

/***** Interfaces *****/

class NodeType extends InterfaceType {
    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'id' => Type::id()
            ],
            'resolveType' => function ($id) {
                switch (true) {
                    case (preg_match('/^forum_\d+$/i',$id,$m) > 0): $s = 'Thread'; break;
                    case (preg_match('/^forum_tid_\d+$/i',$id,$m) > 0): $s = 'TidThread'; break;
                    case (preg_match('/^forum_\d+-\d+$/i',$id,$m) > 0): $s = 'Comment'; break;
                    case (preg_match('/^forum_tid_\d+-\d+$/i',$id,$m) > 0): $s = 'TidComment'; break;
                    default: throw new TypedException("Couldn't find a node with id '$id'.", ErrorType::NOT_FOUND);
                }

                if (isset($s)) try {
                    $rm = (new \ReflectionMethod(Types::class, $s));
                    return $rm->invoke(null);
                } catch (\Exception $e) { }

                throw new TypedException("Couldn't find a node with id '$id'.", ErrorType::NOT_FOUND);
            }
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class OperationType extends InterfaceType {
    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'success' => [
                    'type' => fn() => Type::nonNull(Type::boolean()),
                    'resolve' => fn($o) => $o->resultType instanceof SuccessType,
                    'complexity' => fn($childN) => $childN
                ],
                'resultCode' => [
                    'type' => fn() => Type::nonNull(Type::string()),
                    'resolve' => fn($o) => $o->resultType->name,
                    'complexity' => fn($childN) => $childN
                ],
                'resultMessage' => [
                    'type' => fn() => Type::nonNull(Type::string()),
                    'resolve' => fn($o) => $o->resultMsg,
                    'complexity' => fn($childN) => $childN
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class WSMessageType extends InterfaceType {
    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'tags' => [
                    'type' => fn() => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                    'resolve' => fn($o) => $o instanceof WSMessage ? $o->tags : [],
                    'complexity' => fn($childN) => $childN
                ],
                'message' => [
                    'type' => fn() => Type::nonNull(Type::string()),
                    'resolve' => fn($o) =>  $o instanceof WSMessage ? $o->message : '',
                    'complexity' => fn($childN) => $childN
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}
/***** Parent Classes *****/

class SimpleOperationType extends ObjectType {
    public static int $processComplexity = 0;

    public function __construct(array $config2 = null) {
        $config = [
            'interfaces' => [Types::Operation()],
            'fields' => [
                Types::Operation()->getField('success'),
                Types::Operation()->getField('resultCode'),
                Types::Operation()->getField('resultMessage')
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class WSSimpleMessageType extends ObjectType {
    public static int $processComplexity = 0;

    public function __construct(array $config2 = null) {
        $config = [
            'interfaces' => [Types::WSMessage()],
            'fields' => [
                Types::WSMessage()->getField('tags'),
                Types::WSMessage()->getField('message')
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class ConnectionType extends ObjectType {
    public static function getEmptyConnection() {
        return ['data' => [], 'metadata' => ['pageInfo' => new PageInfo(null,null,false,false,1,1,0)]];
    }

    public function __construct(callable $edgeType, array $config2 = null) {
        $config = [
            'fields' => [
                'pageInfo' => [
                    'type' => fn() => Type::nonNull(Types::PageInfo()),
                    'complexity' => fn($childN) => $childN + PageInfoType::$processComplexity
                ],
                'edges' => [
                    'type' => fn() => Type::listOf($edgeType()),
                    'complexity' => fn($childN) => $childN
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class EdgeType extends ObjectType {
    public function __construct(callable $nodeType, array $config2 = null) {
        $config = [
            'fields' => [
                'node' => [
                    'type' => fn() => $nodeType(),
                    'complexity' => fn($childN) => $childN
                ],
                'cursor' => [
                    'type' => Type::nonNull(Type::string()),
                    'complexity' => fn($childN) => $childN
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class PageInfoType extends ObjectType {
    public static int $processComplexity = 0;

    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'hasNextPage' => [
                    'type' => fn() => Type::nonNull(Type::boolean()),
                    'resolve' => fn($o) => $o['hasNextPage'],
                    'complexity' => fn($childN) => $childN
                ],
                'hasPreviousPage' => [
                    'type' => fn() => Type::nonNull(Type::boolean()),
                    'resolve' => fn($o) => $o['hasPreviousPage'],
                    'complexity' => fn($childN) => $childN
                ],
                'startCursor' => [
                    'type' => fn() => Type::string(),
                    'resolve' => fn($o) => $o['startCursor'],
                    'complexity' => fn($childN) => $childN
                ],
                'endCursor' => [
                    'type' => fn() => Type::string(),
                    'resolve' => fn($o) => $o['endCursor'],
                    'complexity' => fn($childN) => $childN
                ],
                'pageCount' => [
                    'type' => fn() => Type::int(),
                    'resolve' => fn($o) => $o['pageCount'],
                    'complexity' => fn($childN) => $childN
                ],
                'currPage' => [
                    'type' => fn() => Type::int(),
                    'resolve' => fn($o) => $o['currPage'],
                    'complexity' => fn($childN) => $childN
                ],
                'itemsCount' => [
                    'type' => fn() => Type::int(),
                    'resolve' => fn($o) => $o['itemsCount'],
                    'complexity' => fn($childN) => $childN
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

/***** Scalars *****/

class DateTimeType extends ScalarType {
    public function serialize($value) {
        if ($value instanceof \DateTimeInterface) $value->format('Y-m-d H:i:s');
        else if (is_string($value) && preg_match('/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/', $value) > 0) return $value;
        else if (is_string($value) && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $value) > 0) return "$value 00:00:00";

        throw new InvariantViolation("Could not serialize following value as DateTime: ".\GraphQL\Utils\Utils::printSafe($value));
    }

    public function parseValue($value) {
        if (!is_string($value) || preg_match('/^(?:\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:.\d{3})?Z|\d{4}-\d\d-\d\d(?: \d\d:\d\d:\d\d)?)$/', $value) == 0)
            throw new Error("Cannot represent following value as DateTime: ".\GraphQL\Utils\Utils::printSafeJson($value));

        try {
            $v = new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new Error("Cannot represent following value as DateTime: ".\GraphQL\Utils\Utils::printSafeJson($value));
        }

        return $v;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null) {
        if (!$valueNode instanceof StringValueNode)
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);

        $s = $valueNode->value;
        if (preg_match('/^(?:\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:.\d{3})?Z|\d{4}-\d\d-\d\d(?: \d\d:\d\d:\d\d)?)$/', $s) == 0) throw new Error("Not a valid datetime: '$s'", [$valueNode]);
        try { $v = new \DateTimeImmutable($s); } catch (\Exception $e) { throw new Error("Not a valid datetime: '$s'", [$valueNode]); }

        return $v;
    }
}

class DateType extends ScalarType {
    public function serialize($value) {
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
        else if (is_string($value) && preg_match('/^\d\d\d\d-\d\d-\d\d$/', $value, $m) > 0) return $value;

        throw new InvariantViolation("Could not serialize following value as Date: ".\GraphQL\Utils\Utils::printSafe($value));
    }

    public function parseValue($value) {
        if (!is_string($value) || preg_match('/^\d\d\d\d-\d\d-\d\d$/', $value) == 0)
            throw new Error("Cannot represent following value as Date: ".\GraphQL\Utils\Utils::printSafeJson($value));

        try {
            $v = new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new Error("Cannot represent following value as Date: ".\GraphQL\Utils\Utils::printSafeJson($value));
        }

        return $v;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null) {
        if (!$valueNode instanceof StringValueNode)
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);

        $s = $valueNode->value;
        if (preg_match('/^\d\d\d\d-\d\d-\d\d$/', $s) == 0) throw new Error("Not a valid date: '$s'", [$valueNode]);
        try { $v = new \DateTimeImmutable($s); } catch (\Exception $e) { throw new Error("Not a valid date: '$s'", [$valueNode]); }

        return $v;
    }
}

class TimeType extends ScalarType {
    public function serialize($value) {
        if ($value instanceof \DateTimeInterface) return $value->format('H:i:s');
        else if (is_string($value) && preg_match('/^\d\d:\d\d:\d\d$/', $value, $m) > 0) return $value;

        throw new InvariantViolation("Could not serialize following value as Time: ".\GraphQL\Utils\Utils::printSafe($value));
    }

    public function parseValue($value) {
        if (!is_string($value) || preg_match('/^\d\d:\d\d:\d\d$/', $value) == 0)
            throw new Error("Cannot represent following value as Time: ".\GraphQL\Utils\Utils::printSafeJson($value));

        try {
            $v = new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new Error("Cannot represent following value as Time: ".\GraphQL\Utils\Utils::printSafeJson($value));
        }

        return $v;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null) {
        if (!$valueNode instanceof StringValueNode)
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);

        $s = $valueNode->value;
        if (preg_match('/^\d\d:\d\d:\d\d$/', $s) == 0) throw new Error("Not a valid time: '$s'", [$valueNode]);
        try { $v = new \DateTimeImmutable($s); } catch (\Exception $e) { throw new Error("Not a valid time: '$s'", [$valueNode]); }

        return $v;
    }
}

class EmailType extends ScalarType {
    public function serialize($value) {
        if (is_string($value) && preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $value, $m) > 0) return $value;

        throw new InvariantViolation("Could not serialize following value as Email: ".\GraphQL\Utils\Utils::printSafe($value));
    }

    public function parseValue($value) {
        if (!is_string($value) || preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $value) == 0)
            throw new Error("Cannot represent following value as Email: ".\GraphQL\Utils\Utils::printSafeJson($value));

        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null) {
        if (!$valueNode instanceof StringValueNode)
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);

        $s = $valueNode->value;
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $s) == 0) throw new Error("Not a valid email: '$s'", [$valueNode]);

        return $s;
    }
}

/***** Admin *****/

class WorkerStatsType extends ObjectType {
    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'workerId' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o,
                    'complexity' => fn($childN) => $childN
                ],
                'nRequests' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => ServerContext::workerGet($o,'nRequests'),
                    'complexity' => fn($childN) => $childN
                ],
                'memUsage' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => ServerContext::workerGet($o,'mem_usage'),
                    'complexity' => fn($childN) => $childN
                ],
                'memUsage_true' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => ServerContext::workerGet($o,'true_mem_usage'),
                    'complexity' => fn($childN) => $childN
                ],
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

class SwooleTableStatsType extends ObjectType {
    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                'name' => [
                    'type' => fn() => Type::nonNull(Type::string()),
                    'resolve' => fn($o) => $o['name'],
                    'complexity' => fn($childN) => $childN
                ],
                'count' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['count'],
                    'complexity' => fn($childN) => $childN
                ],
                'size' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['size'],
                    'complexity' => fn($childN) => $childN
                ],
                'memorySize' => [
                    'type' => fn() => Type::int(),
                    'resolve' => fn($o) => $o['memorySize'],
                    'complexity' => fn($childN) => $childN
                ],
                'stats_num' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['stats_num'],
                    'complexity' => fn($childN) => $childN
                ],
                'stats_conflict_count' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['stats_conflict_count'],
                    'complexity' => fn($childN) => $childN
                ],
                'stats_conflict_max_level' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['stats_conflict_max_level'],
                    'complexity' => fn($childN) => $childN
                ],
                'stats_insert_count' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['stats_insert_count'],
                    'complexity' => fn($childN) => $childN
                ],
                'stats_update_count' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['stats_update_count'],
                    'complexity' => fn($childN) => $childN
                ],
                'stats_delete_count' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['stats_delete_count'],
                    'complexity' => fn($childN) => $childN
                ],
                'stats_available_slice_num' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['stats_available_slice_num'],
                    'complexity' => fn($childN) => $childN
                ],
                'stats_total_slice_num' => [
                    'type' => fn() => Type::nonNull(Type::int()),
                    'resolve' => fn($o) => $o['stats_total_slice_num'],
                    'complexity' => fn($childN) => $childN
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

/***** User *****/

class RegisteredUserType extends ObjectType {
    public static int $processComplexity = 3;
    private static array $cache = [];
    public static function process(Context $context, ResolveInfo $ri, mixed $o, callable $f) {
        if ($context instanceof OAuthContext) {
            if ($context?->asUser == null) return null;
            $pass = false;
            foreach ($context->scope as $v) {
                switch ($v) {
                    case 'user:basic': if (preg_match('/^(?:id|dbId|name|avatarURL)$/i', $ri->fieldName) > 0) { $pass = true; break; }
                }
                if ($pass) break;
            }
            if (!$pass) return null;
        } else if ($context->getAuthenticatedUser() == null) return null;

        if (is_array($o) && isset($o['cursor'],$o['edge'])) {
            if ($o['edge']['metadata']['fromDb'] == 'threadlists_editors') $o = $o['edge']['data']['editor_id'];
            else $o = $o['edge']['data']['id'];
        }

        if (isset(self::$cache[$o])) return $f(self::$cache[$o]);

        $data = LocalCache::getUser($context->getLDRedis(), $o);
        if ($data != null) return $f($data);

        LocalCache::prepOrGetUser($context->getLDRedis(), $o);
        Timer::after(5000,function() use($o) { unset(self::$cache[$o]); });
        return new SwoolePromise(function() use($o,$context,$f) {
            $data = LocalCache::getUser($context->getLDRedis(), $o);
            self::$cache[$o] = $data;
            return $data['data'] == null ? null : $f($data);
        });
    }

    public function __construct(array $config2 = null) {
        $config = [
            'interfaces' => [Types::Node()],
            'fields' => [
                'id' => [
                    'type' => fn() => Type::id(),
                    'resolve' => fn($o,$args,$context,$ri) => self::process($context, $ri, $o, fn($row) => 'USER_'.$row['data']['id']),
                    'complexity' => fn($childN) => $childN
                ],
                'dbId' => [
                    'type' => fn() => Type::int(),
                    'resolve' => fn($o,$args,$context,$ri) => self::process($context, $ri, $o, fn($row) => $row['data']['id']),
                    'complexity' => fn($childN) => $childN
                ],
                'name' => [
                    'type' => fn() => Type::string(),
                    'resolve' => fn($o,$args,$context,$ri) => self::process($context, $ri, $o, fn($row) => $row['data']['name']),
                    'complexity' => fn($childN) => $childN
                ],
                'roles' => [
                    'type' => fn() => Type::listOf(Type::nonNull(Type::string())),
                    'resolve' => fn($o,$args,$context,$ri) => self::process($context, $ri, $o, fn($row) => (strlen($row['data']['roles']) > 0 ? explode(',',$row['data']['roles']) : [])),
                    'complexity' => fn($childN) => $childN
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

/***** Others *****/

class S3ObjectMetadataType extends ObjectType {
    public static int $processComplexity = 1;

    public function __construct(array $config2 = null) {
        $config = [
            'fields' => [
                '_key' => [
                    'type' => fn() => Type::string(),
                    'resolve' => function($res) {
                        return $res['_Key']??null;
                    },
                    'complexity' => fn($childN) => $childN
                ],
                'contentType' => [
                    'type' => fn() => Type::string(),
                    'resolve' => function($res) {
                        return $res['ContentType']??null;
                    },
                    'complexity' => fn($childN) => $childN
                ],
                'contentLength' => [
                    'type' => fn() => Type::int(),
                    'resolve' => function($res) {
                        return ((int)$res['ContentLength'])??null;
                    },
                    'complexity' => fn($childN) => $childN
                ]
            ]
        ];
        parent::__construct($config2 == null ? $config : array_merge_recursive_distinct($config,$config2));
    }
}

/***** Support Classes *****/

class Generator implements ISchemaGenerator {
    public static Set $generatedConnections;

    public static function init() {
        self::$generatedConnections = new Set();

        self::genQuickOperation('OnRegisteredUser',['registeredUser' => 'Types::RegisteredUser()']);
    }

    public static function genConnection(string $objectType) {
        if (preg_match('/^\w+$/',$objectType) === 0) throw new \Exception("genConnection error: $objectType");
        eval(<<<PHP
        namespace BaseWebsite\Schema;

        class {$objectType}sConnectionType extends ConnectionType {
            public function __construct(array \$config2 = null) {
                \$config = [
                    'fields' => [
                        'edges' => [
                            'resolve' => fn(\$o) => \$o['data']
                        ],
                        'pageInfo' => [
                            'resolve' => fn(\$o) =>  \$o['metadata']['pageInfo']
                        ]
                    ]
                ];
                parent::__construct(fn() => Types::getEdgeObjectType('{$objectType}'), \$config2 == null ? \$config : array_merge_recursive_distinct(\$config,\$config2));
            }
        }

        class {$objectType}EdgeType extends EdgeType {
            public function __construct(array \$config2 = null) {
                \$config = [
                    'fields' => [
                        'node' => [
                            'resolve' => fn(\$o) => \$o
                        ],
                        'cursor' => [
                            'resolve' => fn(\$o) => \$o['cursor']
                        ]
                    ]
                ];
                parent::__construct(fn() => Types::{$objectType}(), \$config2 == null ? \$config : array_merge_recursive_distinct(\$config,\$config2));
            }
        }
        PHP);
        self::$generatedConnections[] = $objectType;
        Types::$types["{$objectType}sConnection"] ??= ((new \ReflectionClass("\\BaseWebsite\\Schema\\{$objectType}sConnectionType"))->newInstance());
    }

    public static function genQuickOperation(string $name, array $fieldsKV) {
        if (preg_match('/^\w+$/',$name) === 0) throw new \Exception("genOperation error: $name");
        $sFields = '';
        $iKV = 0;
        foreach ($fieldsKV as $k => $v)
            if (preg_match('/^\w+$/',$k) === 0 || preg_match('/^[\w:\(\)$]+$/',$v) === 0) throw new \Exception("genOperation error: [$k=>$v]");
            else {
                $sFields .= <<<PHP
                '$k' => [
                    'type' => $v,
                    'resolve' => fn(\$o) => \$o->fieldsData[$iKV]??null
                ],
                PHP;
                $iKV++;
            }

        eval(<<<PHP
        namespace BaseWebsite\Schema;
        use GraphQL\Type\Definition\{ObjectType, Type};
        use LDLib\General\ErrorType;

        class Operation{$name}Type extends ObjectType {
            public function __construct(array \$config2 = null) {
                \$config = [
                    'interfaces' => [Types::Operation()],
                    'fields' => [
                        Types::Operation()->getField('success'),
                        Types::Operation()->getField('resultCode'),
                        Types::Operation()->getField('resultMessage'),
                        $sFields
                    ]
                ];
                parent::__construct(\$config2 == null ? \$config : array_merge_recursive_distinct(\$config,\$config2));
            }
        }
        PHP);
    }
}

class Types {
    public static array $types = [];

    public static function Query():QueryType {
        return self::$types['Query'] ??= new QueryType();
    }

    public static function WebSocketMode_Query():WebSocketMode_QueryType {
        return self::$types['WebSocketMode_Query'] ??= new WebSocketMode_QueryType();
    }

    public static function Mutation():MutationType {
        return self::$types['Mutation'] ??= new MutationType();
    }

    public static function Subscription():SubscriptionType {
        return self::$types['Subscription'] ??= new SubscriptionType();
    }

    public static function WebSocketMode_Mutation():WebSocketMode_MutationType {
        return self::$types['WebSocketMode_Mutation'] ??= new WebSocketMode_MutationType();
    }

    public static function getConnectionObjectType(string $s) {
        if (!Generator::$generatedConnections->contains($s)) Generator::genConnection($s);
        return self::$types["{$s}sConnection"];
    }

    public static function getEdgeObjectType(string $s) {
        if (!Generator::$generatedConnections->contains($s)) Generator::genConnection($s);
        return self::$types["{$s}Edge"] ??= (new \ReflectionClass("\\BaseWebsite\\Schema\\{$s}EdgeType"))->newInstance();
    }

    public static function getOperationObjectType(string $name) {
        return self::$types["Operation{$name}Type"] ??= (new \ReflectionClass("\\BaseWebsite\\Schema\\Operation{$name}Type"))->newInstance();
    }

    /***** Interfaces *****/

    public static function Node():NodeType {
        return self::$types['Node'] ??= new NodeType();
    }

    public static function Operation():OperationType {
        return self::$types['Operation'] ??= new OperationType();
    }

    public static function WSMessage():WSMessageType {
        return self::$types['WSMessage'] ??= new WSMessageType();
    }

    /***** Parent Classes and Unions *****/

    public static function SimpleOperation():SimpleOperationType {
        return self::$types['SimpleOperation'] ??= new SimpleOperationType();
    }

    public static function WSSimpleMessage():WSSimpleMessageType {
        return self::$types['WSSimpleMessage'] ??= new WSSimpleMessageType();
    }

    public static function PageInfo():PageInfoType {
        return self::$types['PageInfo'] ??= new PageInfoType();
    }

    /***** Scalars *****/

    public static function DateTime():DateTimeType {
        return self::$types['DateTime'] ??= new DateTimeType();
    }

    public static function Date():DateType {
        return self::$types['Date'] ??= new DateType();
    }

    public static function Time():TimeType {
        return self::$types['Time'] ??= new TimeType();
    }

    public static function Email():EmailType {
        return self::$types['Email'] ??= new EmailType();
    }

    /*****  *****/
    public static function WorkerStats():WorkerStatsType {
        return self::$types['WorkerStats'] ??= new WorkerStatsType();
    }

    public static function SwooleTableStats():SwooleTableStatsType {
        return self::$types['SwooleTableStats'] ??= new SwooleTableStatsType();
    }

    public static function RegisteredUser():RegisteredUserType {
        return self::$types['RegisteredUser'] ??= new RegisteredUserType();
    }

    public static function S3ObjectMetadata():S3ObjectMetadataType {
        return self::$types['S3ObjectMetadata'] ??= new S3ObjectMetadataType();
    }
}
?>