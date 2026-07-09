<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SnowmanNunu\Ai\Drivers\ZhipuDriver;
use SnowmanNunu\Ai\DTO\AiResponse;

class ZhipuDriverTest extends TestCase
{
    public function test_chat(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'model' => 'glm-4-flash',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello from Zhipu GLM',
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

        $driver = new ZhipuDriver([
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
        $this->assertEquals('Hello from Zhipu GLM', $response->content);
        $this->assertEquals('glm-4-flash', $response->model);
        $this->assertEquals(10, $response->usage->inputTokens);
        $this->assertEquals(5, $response->usage->outputTokens);
        $this->assertEquals(15, $response->usage->totalTokens);
        $this->assertEquals('zhipu', $response->driver);
    }
}
