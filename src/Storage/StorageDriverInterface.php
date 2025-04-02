<?php

namespace ReactphpX\Queue\Storage;

use React\Promise\PromiseInterface;

interface StorageDriverInterface
{
    /**
     * 存储任务状态
     *
     * @param string $jobId 任务ID
     * @param array $data 任务数据
     * @return PromiseInterface
     */
    public function setJobStatus(string $jobId, array $data): PromiseInterface;

    /**
     * 获取任务状态
     *
     * @param string $jobId 任务ID
     * @return PromiseInterface
     */
    public function getJobStatus(string $jobId): PromiseInterface;

    /**
     * 获取所有任务
     *
     * @param int $offset 偏移量
     * @param int $limit 限制数量
     * @return PromiseInterface
     */
    public function getAllJobs(int $offset = 0, int $limit = 10): PromiseInterface;

    /**
     * 清除所有任务
     *
     * @return PromiseInterface
     */
    public function clearJobs(): PromiseInterface;
}