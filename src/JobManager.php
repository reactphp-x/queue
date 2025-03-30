<?php

namespace ReactphpX\Queue;

use Clue\React\Redis\RedisClient;
use ReactphpX\SerializableClosure\SerializableClosure;

class JobManager {
    private $redis;
    private $queue;

    const STATUS_CREATED = 'created';
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function __construct(RedisClient $redis, Queue $queue) {
        $this->redis = $redis;
        $this->queue = $queue;
    }

    public function pushJob($jobId, $closure = null, $queueName = 'default') {
        if (!$closure instanceof \Closure) {
            throw new \InvalidArgumentException('The closure parameter must be a valid Closure object');
        }

        if (!$jobId) {
            // 生成唯一的任务ID
            $jobId = uniqid();
        }

        // 序列化闭包
        $serializedClosure = SerializableClosure::serialize($closure);
        
        // 存储任务状态
        return $this->redis->hMSet("job:$jobId", "status", self::STATUS_CREATED, "created_at", (string)time())
            ->then(function () use ($jobId, $serializedClosure, $queueName) {
                // 推送到队列，将数组序列化为JSON字符串
                return $this->queue->enqueue(json_encode([
                    'job_id' => $jobId,
                    'closure' => $serializedClosure
                ]), $queueName)->then(function () use ($jobId) {
                    // 返回一个新的Promise，该Promise会在任务完成或失败时resolve
                    return new \React\Promise\Promise(function ($resolve, $reject) use ($jobId) {
                        $checkStatus = function () use ($jobId, $resolve, &$checkStatus) {
                            $this->getJobStatus($jobId)->then(function ($status) use ($jobId, $resolve, &$checkStatus) {
                                if (isset($status['status'])) {
                                    if ($status['status'] === self::STATUS_COMPLETED) {
                                        $resolve(['status' => 'completed', 'job_id' => $jobId, 'result' => $status['result'] ?? null]);
                                    } elseif ($status['status'] === self::STATUS_FAILED) {
                                        $resolve(['status' => 'failed', 'job_id' => $jobId, 'error' => $status['error'] ?? null]);
                                    } else {
                                        // 如果任务还未完成，继续检查
                                        \React\Promise\Timer\sleep(0.5)->then($checkStatus);
                                    }
                                }
                            });
                        };
                        $checkStatus();
                    });
                });
            });
    }

    public function getJobStatus($jobId) {
        return $this->redis->hGetAll("job:$jobId")->then(function ($data) {
            // 将数组转换为关联数组
            $result = [];
            for ($i = 0; $i < count($data); $i += 2) {
                $result[$data[$i]] = $data[$i + 1];
            }
            return $result;
        });
    }

    public function getAllJobs($offset = 0, $limit = 10) {
        return $this->redis->keys('job:*')
            ->then(function ($keys) use ($offset, $limit) {
                // 对任务ID进行分页
                $keys = array_slice($keys, $offset, $limit);
                $promises = [];
                foreach ($keys as $key) {
                    $jobId = str_replace('job:', '', $key);
                    $promises[$jobId] = $this->getJobStatus($jobId);
                }
                return \React\Promise\all($promises);
            });
    }

    public function processJob($data) {
        // 反序列化数据
        $data = json_decode($data, true);
        // 解析JSON数据
        $jobId = $data['job_id'];
        $closure = SerializableClosure::unserialize($data['closure']);
        
        // 获取重试信息
        $retryInfo = isset($data['__retry_info']) ? $data['__retry_info'] : [
            'attempts' => 1,
            'first_attempt' => time(),
            'last_attempt' => time()
        ];

        // 更新任务状态为处理中
        return $this->redis->hMSet("job:$jobId", 
            "status", self::STATUS_PENDING, 
            "started_at", (string)time(),
            "attempts", (string)$retryInfo['attempts'],
            "first_attempt", (string)$retryInfo['first_attempt'],
            "last_attempt", (string)$retryInfo['last_attempt']
        )
            ->then(function () use ($closure, $jobId) {
                try {
                    // 执行任务
                    return \React\Promise\resolve($closure())->then(function ($result) use ($jobId) {
                        // 如果结果是数组，转换为JSON字符串
                        $resultStr = is_array($result) ? json_encode($result) : (string)$result;
                        // 更新任务状态为完成，并存储结果
                        return $this->redis->hMSet("job:$jobId", 
                            "status", self::STATUS_COMPLETED, 
                            "completed_at", (string)time(),
                            "result", $resultStr
                        );
                    }, function ($error) use ($jobId){
                        // 更新任务状态为失败
                        return $this->redis->hMSet("job:$jobId", 
                            "status", self::STATUS_FAILED, 
                            "failed_at", (string)time(), 
                            "error", $error->getMessage()
                        )->then(function () use ($error) {
                            return \React\Promise\reject($error);
                        });
                    });
                } catch (\Exception $e) {
                    // 更新任务状态为失败
                    return $this->redis->hMSet("job:$jobId", 
                        "status", self::STATUS_FAILED, 
                        "failed_at", (string)time(), 
                        "error", $e->getMessage()
                    )->then(function () use ($e) {
                        return \React\Promise\reject($e);
                    });
                }
            });
    }
}