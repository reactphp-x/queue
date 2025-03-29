<?php

namespace ReactphpX\Queue;

use Clue\React\Redis\RedisClient;

class Queue implements QueueInterface
{
    private $redis;
    private $prefix = '';

    public function __construct(RedisClient $redis, string $prefix = '')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    private function getQueueName(string $queueName)
    {
        return $this->prefix ? $this->prefix . ':' . $queueName : $queueName;
    }

    public function enqueue($data, string $queueName = 'default')
    {
        return $this->redis->rpush($this->getQueueName($queueName), json_encode($data));
    }

    public function dequeue(string $queueName = 'default')
    {
        return $this->redis->lpop($this->getQueueName($queueName))->then(function ($data) {
            return $data ? json_decode($data, true) : null;
        });
    }

    public function blockingDequeue($timeout = 0, string $queueName = 'default')
    {
        return $this->redis->blpop($this->getQueueName($queueName), $timeout)->then(function ($data) {
            return $data ? json_decode($data[1], true) : null;
        });
    }

    public function size(string $queueName = 'default')
    {
        return $this->redis->llen($this->getQueueName($queueName));
    }

    public function clear(string $queueName = 'default')
    {
        return $this->redis->del($this->getQueueName($queueName));
    }
}
