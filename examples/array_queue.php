<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use ReactphpX\Queue\ArrayQueue;

$queue = new ArrayQueue();

// 添加任务到队列
$queue->enqueue('task1')->then(function () {
    echo "添加任务1到队列\n";
});

$queue->enqueue('task2')->then(function () {
    echo "添加任务2到队列\n";
});

// 检查队列大小
$queue->size()->then(function ($size) {
    echo "队列大小: {$size}\n";
});

// 从队列中获取任务
$queue->dequeue()->then(function ($data) {
    echo "处理任务: {$data}\n";
});

// 使用阻塞模式等待任务
$queue->blockingDequeue(5)->then(function ($data) {
    if ($data === null) {
        echo "等待超时，没有新任务\n";
    } else {
        echo "处理阻塞任务: {$data}\n";
    }
});

// 清空队列
$queue->clear()->then(function () {
    echo "队列已清空\n";
});

// 检查清空后的队列大小
$queue->size()->then(function ($size) {
    echo "清空后队列大小: {$size}\n";
});