<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Webhook;

use App\PullRequest\Application\Command\AddLabelByApprovalCountCommand;
use App\PullRequest\Application\Command\CheckHookCommand;
use App\PullRequest\Application\Command\CheckMilestoneCommand;
use App\PullRequest\Application\Command\CheckTableDescriptionCommand;
use App\PullRequest\Application\Command\CheckTranslationsCommand;
use App\PullRequest\Application\Command\RequestChangesCommand;
use App\PullRequest\Application\Command\WelcomeNewContributorCommand;
use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByApprovalCountCommand;
use App\PullRequestDashboard\Application\Command\MovePullRequestCardToColumnByLabelCommand;
use App\Shared\Infrastructure\Adapter\SpyMessageBus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

class GithubWebhookTest extends WebTestCase
{
    private SpyMessageBus $commandBus;
    private AbstractBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->client->catchExceptions(false);
        /** @var SpyMessageBus $commandBus */
        $commandBus = self::$kernel->getContainer()->get(MessageBusInterface::class);
        $this->commandBus = $commandBus;
    }

    /**
     * @dataProvider successfulExecutionProvider
     *
     * @param object[] $expectedDispatchedMessages
     */
    public function testWebhook(string $eventType, string $payload, array $expectedDispatchedMessages): void
    {
        $this->client->request('POST', '/webhook/github', server: [
            'HTTP_X-GitHub-Event' => $eventType,
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, $_ENV['WEBHOOK_SECRET']),
        ], content: $payload);

        $this->assertResponseIsSuccessful();
        $this->assertEquals($expectedDispatchedMessages, $this->commandBus->getDispatchedMessages());
    }

    /**
     * @return array<array{string, string, object[]}>
     */
    public static function successfulExecutionProvider(): array
    {
        return [
            [
                'pull_request_review',
                '{
                    "action": "submitted",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "number": 123
                    },
                   "review": {
                      "state": "changes_requested"
                    }
                }',
                [
                    new RequestChangesCommand(repositoryOwner: 'owner', repositoryName: 'repo', pullRequestNumber: '123'),
                ],
            ],
            [
                'pull_request_review',
                '{
                  "action": "submitted",
                  "pull_request": {
                    "base": {
                      "repo": {
                        "name": "PrestaShop",
                        "owner": {
                          "login": "PrestaShop"
                        }
                      }
                    },
                    "number": 162
                  },
                  "review": {
                    "state": "approved"
                  }
                }',
                [
                    new MovePullRequestCardToColumnByApprovalCountCommand(
                        projectNumber: '17',
                        repositoryOwner: 'PrestaShop',
                        repositoryName: 'PrestaShop',
                        pullRequestNumber: '162'
                    ),
                    new AddLabelByApprovalCountCommand(
                        repositoryOwner: 'PrestaShop',
                        repositoryName: 'PrestaShop',
                        pullRequestNumber: '162'
                    ),
                ],
            ],
            [
                'pull_request_review',
                '{
                  "action": "submitted",
                  "pull_request": {
                    "base": {
                      "repo": {
                        "name": "docs",
                        "owner": {
                          "login": "PrestaShop"
                        }
                      }
                    },
                    "number": 162
                  },
                  "review": {
                    "state": "approved"
                  }
                }',
                [],
            ],
            [
                'pull_request_review',
                '{
                  "action": "submitted",
                  "pull_request": {
                    "labels": [
                      {
                        "name": "TE"
                      }
                    ],
                    "base": {
                      "repo": {
                        "name": "PrestaShop",
                        "owner": {
                          "login": "PrestaShop"
                        }
                      }
                    },
                    "number": 162
                  },
                  "review": {
                    "state": "approved"
                  }
                }',
                [],
            ],
            ['pull_request_review', '{"action": "", "pull_request": { "base": { "repo": { "name": "PrestaShop" } } }}', []],
            ['pull_request_review', '{"action": "submitted", "review": {"state": ""}, "pull_request": { "base": { "repo": { "name": "PrestaShop" } } }}', []],
            'Not supported event' => ['not_supported_event_type', '{"action": "not_supported_action", "pull_request": { "base": { "repo": { "name": "PrestaShop" } } }}', []],
            ['pull_request', '{"action": "", "pull_request": { "base": { "repo": { "name": "PrestaShop" } } }}', []],
            [
                'pull_request',
                '{
                    "action": "labeled",
                    "label": {
                        "name": "documentation"
                    },
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "number": 123
                    }
                }',
                [
                    new MovePullRequestCardToColumnByLabelCommand(
                        projectNumber: '17',
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                        label: 'documentation',
                    ),
                ],
            ],
            [
                'pull_request',
                '{
                    "action": "opened",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "user": {
                            "login": "contributor"
                        },
                        "draft": false,
                        "number": 123
                    }
                }',
                [
                    new CheckTableDescriptionCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                    ),
                    new WelcomeNewContributorCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                        contributor: 'contributor'
                    ),
                    new MovePullRequestCardToColumnByLabelCommand(
                        projectNumber: '17',
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                        label: 'Ready for review',
                    ),
                    new CheckTranslationsCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123'
                    ),
                    new CheckHookCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123'
                    ),
                ],
            ],
            [
                'pull_request',
                '{
                    "action": "opened",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "draft": true,
                        "number": 123
                    }
                }',
                [],
            ],
            [
                'pull_request',
                '{
                    "action": "ready_for_review",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "user": {
                            "login": "contributor"
                        },
                        "draft": false,
                        "number": 123
                    }
                }',
                [
                    new CheckTableDescriptionCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                    ),
                    new WelcomeNewContributorCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                        contributor: 'contributor'
                    ),
                    new MovePullRequestCardToColumnByLabelCommand(
                        projectNumber: '17',
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                        label: 'Ready for review',
                    ),
                    new CheckTranslationsCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123'
                    ),
                    new CheckHookCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123'
                    ),
                ],
            ],
            [
                'pull_request',
                '{
                    "action": "opened",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "docs",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "draft": false,
                        "number": 123
                    }
                }',
                [],
            ],
            [
                'pull_request',
                '{
                    "action": "opened",
                    "pull_request": {
                        "labels": [
                            {
                                "name": "TE"
                            }
                        ],
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "draft": false,
                        "number": 123
                    }
                }',
                [],
            ],
            [
                'pull_request',
                '{
                    "action": "reopened",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "number": 123
                    }
                }',
                [
                    new MovePullRequestCardToColumnByLabelCommand(
                        projectNumber: '17',
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                        label: 'Reopened',
                    ),
                    new CheckTranslationsCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123'
                    ),
                    new CheckHookCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123'
                    ),
                ],
            ],
            [
                'pull_request',
                '{
                    "action": "closed",
                    "pull_request": {
                        "merged": false,
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "number": 123
                    }
                }',
                [
                    new MovePullRequestCardToColumnByLabelCommand(
                        projectNumber: '17',
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                        label: 'Closed',
                    ),
                ],
            ],
            [
                'pull_request',
                '{
                    "action": "closed",
                    "pull_request": {
                        "merged": true,
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "number": 123
                    }
                }',
                [
                    new MovePullRequestCardToColumnByLabelCommand(
                        projectNumber: '17',
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                        label: 'Merged',
                    ),
                    new CheckMilestoneCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                    ),
                ],
            ],
            [
                'pull_request',
                '{
                    "action": "synchronize",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "state": "closed",
                        "number": 123
                    }
                }',
                [],
            ],
            [
                'pull_request',
                '{
                    "action": "synchronize",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "state": "open",
                        "number": 123
                    }
                }',
                [
                    new CheckTranslationsCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123'
                    ),
                    new CheckHookCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123'
                    ),
                ],
            ],
            [
                'pull_request',
                '{
                    "action": "edited",
                    "pull_request": {
                        "base": {
                            "repo": {
                                "name": "repo",
                                "owner": {
                                    "login": "owner"
                                }
                            }
                        },
                        "state": "open",
                        "draft": false,
                        "number": 123
                    }
                }',
                [
                    new CheckTableDescriptionCommand(
                        repositoryOwner: 'owner',
                        repositoryName: 'repo',
                        pullRequestNumber: '123',
                    ),
                ],
            ],
        ];
    }

    public function testInvalidJsonPayload(): void
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Error on json');

        $this->client->request(
            'POST',
            '/webhook/github',
            server: [
                'HTTP_X-GitHub-Event' => 'not_supported_event_type',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', '{"action"@: "not_supported_action"}', $_ENV['WEBHOOK_SECRET']),
            ],
            content: '{"action"@: "not_supported_action"}'
        );
        $this->assertResponseStatusCodeSame(400);
    }

    public function testErrorIfSignatureFromPayloadDoesntExist(): void
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is missing');

        $this->client->request('POST', '/webhook/github', server: [
            'HTTP_X-GitHub-Event' => 'eventType',
        ], content: '{}');

        $this->assertResponseStatusCodeSame(406);
    }

    public function testErrorIfThePayloadSignatureIsInvalid(): void
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Access denied');

        $this->client->request('POST', '/webhook/github', server: [
            'HTTP_X-GitHub-Event' => 'eventType',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', '{}', 'wrong_secret'),
        ], content: '{}');

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @dataProvider nothingHappenIfMethodIsDifferentThanPOSTProvider
     *
     * @param object[] $expectedDispatchedMessages
     */
    public function testNothingHappenIfMethodIsDifferentThanPOSTProvider(string $eventType, string $payload, array $expectedDispatchedMessages, string $method): void
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Request does not match.');

        $this->client->request($method, '/webhook/github', server: [
            'HTTP_X-GitHub-Event' => $eventType,
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, $_ENV['WEBHOOK_SECRET']),
        ], content: $payload);

        $this->assertResponseStatusCodeSame(406);
        $this->assertEquals($expectedDispatchedMessages, $this->commandBus->getDispatchedMessages());
    }

    /**
     * @return array<array{string, string, object[]}>
     */
    public static function nothingHappenIfMethodIsDifferentThanPOSTProvider(): array
    {
        return array_merge(
            array_map(
                fn (array $data) => [$data[0], $data[1], [], 'GET'],
                self::successfulExecutionProvider()
            ),
            array_map(
                fn (array $data) => [$data[0], $data[1], [], 'PUT'],
                self::successfulExecutionProvider()
            ),
            array_map(
                fn (array $data) => [$data[0], $data[1], [], 'PATCH'],
                self::successfulExecutionProvider()
            ),
            array_map(
                fn (array $data) => [$data[0], $data[1], [], 'DELETE'],
                self::successfulExecutionProvider()
            )
        );
    }
}
