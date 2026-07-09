# PHP AI SDK 开发需求文档

> 版本：v1.0 · 状态：草稿 · 作者：SnowmanNunu · 日期：2026-06
> 仓库：https://github.com/SnowmanNunu/php-ai

---

## 1. 项目概述

### 1.1 背景与目标

PHP 生态缺少一个轻量、多驱动、标准化输出的 AI 接入 SDK。现有方案要么强绑单一模型，要么依赖臃肿的官方 SDK，缺少像 weather 和 mask-cn 那种「开箱即用 + DTO 标准化」的设计风格。

本项目目标：提供一个纯 PHP（Laravel 优先）的统一 AI 接入 SDK，屏蔽多模型 API 差异，输出结构一致的 DTO 响应，一行代码即可切换驱动。

### 1.2 项目信息

| 字段 | 内容 |
|------|------|
| 项目名称 | php-ai（Packagist: snowman/ai） |
| 命名空间 | `SnowmanNunu\Ai` |
| 目标 PHP 版本 | PHP 8.1+ |
| 主要依赖 | guzzlehttp/guzzle（HTTP）、Laravel 10/11（可选） |
| 开源协议 | MIT |
| 仓库地址 | https://github.com/SnowmanNunu/php-ai |

### 1.3 MVP 范围（第一期）

> **原则**：MVP 只做「接入 + 标准化输出」，不做 Agent、Tool Use、多模态。让 PHP 开发者用 5 行代码跑起来是唯一验收标准。

**MVP 包含：**

- 统一调用接口（`chat` / `stream`）
- 三个官方驱动：Claude（Anthropic）、OpenAI/GPT、DeepSeek
- 标准化 DTO 响应：`AiResponse`、`StreamChunk`、`TokenUsage`
- Laravel `ServiceProvider` + `Facade` + `config/ai.php`
- 完整的 README 快速上手文档

**MVP 明确不包含：**

- Tool Use / Function Calling
- 多模态（图片/文件输入）
- Embedding 接口
- Agent 框架
- 通义 / 文心驱动（二期）

---

## 2. 架构设计

### 2.1 整体分层

| 层级 | 类 / 接口 | 职责 |
|------|-----------|------|
| Laravel 集成层 | `AiServiceProvider`, `Facade`, `config/ai.php` | 注册驱动、读取 .env、提供 Facade |
| 管理层 | `AiManager` | 驱动注册表、动态切换、`extend()` 扩展 |
| 接口层 | `AiDriverInterface` | 定义 `chat()` / `stream()` 契约 |
| 驱动层 | `ClaudeDriver`, `OpenAiDriver`, `DeepSeekDriver` | 各模型 API 适配，HTTP 请求封装 |
| DTO 层 | `AiResponse`, `StreamChunk`, `TokenUsage` | 标准化输出，屏蔽 API 差异 |
| 中间件层 | Cache, Log（可选） | 请求缓存、用量日志（插件式，二期） |

### 2.2 目录结构

```
php-ai/
├── src/
│   ├── AiManager.php
│   ├── Contracts/
│   │   └── AiDriverInterface.php
│   ├── Drivers/
│   │   ├── ClaudeDriver.php
│   │   ├── OpenAiDriver.php
│   │   └── DeepSeekDriver.php
│   ├── DTO/
│   │   ├── AiResponse.php
│   │   ├── StreamChunk.php
│   │   └── TokenUsage.php
│   ├── Exceptions/
│   │   ├── AiException.php
│   │   ├── AiAuthException.php
│   │   ├── AiRateLimitException.php
│   │   ├── AiTimeoutException.php
│   │   ├── AiServerException.php
│   │   └── AiDriverNotFoundException.php
│   └── Laravel/
│       ├── AiServiceProvider.php
│       └── Facades/Ai.php
├── config/
│   └── ai.php
├── tests/
│   ├── Unit/
│   └── Feature/
├── composer.json
└── README.md
```

### 2.3 核心接口定义

**AiDriverInterface**

