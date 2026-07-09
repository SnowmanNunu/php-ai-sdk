<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai\Drivers;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use SnowmanNunu\Ai\Contracts\AiDriverInterface;
use SnowmanNunu\Ai\DTO\AiResponse;
use SnowmanNunu\Ai\DTO\StreamChunk;
use SnowmanNunu\Ai\DTO\TokenUsage;

class ClaudeDriver implements AiDriverInterface
{
    use HandlesGuzzleExceptions;
    protected Client $client;

    protected array $config;

    protected const DEFAULT_BASE_URL = 'https://api.anthropic.com/v1';

    protected const DEFAULT_MODEL = 'claude-sonnet-4-6';

    protected const DEFAULT_MAX_TOKENS = 1024;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->client = new Client([
            'base_uri' => $config['base_url'] ?? self::DEFAULT_BASE_URL,
            'timeout' => $config['timeout'] ?? 60,
            'headers' => $this->getDefaultHeaders(),
        ]);
    }

    protected function getDefaultHeaders(): array
    {
        return [
            'x-api-key' => $this->config['api_key'] ?? '',
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ];
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $body = $this->buildRequestBody($messages, $options);

        try {
            $response = $this->client->post('messages', [
                'json' => $body,
            ]);

            return $this->parseChatResponse($response);
        } catch (GuzzleException $e) {
            throw $this->handleGuzzleException($e);
        }
    }

    public function stream(array $messages, array $options = []): Generator
    {
        $body = $this->buildRequestBody($messages, $options);
        $body['stream'] = true;

        try {
            $response = $this->client->post('messages', [
                'json' => $body,
                'stream' => true,
                'headers' => [
                    'Accept' => 'text/event-stream',
                ],
            ]);

            return $this->parseStreamResponse($response);
        } catch (GuzzleException $e) {
            throw $this->handleGuzzleException($e);
        }
    }

    protected function buildRequestBody(array $messages, array $options = []): array
    {
        $system = '';
        $cleanMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system = $message['content'];
            } else {
                $cleanMessages[] = $message;
            }
        }

        $body = [
            'model' => $options['model'] ?? $this->config['model'] ?? self::DEFAULT_MODEL,
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'messages' => $cleanMessages,
        ];

        if (! empty($system)) {
            $body['system'] = $system;
        }

        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }

        if (isset($options['top_p'])) {
            $body['top_p'] = $options['top_p'];
        }

        return $body;
    }

    protected function parseChatResponse(ResponseInterface $response): AiResponse
    {
        $raw = json_decode($response->getBody()->getContents(), true);

        $content = '';
        if (isset($raw['content'][0]['text'])) {
            $content = $raw['content'][0]['text'];
        }

        $usage = $this->parseTokenUsage($raw);

        return new AiResponse(
            content: $content,
            model: $raw['model'] ?? '',
            usage: $usage,
            driver: 'claude',
            raw: $raw,
        );
    }

    protected function parseStreamResponse(ResponseInterface $response): Generator
    {
        $body = $response->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                if (strpos($line, 'data: ') === 0) {
                    $data = substr($line, 6);

                    if ($data === '[DONE]') {
                        yield new StreamChunk(content: '', done: true);

                        return;
                    }

                    $event = json_decode($data, true);

                    if ($event['type'] === 'content_block_delta') {
                        $delta = $event['delta']['text'] ?? '';
                        yield new StreamChunk(content: $delta, done: false);
                    } elseif ($event['type'] === 'message_stop') {
                        yield new StreamChunk(content: '', done: true);

                        return;
                    }
                }
            }
        }

        yield new StreamChunk(content: '', done: true);
    }

    protected function parseTokenUsage(array $raw): TokenUsage
    {
        $usage = $raw['usage'] ?? [];

        return new TokenUsage(
            inputTokens: $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
            totalTokens: ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
        );
    }
}
