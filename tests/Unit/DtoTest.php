<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SnowmanNunu\Ai\DTO\AiResponse;
use SnowmanNunu\Ai\DTO\StreamChunk;
use SnowmanNunu\Ai\DTO\TokenUsage;

class DtoTest extends TestCase
{
    public function test_token_usage(): void
    {
        $usage = new TokenUsage(100, 50, 150);

        $this->assertEquals(100, $usage->inputTokens);
        $this->assertEquals(50, $usage->outputTokens);
        $this->assertEquals(150, $usage->totalTokens);
    }

    public function test_ai_response(): void
    {
        $usage = new TokenUsage(100, 50, 150);
        $raw = ['key' => 'value'];

        $response = new AiResponse(
            content: 'Hello',
            model: 'gpt-4o-mini',
            usage: $usage,
            driver: 'openai',
            raw: $raw,
        );

        $this->assertEquals('Hello', $response->content);
        $this->assertEquals('gpt-4o-mini', $response->model);
        $this->assertEquals($usage, $response->usage);
        $this->assertEquals('openai', $response->driver);
        $this->assertEquals($raw, $response->raw);
    }

    public function test_stream_chunk(): void
    {
        $chunk = new StreamChunk(content: 'Hello', done: false);

        $this->assertEquals('Hello', $chunk->content);
        $this->assertFalse($chunk->done);

        $chunkDone = new StreamChunk(content: '', done: true);

        $this->assertEquals('', $chunkDone->content);
        $this->assertTrue($chunkDone->done);
    }
}