```php
interface AiDriverInterface
{
    public function chat(array $messages, array $options = []): AiResponse;

    /**
     * @return Generator<StreamChunk>
     */
    public function stream(array $messages, array $options = []): Generator;
}
```

**AiResponse DTO**

```php
class AiResponse
{
    public function __construct(
        public readonly string     $content,    // 模型输出文本
        public readonly string     $model,      // 实际使用的模型名
        public readonly TokenUsage $usage,      // token 用量
        public readonly string     $driver,     // 驱动标识
        public readonly array      $raw,        // 原始 API 响应（调试用）
    ) {}
}
```

**TokenUsage DTO**

```php
class TokenUsage
{
    public function __construct(
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $totalTokens,
    ) {}
}
```

**StreamChunk DTO**

```php
class StreamChunk
{
    public function __construct(
        public readonly string $content,   // 本次增量文本
        public readonly bool   $done,      // 是否结束
    ) {}
}
```

---

## 3. 驱动规格

### 3.1 Claude Driver（Anthropic）

| 参数 | 值 |
|------|----|
| API Base URL | `https://api.anthropic.com/v1` |
| 认证方式 | Header: `x-api-key: {key}` |
| 必需 Header | `anthropic-version: 2023-06-01` |
| 模型默认值 | `claude-sonnet-4-6` |
| 最大 Token 默认 | `1024` |
| 流式协议 | SSE（`text/event-stream`） |

**注意事项：**

- `messages` 格式为标准 `role/content` 数组，`system` 消息需单独提取为顶层 `system` 字段，不能混入 messages 数组
- stream 解析：逐行读取 `data: {...}` 事件，`type=content_block_delta` 时提取 `delta.text`

### 3.2 OpenAI Driver

| 参数 | 值 |
|------|----|
| API Base URL | `https://api.openai.com/v1`（支持自定义，兼容第三方中转） |
| 认证方式 | Header: `Authorization: Bearer {key}` |
| 模型默认值 | `gpt-4o-mini` |
| 最大 Token 默认 | `1024` |
| 流式协议 | SSE，`data: [DONE]` 结束 |
| 特殊处理 | `base_url` 可配置，用于国内中转或 Azure |

### 3.3 DeepSeek Driver

| 参数 | 值 |
|------|----|
| API Base URL | `https://api.deepseek.com/v1` |
| 认证方式 | Header: `Authorization: Bearer {key}` |
| 模型默认值 | `deepseek-chat` |
| 最大 Token 默认 | `1024` |
| 流式协议 | OpenAI 兼容格式 |

> **设计建议**：DeepSeek 与 OpenAI 接口完全兼容，`DeepSeekDriver extends OpenAiDriver`，只重写 `defaultBaseUrl()` 和 `defaultModel()`，减少重复代码。未来接入通义、文心等兼容 OpenAI 格式的模型时，同样策略复用。

---

## 4. 配置与使用

### 4.1 安装

```bash
composer require snowman/ai
```

### 4.2 config/ai.php

```php
return [
    'default' => env('AI_DRIVER', 'claude'),

    'drivers' => [
        'claude' => [
            'api_key'    => env('CLAUDE_API_KEY'),
            'model'      => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
            'max_tokens' => 1024,
        ],
        'openai' => [
            'api_key'    => env('OPENAI_API_KEY'),
            'model'      => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url'   => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'max_tokens' => 1024,
        ],
        'deepseek' => [
            'api_key'    => env('DEEPSEEK_API_KEY'),
            'model'      => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'max_tokens' => 1024,
        ],
    ],
];
```

### 4.3 .env 示例

```dotenv
AI_DRIVER=claude

CLAUDE_API_KEY=sk-ant-xxxxxxxx
OPENAI_API_KEY=sk-xxxxxxxx
DEEPSEEK_API_KEY=sk-xxxxxxxx
```

### 4.4 使用示例

**普通对话（Laravel Facade）**

```php
use SnowmanNunu\Ai\Laravel\Facades\Ai;

$response = Ai::chat([
    ['role' => 'user', 'content' => '用 PHP 写一个冒泡排序'],
]);

echo $response->content;             // 模型回复文本
echo $response->usage->totalTokens; // 总 token 用量
echo $response->model;               // 实际模型名
```

