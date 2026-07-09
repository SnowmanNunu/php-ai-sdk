<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SnowmanNunu\Ai\Drivers\ClaudeDriver;
use SnowmanNunu\Ai\DTO\AiResponse;

class ClaudeDriverTest extends TestCase
{
    public function test_chat(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'model' => 'claude-sonnet-4-6',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello from Claude'],
                ],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $driver = new ClaudeDriver([
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
        $this->assertEquals('Hello from Claude', $response->content);
        $this->assertEquals('claude-sonnet-4-6', $response->model);
        $this->assertEquals(10, $response->usage->inputTokens);
        $this->assertEquals(5, $response->usage->outputTokens);
        $this->assertEquals(15, $response->usage->totalTokens);
        $this->assertEquals('claude', $response->driver);
    }
}
