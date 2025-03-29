<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use Clue\React\Redis\RedisClient;
use ReactphpX\Queue\Queue;

$loop = Loop::get();
$redis = new RedisClient('redis://127.0.0.1:6379');
$queue = new Queue($redis, 'multi');

// 模拟不同优先级的队列
$priorities = ['high', 'medium', 'low'];

// 向不同优先级的队列添加任务
foreach ($priorities as $priority) {
    for ($i = 1; $i <= 3; $i++) {
        $queue->enqueue(
            ['task' => "task{$i}", 'priority' => $priority],
            $priority
        )->then(function () use ($priority, $i) {
            echo "添加任务 {$i} 到 {$priority} 优先级队列\n";
        });
    }
}

// 检查每个队列的大小
foreach ($priorities as $priority) {
    $queue->size($priority)->then(function ($size) use ($priority) {
        echo "{$priority} 优先级队列大小: {$size}\n";
    });
}

// 按优先级处理任务
$processQueues = function () use ($queue, $priorities, &$processQueues, $loop) {
    // 从高优先级到低优先级依次处理
    foreach ($priorities as $priority) {
        $queue->dequeue($priority)->then(function ($data) use ($priority) {
            if ($data) {
                echo "处理 {$priority} 优先级任务: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
            }
        });
    }
    
    // 延迟一秒后继续处理
    $loop->addTimer(1, $processQueues);
};

// 开始处理队列
$loop->addTimer(2, $processQueues);
