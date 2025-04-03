# ReactPHP Queue

基于 ReactPHP 和 Redis 实现的异步队列系统，提供轻量级、高性能的消息队列解决方案。

## 特性

- 基于 Redis 实现的轻量级队列
- 支持异步操作和非阻塞式处理
- 支持多队列和优先级队列
- 提供阻塞式出队操作
- 内置任务状态管理
- 支持多工作进程处理
- 支持内存队列（ArrayQueue）
- 支持队列前缀配置

## 安装

```bash
composer require reactphp-x/queue
```

## 基础使用

### 创建队列实例

```php
use React\EventLoop\Loop;
use Clue\React\Redis\RedisClient;
use ReactphpX\Queue\Queue;

$loop = Loop::get();
$redis = new RedisClient('redis://127.0.0.1:6379');
$queue = new Queue($redis, 'example');
```

### 基本队列操作

```php
// 入队操作
$queue->enqueue(['task' => 'task1', 'data' => 'some data'])->then(function () {
    echo "Task1 入队成功\n";
});

// 获取队列大小
$queue->size()->then(function ($size) {
    echo "当前队列大小: {$size}\n";
});

// 出队操作
$queue->dequeue()->then(function ($data) {
    echo "出队数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
});

// 阻塞式出队（等待5秒）
$queue->blockingDequeue(5)->then(function ($data) {
    if ($data) {
        echo "获取到数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "等待超时，没有数据\n";
    }
});
```

## 高级特性

### 多队列支持

支持创建多个优先级队列，实现任务优先级处理：

```php
// 向不同优先级的队列添加任务
$queue->enqueue($task, 'high');
$queue->enqueue($task, 'medium');
$queue->enqueue($task, 'low');

// 按优先级处理任务
$queue->dequeue('high');
$queue->dequeue('medium');
$queue->dequeue('low');
```

### 任务状态管理

使用 JobManager 管理任务状态和执行：

```php
use ReactphpX\Queue\JobManager;
use ReactphpX\Queue\Storage\RedisStorageDriver;

$storage = new RedisStorageDriver($redis);
$jobManager = new JobManager($storage, $queue);

// 推送带状态跟踪的任务
$jobManager->pushJob('job-1', function () {
    return "Job completed";
}, 'default', true);

// 获取任务状态
$jobManager->getAllJobs()->then(function ($jobs) {
    foreach ($jobs as $jobId => $job) {
        echo "Job ID: $jobId, Status: {$job['status']}\n";
    }
});
```

### 工作进程

支持多工作进程并行处理任务：

```php
use ReactphpX\Queue\Consumer;

$consumer = new Consumer($queue);

// 注册任务处理器
$consumer->consume(function ($data) {
    echo "Processing task: " . json_encode($data) . "\n";
    // 处理任务逻辑
    return true;
});
```

### 内存队列

提供基于内存的队列实现，适用于单进程场景：

```php
use ReactphpX\Queue\ArrayQueue;

$queue = new ArrayQueue();

$queue->enqueue('task1')->then(function () {
    echo "Task added\n";
});

$queue->dequeue()->then(function ($data) {
    echo "Processing: $data\n";
});
```

## 许可证

MIT License