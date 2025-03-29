<?php

namespace ReactphpX\Queue;

use React\Promise\Deferred;
use function React\Promise\resolve;
use React\Promise\PromiseInterface;
use React\EventLoop\Loop;

class ArrayQueue implements QueueInterface
{
    private $queues = [];
    private $waitingConsumers = [];

    public function enqueue($data, string $queueName = 'default'): PromiseInterface
    {
        if (!isset($this->queues[$queueName])) {
            $this->queues[$queueName] = [];
        }

        $this->queues[$queueName][] = $data;

        // 如果有等待的消费者，通知第一个
        if (isset($this->waitingConsumers[$queueName]) && !empty($this->waitingConsumers[$queueName])) {
            $deferred = array_shift($this->waitingConsumers[$queueName]);
            $deferred->resolve($this->dequeue($queueName));
        }

        return resolve(true);
    }

    public function dequeue(string $queueName = 'default'): PromiseInterface
    {
        if (!isset($this->queues[$queueName]) || empty($this->queues[$queueName])) {
            return resolve(null);
        }

        $data = array_shift($this->queues[$queueName]);
        return resolve($data);
    }

    public function blockingDequeue($timeout = 0, string $queueName = 'default'): PromiseInterface
    {
        if (!isset($this->queues[$queueName])) {
            $this->queues[$queueName] = [];
        }

        if (!empty($this->queues[$queueName])) {
            return $this->dequeue($queueName);
        }

        $deferred = new Deferred();

        if (!isset($this->waitingConsumers[$queueName])) {
            $this->waitingConsumers[$queueName] = [];
        }
        $this->waitingConsumers[$queueName][] = $deferred;

        if ($timeout > 0) {
            Loop::addTimer($timeout, function () use ($deferred, $queueName) {
                $index = array_search($deferred, $this->waitingConsumers[$queueName]);
                if ($index !== false) {
                    array_splice($this->waitingConsumers[$queueName], $index, 1);
                    $deferred->resolve(null);
                }
            });
        }

        return $deferred->promise();
    }

    public function size(string $queueName = 'default'): PromiseInterface
    {
        $size = isset($this->queues[$queueName]) ? count($this->queues[$queueName]) : 0;
        return resolve($size);
    }

    public function clear(string $queueName = 'default'): PromiseInterface
    {
        $this->queues[$queueName] = [];
        return resolve(true);
    }
}