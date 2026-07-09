<?php

declare(strict_types=1);

namespace SnowmanNunu\Ai\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SnowmanNunu\Ai\AiManager;
use SnowmanNunu\Ai\Contracts\AiDriverInterface;
use SnowmanNunu\Ai\Exceptions\AiDriverNotFoundException;

class AiManagerTest extends TestCase
{
    public function testDefaultDriver(): void
    {
        $manager = new AiManager([
            'default' => 'claude',
            'drivers' => [
                'claude' => ['api_key' => 'test-key'],
            ],
        ]);

        $this->assertEquals('claude', $manager->getDefaultDriver());
    }

    public function testSetDefaultDriver(): void
    {
        $manager = new AiManager([
            'default' => 'claude',
            'drivers' => [
                'claude' => ['api_key' => 'test-key'],
                'openai' => ['api_key' => 'test-key'],
            ],
        ]);

        $manager->setDefaultDriver('openai');

        $this->assertEquals('openai', $manager->getDefaultDriver());
    }

    public function testDriverNotFound(): void
    {
        $manager = new AiManager([
            'default' => 'unknown',
            'drivers' => [],
        ]);

        $this->expectException(AiDriverNotFoundException::class);

        $manager->driver();
    }

    public function testCustomDriver(): void
    {
        $manager = new AiManager([
            'default' => 'custom',
            'drivers' => [
                'custom' => ['api_key' => 'test-key'],
            ],
        ]);

        $mockDriver = $this->createMock(AiDriverInterface::class);

        $manager->extend('custom', function () use ($mockDriver) {
            return $mockDriver;
        });

        $driver = $manager->driver('custom');

        $this->assertSame($mockDriver, $driver);
    }
}
