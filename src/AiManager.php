<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai;

use Closure;
use SnowmanNunu\Ai\Contracts\AiDriverInterface;
use SnowmanNunu\Ai\DTO\AiResponse;
use SnowmanNunu\Ai\Exceptions\AiDriverNotFoundException;

class AiManager
{
    protected array $config;

    protected array $drivers = [];

    protected array $customCreators = [];

    protected ?string $defaultDriver = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->defaultDriver = $config['default'] ?? 'claude';
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        return $this->driver()->chat($messages, $options);
    }

    public function stream(array $messages, array $options = []): \Generator
    {
        return $this->driver()->stream($messages, $options);
    }

    public function driver(?string $driver = null): AiDriverInterface
    {
        $driver = $driver ?: $this->defaultDriver;

        if (is_null($driver)) {
            throw new AiDriverNotFoundException('No default driver configured.');
        }

        if (! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    protected function createDriver(string $driver): AiDriverInterface
    {
        if (isset($this->customCreators[$driver])) {
            return $this->customCreators[$driver]($this->getDriverConfig($driver));
        }

        return match ($driver) {
            'claude' => $this->createClaudeDriver(),
            'openai' => $this->createOpenAiDriver(),
            'deepseek' => $this->createDeepSeekDriver(),
            'zhipu' => $this->createZhipuDriver(),
            'qwen' => $this->createQwenDriver(),
            'wenxin' => $this->createWenxinDriver(),
            'moonshot' => $this->createMoonshotDriver(),
            'minimax' => $this->createMiniMaxDriver(),
            'kimi' => $this->createKimiDriver(),
            'xiaomi-mimo' => $this->createXiaomiMimoDriver(),
            default => throw new AiDriverNotFoundException("Driver [{$driver}] not found."),
        };
    }

    protected function createClaudeDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('claude');

        return new \SnowmanNunu\Ai\Drivers\ClaudeDriver($config);
    }

    protected function createOpenAiDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('openai');

        return new \SnowmanNunu\Ai\Drivers\OpenAiDriver($config);
    }

    protected function createDeepSeekDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('deepseek');

        return new \SnowmanNunu\Ai\Drivers\DeepSeekDriver($config);
    }

    protected function createZhipuDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('zhipu');

        return new \SnowmanNunu\Ai\Drivers\ZhipuDriver($config);
    }

    protected function createQwenDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('qwen');

        return new \SnowmanNunu\Ai\Drivers\QwenDriver($config);
    }

    protected function createWenxinDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('wenxin');

        return new \SnowmanNunu\Ai\Drivers\WenxinDriver($config);
    }

    protected function createMoonshotDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('moonshot');

        return new \SnowmanNunu\Ai\Drivers\MoonshotDriver($config);
    }

    protected function createMiniMaxDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('minimax');

        return new \SnowmanNunu\Ai\Drivers\MiniMaxDriver($config);
    }

    protected function createKimiDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('kimi');

        return new \SnowmanNunu\Ai\Drivers\KimiDriver($config);
    }

    protected function createXiaomiMimoDriver(): AiDriverInterface
    {
        $config = $this->getDriverConfig('xiaomi-mimo');

        return new \SnowmanNunu\Ai\Drivers\XiaomiMimoDriver($config);
    }

    protected function getDriverConfig(string $driver): array
    {
        return $this->config['drivers'][$driver] ?? [];
    }

    public function extend(string $driver, Closure $callback): void
    {
        $this->customCreators[$driver] = $callback;
    }

    public function getDefaultDriver(): ?string
    {
        return $this->defaultDriver;
    }

    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }
}
