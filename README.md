# PHP AI SDK

> 轻量、多驱动、标准化输出的 PHP AI 接入 SDK

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## Features

- **多驱动支持**：Claude、OpenAI、DeepSeek，一行代码切换
- **标准化 DTO**：统一响应结构，屏蔽各 API 差异
- **Laravel 友好**：ServiceProvider + Facade + 配置文件，开箱即用
- **流式输出**：原生 Generator 支持，实时获取响应
- **异常映射**：HTTP 状态码智能映射到具体异常类

## 快速上手

### 安装

```bash
composer require snowman/ai
```

### Laravel 配置

```bash
# 发布配置文件
php artisan vendor:publish --tag=ai-config
```

### .env 配置

```dotenv
AI_DRIVER=claude

CLAUDE_API_KEY=sk-ant-xxxxxxxx
OPENAI_API_KEY=sk-xxxxxxxx
DEEPSEEK_API_KEY=sk-xxxxxxxx
```

### 使用

```php
use SnowmanNunu\Ai\Laravel\Facades\Ai;

$response = Ai::chat([
    ['role' => 'user', 'content' => '用 PHP 写一个冒泡排序'],
]);

echo $response->content;             // 模型回复文本
echo $response->usage->totalTokens; // 总 token 用量
echo $response->model;               // 实际模型名
```

## 驱动配置

### Claude

```dotenv
AI_DRIVER=claude
CLAUDE_API_KEY=sk-ant-xxxxxxxx
CLAUDE_MODEL=claude-sonnet-4-6
```

### OpenAI

```dotenv
AI_DRIVER=openai
OPENAI_API_KEY=sk-xxxxxxxx
OPENAI_MODEL=gpt-4o-mini
OPENAI_BASE_URL=https://api.openai.com/v1
```

### DeepSeek

```dotenv
AI_DRIVER=deepseek
DEEPSEEK_API_KEY=sk-xxxxxxxx
DEEPSEEK_MODEL=deepseek-chat
```

### 智谱 AI (GLM)

```dotenv
AI_DRIVER=zhipu
ZHIPU_API_KEY=xxxxxxxx
ZHIPU_MODEL=glm-4-flash
ZHIPU_BASE_URL=https://open.bigmodel.cn/api/paas/v4
```

### 通义千问 (Qwen)

```dotenv
AI_DRIVER=qwen
QWEN_API_KEY=xxxxxxxx
QWEN_MODEL=qwen-turbo
QWEN_BASE_URL=https://dashscope.aliyuncs.com/compatible-mode/v1
```

### 文心一言 (Wenxin)

```dotenv
AI_DRIVER=wenxin
WENXIN_API_KEY=xxxxxxxx
WENXIN_MODEL=ernie-4.0-8k-latest
WENXIN_BASE_URL=https://qianfan.baidubce.com/v2
```

### 月之暗面 (Moonshot)

```dotenv
AI_DRIVER=moonshot
MOONSHOT_API_KEY=xxxxxxxx
MOONSHOT_MODEL=moonshot-v1-8k
MOONSHOT_BASE_URL=https://api.moonshot.cn/v1
```

### MiniMax

```dotenv
AI_DRIVER=minimax
MINIMAX_API_KEY=xxxxxxxx
MINIMAX_MODEL=abab6.5s-chat
MINIMAX_BASE_URL=https://api.minimax.chat/v1
```

### Kimi for Coding

```dotenv
AI_DRIVER=kimi
KIMI_API_KEY=xxxxxxxx
KIMI_MODEL=kimi-coding
KIMI_BASE_URL=https://api.kimi.com/coding
```

### Xiaomi MiMo

```dotenv
AI_DRIVER=xiaomi-mimo
XIAOMI_MIMO_API_KEY=xxxxxxxx
XIAOMI_MIMO_MODEL=mimo-v2.5-pro
XIAOMI_MIMO_BASE_URL=https://token-plan-cn.xiaomimimo.com/v1
```

## API 参考

### chat()

```php
Ai::chat(array $messages, array $options = []): AiResponse
```

**参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `messages` | `array` | 消息数组，格式：`[['role' => 'user|assistant|system', 'content' => '...']]` |
| `options.model` | `string` | 模型名称 |
| `options.max_tokens` | `int` | 最大输出 token 数 |
| `options.temperature` | `float` | 温度参数 |

**返回 `AiResponse`：**

| 字段 | 类型 | 说明 |
|------|------|------|
| `content` | `string` | 模型输出文本 |
| `model` | `string` | 实际使用的模型名 |
| `usage` | `TokenUsage` | token 用量 |
| `driver` | `string` | 驱动标识 |
| `raw` | `array` | 原始 API 响应 |

### stream()

```php
Ai::stream(array $messages, array $options = []): Generator<StreamChunk>
```

**返回 `StreamChunk`：**

| 字段 | 类型 | 说明 |
|------|------|------|
| `content` | `string` | 本次增量文本 |
| `done` | `bool` | 是否结束 |

## 切换驱动

```php
$response = Ai::driver('openai')->chat([...]);
$response = Ai::driver('deepseek')->chat([...]);
```

## 自定义驱动

```php
Ai::extend('my-model', function (array $config) {
    return new MyCustomDriver($config);
});

Ai::driver('my-model')->chat([...]);
```

## 异常处理

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

### 异常类型

| 异常类 | 触发条件 |
|--------|----------|
| `AiAuthException` | API Key 无效或缺失（HTTP 401） |
| `AiRateLimitException` | 请求频率超限（HTTP 429） |
| `AiTimeoutException` | 连接或读取超时 |
| `AiServerException` | 模型端 5xx 错误 |
| `AiDriverNotFoundException` | 指定驱动未注册 |

## 纯 PHP 使用

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

## 测试

```bash
composer test            # 运行全部测试
composer test:unit       # 仅单元测试
composer test:coverage   # 生成覆盖率报告
composer lint            # 代码格式化检查
```

## Contributing

欢迎提交 Issue 和 PR！

## License

MIT
