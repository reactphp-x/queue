<?php

namespace ReactphpX\Queue;

use ReactphpX\SerializableClosure\SerializableClosure;
use ReactphpX\ProcessManager\ProcessManager;
use React\Promise\Deferred;
use ReactphpX\Queue\Storage\StorageDriverInterface;

class JobManager {
    private $storage;
    private $queue;
    private $processManager;

    const STATUS_CREATED = 'created';
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function __construct(StorageDriverInterface $storage, Queue $queue) {
        $this->storage = $storage;
        $this->queue = $queue;
    }

    public function initProcess($min, $max)
    {
        $this->processManager = new ProcessManager(sprintf(
            'exec php %s/child_process_init.php',
            __DIR__
        ), $min, $max, PHP_INT_MAX, 0);
    }

    public function pushJob($jobId, $closure, $queueName = 'default', $useProcess = false) {


        if (!$closure instanceof \Closure) {
            throw new \InvalidArgumentException('The closure parameter must be a valid Closure object');
        }

        if ($useProcess) {
            if (!$this->processManager) {
                throw new \InvalidArgumentException('ProcessManager is not initialized');
            }
        }

        if (!$jobId) {
            // 生成唯一的任务ID
            $jobId = uniqid();
        }

        // 序列化闭包
        $serializedClosure = SerializableClosure::serialize($closure);
        
        // 存储任务状态
        return $this->storage->setJobStatus($jobId, [
                "status" => self::STATUS_CREATED,
                "created_at" => (string)time(),
                "use_process" => $useProcess ? "1" : "0"
            ])
            ->then(function () use ($jobId, $serializedClosure, $queueName, $useProcess) {
                // 推送到队列，将数组序列化为JSON字符串
                return $this->queue->enqueue(json_encode([
                    'job_id' => $jobId,
                    'closure' => $serializedClosure,
                    'use_process' => $useProcess
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
        return $this->storage->getJobStatus($jobId);
    }

    public function getAllJobs($offset = 0, $limit = 10) {
        return $this->storage->getAllJobs($offset, $limit);
    }

    public function processJob($data) {
        // 反序列化数据
        $data = json_decode($data, true);
        // 解析JSON数据
        $jobId = $data['job_id'];
        $closure = SerializableClosure::unserialize($data['closure']);
        $useProcess = $data['use_process'];
        // 获取重试信息
        $retryInfo = isset($data['__retry_info']) ? $data['__retry_info'] : [
            'attempts' => 1,
            'first_attempt' => time(),
            'last_attempt' => time()
        ];

        // 更新任务状态为处理中
        return $this->storage->setJobStatus($jobId, [
            "status" => self::STATUS_PENDING,
            "started_at" => (string)time(),
            "attempts" => (string)$retryInfo['attempts'],
            "first_attempt" => (string)$retryInfo['first_attempt'],
            "last_attempt" => (string)$retryInfo['last_attempt']
        ])
            ->then(function () use ($closure, $jobId, $useProcess) {
                try {
                    // 执行任务
                    return ($useProcess ? $this->runCallbackInProcess($closure) : \React\Promise\resolve($closure()))->then(function ($result) use ($jobId) {
                        // 如果结果是数组，转换为JSON字符串
                        $resultStr = is_array($result) ? json_encode($result) : (string)$result;
                        // 更新任务状态为完成，并存储结果
                        return $this->storage->setJobStatus($jobId, [
                            "status" => self::STATUS_COMPLETED,
                            "completed_at" => (string)time(),
                            "result" => $resultStr
                        ]);
                    }, function ($error) use ($jobId){
                        // 更新任务状态为失败
                        return $this->storage->setJobStatus($jobId, [
                            "status" => self::STATUS_FAILED,
                            "failed_at" => (string)time(),
                            "error" => $error->getMessage()
                        ])->then(function () use ($error) {
                            return \React\Promise\reject($error);
                        });
                    });
                } catch (\Exception $e) {
                    // 更新任务状态为失败
                    return $this->storage->setJobStatus($jobId, [
                        "status" => self::STATUS_FAILED,
                        "failed_at" => (string)time(),
                        "error" => $e->getMessage()
                    ])->then(function () use ($e) {
                        return \React\Promise\reject($e);
                    });
                }
            });
    }

    private function runCallbackInProcess($closure)
    {
        return $this->processManager->run($closure)->then(function ($stream) {
            return $this->streamToPromise($stream);
        }, function ($error) {
            throw new \Exception($error->getMessage());
        });
    }

    protected static function streamToPromise($stream)
    {

        $deferred = new Deferred(function () use ($stream) {
            $stream->close();
        });

        $data = null;
        $stream->on('data', function ($buffer) use (&$data) {
            $data .= $buffer;
        });

        $stream->on('close', function () use ($deferred, &$data) {
            if ($data === null) {
                $deferred->reject(new \Exception('No data received from process'));
            } else {
                $deferred->resolve($data);
                $data = null;
            }
           
        });

        $stream->on('error', function ($e) use ($deferred) {
            $deferred->reject($e);
        });

        return $deferred->promise();
    }

    public function clearJobs() {
        return $this->storage->clearJobs();
    }
}