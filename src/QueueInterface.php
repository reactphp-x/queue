<?php

namespace ReactphpX\Queue;

interface QueueInterface
{
    /**
     * Add an item to the queue
     * @param mixed $data
     * @param string $queueName
     * @return mixed
     */
    public function enqueue(string $data, string $queueName = 'default');

    /**
     * Remove and return an item from the queue
     * @param string $queueName
     * @return mixed
     */
    public function dequeue(string $queueName = 'default');

    /**
     * Remove and return an item from the queue with blocking
     * @param int $timeout
     * @param string $queueName
     * @return mixed
     */
    public function blockingDequeue($timeout = 0, string $queueName = 'default');

    /**
     * Get the size of the queue
     * @param string $queueName
     * @return mixed
     */
    public function size(string $queueName = 'default');

    /**
     * Clear all items from the queue
     * @param string $queueName
     * @return mixed
     */
    public function clear(string $queueName = 'default');
}