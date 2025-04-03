<?php

namespace ReactphpX\Queue;

use Clue\React\Redis\RedisClient;
use React\Promise\PromiseInterface;
use ReactphpX\TaskPoller\TaskPoller;
use ReactphpX\TaskPoller\TaskPollerManager;

class Consumer
{
    private $queue;
    private $priorityQueues;
    private $running = false;
    private $callback;
    private $poller;
    private $maxAttempts = 3;
    private $consumerCount = 2;
    private $currentQueueIndex = 0;
    private $consumedCount = [];
    private $checkHighPriorityAfter = 10;

    public function __construct(QueueInterface $queue, array $priorityQueues = ['high', 'middle', 'low', 'default'], int $maxAttempts = 1, int $consumerCount = 2)
    {
        $this->queue = $queue;
        $this->priorityQueues = $priorityQueues;
        $this->maxAttempts = $maxAttempts;
        $this->consumerCount = $consumerCount;
    }

    public function consume(callable $callback)
    {
        $this->callback = $callback;
        $this->running = true;
        
        // 初始化消费计数器
        foreach ($this->priorityQueues as $queue) {
            $this->consumedCount[$queue] = 0;
        }
        
        // 启动多个消费者
        for ($i = 0; $i < $this->consumerCount; $i++) {
            $this->processNextMessage();
        }
    }

    public function stop()
    {
        $this->running = false;
    }

    private function processNextMessage()
    {
        if (!$this->running) {
            return;
        }

        $this->dequeueFromPriorityQueues()->then(
            function ($data) {
                if ($data !== null) {
                    return $this->executeWithRetry($data);
                }
            }
        )->always(
            function () {
                if ($this->running) {
                    $this->processNextMessage();
                }
            }
        );
    }

    private function executeWithRetry($data, $attempt = 1)
    {
        // 如果数据是JSON字符串，解析它
        $taskData = is_string($data) ? json_decode($data, true) : $data;
        
        // 添加或更新重试信息
        if (!isset($taskData['__retry_info'])) {
            $taskData['__retry_info'] = [
                'attempts' => 0,
                'first_attempt' => microtime(true),
                'last_attempt' => null
            ];
        }
        
        $taskData['__retry_info']['attempts'] = $attempt;
        $taskData['__retry_info']['last_attempt'] = microtime(true);
        
        // 如果原始数据是JSON字符串，重新编码
        $dataToProcess = is_string($data) ? json_encode($taskData) : $taskData;

        return \React\Promise\resolve(($this->callback)($dataToProcess))->then(
            function ($result) {
                return $result;
            },
            function ($error) use ($taskData, $attempt) {
                if ($attempt < $this->maxAttempts) {
                    $delay = pow(2, $attempt - 1) * 1000;
                    return new \React\Promise\Promise(function ($resolve) use ($taskData, $attempt, $delay) {
                        \React\EventLoop\Loop::addTimer($delay / 1000, function () use ($resolve, $taskData, $attempt) {
                            $resolve($this->executeWithRetry(json_encode($taskData), $attempt + 1));
                        });
                    });
                }
                throw new \Exception(sprintf(
                    'Task failed after %d attempts. Last error: %s',
                    $attempt,
                    $error->getMessage()
                ));
            }
        );
    }

    private function getPreviousQueueIndex($currentIndex)
    {
        if ($currentIndex <= 0) {
            return -1;
        }
        return $currentIndex - 1;
    }

    private function dequeueFromPriorityQueues()
    {
        $dequeueNext = function ($index = 0) use (&$dequeueNext) {
            $queueName = $this->priorityQueues[$index];
            
            // 如果当前队列消费次数达到10次，且不是最高优先级队列，检查前一个优先级队列
            if ($index > 0 && $this->consumedCount[$queueName] >= 10) {
                $prevIndex = $this->getPreviousQueueIndex($index);
                $this->consumedCount[$queueName] = 0; // 重置当前队列的计数
                
                return $this->queue->dequeue($this->priorityQueues[$prevIndex])->then(
                    function ($data) use ($index, $prevIndex, $dequeueNext) {
                        if ($data !== null) {
                            $this->consumedCount[$this->priorityQueues[$prevIndex]]++;
                            $this->currentQueueIndex = $prevIndex; // 更新当前队列索引
                            return $data;
                        }
                        // 如果前一个队列为空，继续检查当前队列
                        $this->currentQueueIndex = $index; // 更新当前队列索引
                        return $this->queue->dequeue($queueName)->then(
                            function ($data) use ($index, $queueName, $dequeueNext) {
                                if ($data === null) {
                                    // 如果当前队列也为空，检查下一个队列
                                    $nextIndex = $index + 1;
                                    if ($nextIndex >= count($this->priorityQueues)) {
                                        $nextIndex = 0;
                                    }
                                    $this->currentQueueIndex = $nextIndex; // 更新当前队列索引
                                    return $dequeueNext($nextIndex);
                                }
                                // 更新消费计数
                                $this->consumedCount[$queueName]++;
                                return $data;
                            }
                        );
                    }
                );
            }

            if ($index >= count($this->priorityQueues)) {
                $index = 0;
            }

            $queueName = $this->priorityQueues[$index];
            $this->currentQueueIndex = $index; // 更新当前队列索引
            return $this->queue->dequeue($queueName)->then(
                function ($data) use ($index, $queueName, $dequeueNext) {
                    if ($data === null) {
                        $nextIndex = $index + 1;
                        if ($nextIndex >= count($this->priorityQueues)) {
                            $nextIndex = 0;
                        }
                        return $dequeueNext($nextIndex);
                    }
                    // 更新消费计数
                    $this->consumedCount[$queueName]++;
                    return $data;
                }
            );
        };

        return $dequeueNext($this->currentQueueIndex);
    }
}