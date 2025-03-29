<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use Clue\React\Redis\RedisClient;
use ReactphpX\Queue\Queue;

$loop = Loop::get();
$redis = new RedisClient('redis://127.0.0.1:6379');
$queue = new Queue($redis, 'example');

// 入队操作
$queue->enqueue(['task' => 'task1', 'data' => 'some data'])->then(function () {
    echo "Task1 入队成功\n";
});

$queue->enqueue(['task' => 'task2', 'data' => 'other data'])->then(function () {
    echo "Task2 入队成功\n";
});

// 获取队列大小
$queue->size()->then(function ($size) {
    echo "当前队列大小: {$size}\n";
});

// 普通出队操作
$queue->dequeue()->then(function ($data) {
    echo "出队数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
});

// 阻塞出队操作（等待5秒）
$queue->blockingDequeue(5)->then(function ($data) {
    if ($data) {
        echo "阻塞出队数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "阻塞出队超时，没有数据\n";
    }
});

// // 清空队列
// $queue->clear()->then(function () {
//     echo "队列已清空\n";
// });

$loop->run();