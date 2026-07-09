<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SnowmanNunu\Ai\Drivers\KimiDriver;
use SnowmanNunu\Ai\DTO\AiResponse;

class KimiDriverTest extends TestCase
{
    public function test_chat(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'model' => 'kimi-coding',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello from Kimi',
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

        $driver = new KimiDriver([
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
        $this->assertEquals('Hello from Kimi', $response->content);
        $this->assertEquals('kimi-coding', $response->model);
        $this->assertEquals('kimi', $response->driver);
    }
}
