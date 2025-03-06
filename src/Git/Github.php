<?php
namespace LDLib\Git;

use function LDLib\Net\curl_quickRequest;

class Github {
    public static string $userAgent = 'LDLib '.LDLIB_VERSION_WITH_SUFFIX;
    public static string $apiURL = 'https://api.github.com/graphql';

    public static function gh_getLatestCommits(?string $repoOwner=null, ?string $repoName=null, ?string $branchName=null, int $n=10):array|null {
        $repoOwner ??= $_SERVER['LD_GITHUB_MAIN_REPO_OWNER'] ?? throw new \Exception("repoOwner not set");
        $repoName ??= $_SERVER['LD_GITHUB_MAIN_REPO_NAME'] ?? throw new \Exception("repoName not set");
        $branchName ??= $_SERVER['LD_GITHUB_MAIN_REPO_BRANCH'] ?? throw new \Exception("branchName not set");

        $query = <<<GRAPHQL
        query GetLatestCommits (\$owner:String!, \$name:String!, \$query:String!) {
            repository(owner:\$owner,name:\$name) {
                refs(first:1,refPrefix:"refs/heads/",query:\$query) {
                    nodes {
                        name
                            target {
                            ... on Commit {
                                history(first:$n) {
                                    edges {
                                        node {
                                            message
                                            authoredDate
                                            committedDate
                                            author {
                                                avatarUrl
                                                name
                                                email
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        GRAPHQL;
        $variables = ['owner' => $repoOwner, 'name' => $repoName, 'query' => $branchName];
        $res = curl_quickRequest(self::$apiURL,[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type:application/json','User-Agent: '.self::$userAgent,"Authorization: bearer {$_SERVER['LD_GITHUB_PAT']}"],
            CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'variables' => $variables])
        ]);

        $json = json_decode($res['res'],true);
        if ($json == null) return null;
        $edges = $json['data']['repository']['refs']['nodes'][0]['target']['history']['edges'] ?? null;
        if ($edges == null) return null;
        
        $commits = [];
        foreach ($edges as $edge) $commits[] = $edge['node']??null;
        return $commits;
    }
}
?>