**切换驱动**

```php
$response = Ai::driver('openai')->chat([...]);
$response = Ai::driver('deepseek')->chat([...]);
```

**流式输出**

```php
foreach (Ai::stream([['role' => 'user', 'content' => '写一首诗']]) as $chunk) {
    echo $chunk->content;
    ob_flush();
    flush();
}
```

**纯 PHP（非 Laravel）**

```php
use SnowmanNunu\Ai\AiManager;

$manager = new AiManager([
    'default' => 'claude',
    'drivers' => [
        'claude' => ['api_key' => 'sk-ant-xxxxxxxx'],
    ],
]);

$response = $manager->chat([['role' => 'user', 'content' => 'Hello']]);
```

**自定义驱动扩展**

```php
Ai::extend('my-model', function (array $config) {
    return new MyCustomDriver($config);
});

Ai::driver('my-model')->chat([...]);
```

---

## 5. 异常处理

### 5.1 异常层级

| 异常类 | 触发条件 | 建议处理 |
|--------|----------|----------|
| `AiException` | 基类，所有 AI 相关异常的父类 | catch 兜底 |
| `AiAuthException` | API Key 无效或缺失（HTTP 401） | 提示配置问题 |
| `AiRateLimitException` | 请求频率超限（HTTP 429） | 指数退避重试 |
| `AiTimeoutException` | Guzzle 连接或读取超时 | 重试 / 降级 |
| `AiServerException` | 模型端 5xx 错误 | 重试 / 告警 |
| `AiDriverNotFoundException` | 指定驱动未注册 | 检查配置 |

### 5.2 规范

- 所有异常必须继承 `AiException`
- `AiException` 必须携带原始 HTTP 响应体（`$response` 属性），便于调试
- HTTP 状态码必须映射到对应异常类，不允许直接抛出 `\Exception`
- stream 模式下，连接中断须抛出 `AiTimeoutException`，不静默失败

### 5.3 使用示例

```php
use SnowmanNunu\Ai\Exceptions\AiRateLimitException;
use SnowmanNunu\Ai\Exceptions\AiException;

try {
    $response = Ai::chat([...]);
} catch (AiRateLimitException $e) {
    // 限流：等待后重试
    sleep(5);
} catch (AiException $e) {
    // 其他 AI 异常
    logger()->error('AI error', ['message' => $e->getMessage()]);
}
```

---

## 6. Laravel 集成

### 6.1 AiServiceProvider

- 注册 `AiManager` 为单例绑定：`app->singleton('ai', ...)`
- 自动合并配置文件：`mergeConfigFrom(__DIR__.'/../../config/ai.php', 'ai')`
- 在 `boot()` 中注册三个内置驱动（claude / openai / deepseek）
- 支持 `extend()` 方法让用户注册自定义驱动

### 6.2 Facade

- Facade accessor 返回 `'ai'`
- 所有 `AiManager` 公开方法均可通过 `Ai::` 调用
- 提供 IDE Helper 友好的 `@method` 注释，方便自动补全

### 6.3 自动发现配置

```json
"extra": {
    "laravel": {
        "providers": ["SnowmanNunu\\Ai\\Laravel\\AiServiceProvider"]
    }
}
```

Laravel 5.5+ 自动发现，无需手动在 `config/app.php` 注册。

---

## 7. 测试要求

### 7.1 测试矩阵

| 测试类型 | 覆盖范围 | 工具 |
|----------|----------|------|
| 单元测试 | `AiResponse` / `TokenUsage` / `StreamChunk` DTO 构造与属性 | PHPUnit |
| 单元测试 | `AiManager` 驱动注册与切换逻辑 | PHPUnit |
| 集成测试（Mock） | `ClaudeDriver::chat()` 正常响应解析 | PHPUnit + Guzzle MockHandler |
| 集成测试（Mock） | `ClaudeDriver::stream()` SSE 流式解析 | PHPUnit + Guzzle MockHandler |
| 集成测试（Mock） | `OpenAiDriver::chat()` / `stream()` | PHPUnit + Guzzle MockHandler |
| 集成测试（Mock） | `DeepSeekDriver::chat()` | PHPUnit + Guzzle MockHandler |
| 异常测试 | HTTP 401 / 429 / 500 映射到正确异常类 | PHPUnit |
| Laravel 测试 | ServiceProvider 注册 / Facade 调用 | Orchestra Testbench |

