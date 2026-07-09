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

class OpenAiDriver implements AiDriverInterface
{
    use HandlesGuzzleExceptions;
    protected Client $client;

    protected array $config;

    protected const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    protected const DEFAULT_MODEL = 'gpt-4o-mini';

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
            'Authorization' => 'Bearer ' . ($this->config['api_key'] ?? ''),
            'Content-Type' => 'application/json',
        ];
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $body = $this->buildRequestBody($messages, $options);

        try {
            $response = $this->client->post('chat/completions', [
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
            $response = $this->client->post('chat/completions', [
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
        $body = [
            'model' => $options['model'] ?? $this->config['model'] ?? self::DEFAULT_MODEL,
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'] ?? self::DEFAULT_MAX_TOKENS,
            'messages' => $messages,
        ];

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
        if (isset($raw['choices'][0]['message']['content'])) {
            $content = $raw['choices'][0]['message']['content'];
        }

        $usage = $this->parseTokenUsage($raw);

        return new AiResponse(
            content: $content,
            model: $raw['model'] ?? '',
            usage: $usage,
            driver: $this->getDriverName(),
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

                    if (isset($event['choices'][0]['delta']['content'])) {
                        $delta = $event['choices'][0]['delta']['content'] ?? '';
                        yield new StreamChunk(content: $delta, done: false);
                    } elseif (isset($event['choices'][0]['finish_reason'])) {
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
            inputTokens: $usage['prompt_tokens'] ?? 0,
            outputTokens: $usage['completion_tokens'] ?? 0,
            totalTokens: ($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0),
        );
    }

    protected function getDriverName(): string
    {
        return 'openai';
    }
}
