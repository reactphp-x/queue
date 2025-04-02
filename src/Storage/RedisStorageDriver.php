<?php

namespace ReactphpX\Queue\Storage;

use Clue\React\Redis\RedisClient;
use React\Promise\PromiseInterface;

class RedisStorageDriver implements StorageDriverInterface
{
    private $redis;
    private $jobKeyPrefix;

    public function __construct(RedisClient $redis, string $jobKeyPrefix = 'job:')
    {
        $this->redis = $redis;
        $this->jobKeyPrefix = $jobKeyPrefix;
    }

    public function setJobStatus(string $jobId, array $data): PromiseInterface
    {
        $args = [];
        foreach ($data as $key => $value) {
            $args[] = $key;
            $args[] = (string)$value;
        }
        return $this->redis->hMSet($this->jobKeyPrefix . $jobId, ...$args);
    }

    public function getJobStatus(string $jobId): PromiseInterface
    {
        return $this->redis->hGetAll($this->jobKeyPrefix . $jobId)->then(function ($data) {
            $result = [];
            for ($i = 0; $i < count($data); $i += 2) {
                $result[$data[$i]] = $data[$i + 1];
            }
            return $result;
        });
    }

    public function getAllJobs(int $offset = 0, int $limit = 10): PromiseInterface
    {
        return $this->redis->keys($this->jobKeyPrefix . '*')
            ->then(function ($keys) use ($offset, $limit) {
                $keys = array_slice($keys, $offset, $limit);
                $promises = [];
                foreach ($keys as $key) {
                    $jobId = str_replace($this->jobKeyPrefix, '', $key);
                    $promises[$jobId] = $this->getJobStatus($jobId);
                }
                return \React\Promise\all($promises);
            });
    }

    public function clearJobs(): PromiseInterface
    {
        return $this->redis->keys($this->jobKeyPrefix . '*')
            ->then(function ($keys) {
                $promises = [];
                foreach ($keys as $key) {
                    $promises[] = $this->redis->del($key);
                }
                return \React\Promise\all($promises);
            });
    }
}