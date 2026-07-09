<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SnowmanNunu\Ai\Drivers\WenxinDriver;
use SnowmanNunu\Ai\DTO\AiResponse;

class WenxinDriverTest extends TestCase
{
    public function testChat(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'model' => 'ernie-4.0-8k-latest',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello from Wenxin',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $driver = new WenxinDriver([
            'api_key' => 'test-key',
            'base_url' => 'http://mock',
        ]);

        $driverReflect = new \ReflectionClass($driver);
        $clientProperty = $driverReflect->getProperty('client');
        $clientProperty->setValue($driver, $client);

        $response = $driver->chat([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertInstanceOf(AiResponse::class, $response);
        $this->assertEquals('Hello from Wenxin', $response->content);
        $this->assertEquals('ernie-4.0-8k-latest', $response->model);
        $this->assertEquals('wenxin', $response->driver);
    }
}
