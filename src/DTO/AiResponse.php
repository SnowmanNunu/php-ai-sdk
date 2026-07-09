<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai\DTO;

class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly TokenUsage $usage,
        public readonly string $driver,
        public readonly array $raw,
    ) {}
}
