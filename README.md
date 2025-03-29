# ReactPHP Queue

基于 Redis 的异步队列实现，使用 ReactPHP 构建。

## 特性

- 基于 Redis 实现的轻量级队列
- 支持异步操作
- 支持多队列
- 支持阻塞式出队
- 支持队列前缀配置

## 安装

```bash
composer require reactphp-x/queue
```

## 基本用法

### 初始化队列

```php
use Clue\React\Redis\RedisClient;
use ReactphpX\Queue\Queue;

$redis = new RedisClient('redis://localhost:6379');
$queue = new Queue($redis, 'myapp'); // 第二个参数为队列前缀，可选
```

### 入队操作

```php
// 向默认队列添加数据
$queue->enqueue(['job' => 'task1', 'data' => 'some data']);

// 向指定队列添加数据
$queue->enqueue(['job' => 'task2', 'data' => 'other data'], 'high-priority');
```

### 出队操作

```php
// 从默认队列中取出数据（非阻塞）
$queue->dequeue()->then(function ($data) {
    if ($data === null) {
        echo "队列为空\n";
        return;
    }
    echo "获取到数据：" . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
});

// 从指定队列中取出数据（非阻塞）
$queue->dequeue('high-priority')->then(function ($data) {
    // 处理数据
});
```

### 阻塞式出队

```php
// 阻塞等待数据（默认永久等待）
$queue->blockingDequeue()->then(function ($data) {
    // 处理数据
});

// 设置超时时间（单位：秒）
$queue->blockingDequeue(5)->then(function ($data) {
    if ($data === null) {
        echo "等待超时\n";
        return;
    }
    // 处理数据
});
```

### 队列管理

```php
// 获取队列长度
$queue->size()->then(function ($length) {
    echo "队列长度：$length\n";
});

// 清空队列
$queue->clear()->then(function () {
    echo "队列已清空\n";
});
```

## 注意事项

1. 所有操作都返回 Promise 对象，需要使用 then() 方法处理结果
2. 队列中的数据会自动进行 JSON 编码和解码
3. 如果设置了队列前缀，实际的 Redis 键名将为 `前缀:队列名`
4. 阻塞式出队在队列为空时会等待新数据到达，可以通过设置超时时间来避免永久等待