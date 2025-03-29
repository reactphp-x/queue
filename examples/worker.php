<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use Clue\React\Redis\RedisClient;
use ReactphpX\Queue\Queue;

$loop = Loop::get();
$redis = new RedisClient('redis://127.0.0.1:6379');
$queue = new Queue($redis, 'worker');

// 模拟任务生产者
function produceTask($queue, $taskId) {
    return $queue->enqueue([
        'id' => $taskId,
        'type' => 'process',
        'data' => "处理任务 {$taskId}",
        'created_at' => time()
    ])->then(function () use ($taskId) {
        echo "已添加任务 {$taskId} 到队列\n";
    });
}

// 模拟任务消费者
function startWorker($queue, $workerId) {
    $processTask = function () use ($queue, $workerId, &$processTask) {
        $queue->blockingDequeue(2)->then(function ($task) use ($workerId, $processTask) {
            if ($task) {
                echo "工作者 {$workerId} 正在处理任务 {$task['id']}\n";
                // 模拟任务处理
                sleep(1);
                echo "工作者 {$workerId} 完成任务 {$task['id']}\n";
            }
            $processTask(); // 继续处理下一个任务
        });
    };
    $processTask();
}



// 生产一些任务
$taskCount = 5;
for ($i = 1; $i <= $taskCount; $i++) {
    produceTask($queue, $i);
}

// 启动两个工作者
startWorker($queue, 1);
startWorker($queue, 2);

// $loop->run();