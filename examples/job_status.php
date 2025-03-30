<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use ReactphpX\Queue\Queue;
use ReactphpX\Queue\Consumer;
use ReactphpX\Queue\JobManager;
use ReactphpX\SerializableClosure\SerializableClosure;
use function React\Async\async;

// 创建Redis客户端
use Clue\React\Redis\RedisClient;

$redis = new RedisClient('redis://127.0.0.1:6379');

// 创建队列和任务管理器
$queue = new Queue($redis);
$consumer = new Consumer($queue);
$jobManager = new JobManager($redis, $queue);
$jobManager->initProcess(1,1);
$jobManager->clearJobs();
// 注册消费者
$consumer->consume(function ($data) use ($jobManager) {
    echo "Received job: $data\n";
    return $jobManager->processJob($data);
});

// // 示例：推送一些任务到队列
for ($i = 1; $i <= 3; $i++) {
    $jobId = "job-$i";
    $jobManager->pushJob($jobId, function () use ($i) {
        if ($i === 2) {
            throw new \Exception("Job $i failed");
        }
        return "Job $i completed successfully";
    }, 'default')->then(function ($result) use ($jobId) {
        var_dump($result); // 输出任务结果
    });
}

// 定时查询任务状态
// 修改状态输出部分
$timer = Loop::addPeriodicTimer(2, function () use ($jobManager) {
    echo "\nCurrent job statuses:\n";
    $jobManager->getAllJobs()->then(function ($jobs) {
        foreach ($jobs as $jobId => $job) {
            echo "Job ID: $jobId\n";
            foreach ($job as $key => $value) {
                echo "  $key: $value\n";
            }
            echo "\n";
        }
    });
});

// 5秒后停止状态查询
Loop::addTimer(5, function () use ($timer) {
    // Loop::cancelTimer($timer);
    echo "\nDemo completed\n";
});

Loop::run();