### 7.2 测试命令

```bash
composer test            # 运行全部测试
composer test:unit       # 仅单元测试
composer test:coverage   # 生成覆盖率报告（需 Xdebug）
```

### 7.3 composer.json scripts 配置

```json
"scripts": {
    "test": "phpunit",
    "test:unit": "phpunit --testsuite=Unit",
    "test:coverage": "phpunit --coverage-html coverage/",
    "lint": "pint"
}
```

---

## 8. 开发计划

### 8.1 里程碑（v1.0.0）

| 阶段 | 目标 | 预计周期 |
|------|------|----------|
| Phase 0 | 项目初始化：目录结构 / `composer.json` / GitHub Actions CI | 0.5 天 |
| Phase 1 | 核心骨架：`AiManager` + `AiDriverInterface` + DTO 三件套 | 1 天 |
| Phase 2 | Claude Driver：`chat` + `stream` + 异常映射 | 1 天 |
| Phase 3 | OpenAI Driver，DeepSeek Driver 继承复用 | 1 天 |
| Phase 4 | Laravel 集成：`ServiceProvider` + `Facade` + `config` | 0.5 天 |
| Phase 5 | 测试补全 + README 快速上手文档 | 1 天 |
| Phase 6 | 发布 Packagist v1.0.0 + GitHub Release | 0.5 天 |

**合计预估：约 5.5 天**

### 8.2 二期规划（v1.1+）

- Tool Use / Function Calling 支持（Claude + OpenAI）
- 通义千问驱动（阿里云 DashScope）
- 文心一言驱动（百度 AI Studio）
- 请求 Cache 中间件（TTL 可配置）
- 用量日志中间件（DB / File）
- Embedding 接口（`embed()` 方法）

---

## 9. 质量要求

### 9.1 代码规范

- 遵循 PSR-12，使用 Laravel Pint 强制格式化
- 所有公开方法必须有 PHPDoc 注释（`@param`、`@return`、`@throws`）
- 所有文件顶部 `declare(strict_types=1)`
- 不允许魔术方法隐式传参，接口契约优先

### 9.2 依赖原则

- 核心包只依赖 `guzzlehttp/guzzle`
- 不引入 `openai-php/client` 等官方 SDK，避免重复依赖
- Laravel 相关依赖放入 `require-dev` 或通过 `suggest` 声明
- PHP 最低版本锁定 8.1

### 9.3 GitHub 工作流

- 分支规范：`main` / `feature/xxx` / `fix/xxx`
- 提交信息遵循 Conventional Commits（`feat:` / `fix:` / `docs:` 等）
- GitHub Actions 自动运行 PHPUnit + Pint，PR 必须通过 CI
- 提供 Issue 模板（Bug Report / Feature Request）

---

## 10. README 文档结构

README 参考 `mask-cn` 和 `weather` 的风格，按以下顺序组织：

1. 项目标题 + 一句话介绍 + Badge（版本 / PHP / License / CI）
2. Features 亮点列表（3-5 条，突出多驱动 + 标准化 DTO + Laravel 友好）
3. 快速上手（安装 → 配置 → 5 行代码跑起来）
4. 驱动配置说明（Claude / OpenAI / DeepSeek 各自的 .env 示例）
5. API 参考（`chat()` / `stream()` 参数 + `AiResponse` DTO 字段表）
6. 切换驱动 & 自定义驱动扩展示例
7. 异常处理示例
8. 测试运行方式
9. Contributing 指引
10. License

---

*PHP AI SDK · 开发需求文档 v1.0 · SnowmanNunu*
