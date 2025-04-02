<?php


use Pest\TestSuite;
use ReactphpX\Queue\JobManager;
use ReactphpX\Queue\Queue;
use ReactphpX\Queue\Storage\StorageDriverInterface;
use function React\Async\async;
use function React\Async\await;

beforeEach(function () {
    $this->storageMock = $this->createMock(StorageDriverInterface::class);
    $this->queueMock = $this->createMock(Queue::class);
    $this->jobManager = new JobManager($this->storageMock, $this->queueMock);
});

test('pushJob creates job', function () {
    $closure = function() { return 'test'; };
    $this->storageMock->expects($this->once())
        ->method('setJobStatus')
        ->willReturn(React\Promise\resolve(true));

    $this->queueMock->expects($this->once())
        ->method('enqueue')
        ->willReturn(React\Promise\resolve(true));

    $promise = $this->jobManager->pushJob('job1', $closure);
    expect($promise)->toBeInstanceOf(React\Promise\PromiseInterface::class);
});

test('getJobStatus', function () {
    $this->storageMock->expects($this->once())
        ->method('getJobStatus')
        ->with('job1')
        ->willReturn(React\Promise\resolve(['status' => 'completed']));

    $status = await($this->jobManager->getJobStatus('job1'));
    expect($status['status'])->toBe('completed');
});

test('processJob', function () {
    $data = json_encode(['job_id' => 'job1', 'closure' =>  \ReactphpX\SerializableClosure\SerializableClosure::serialize(function() { return 'result'; }), 'use_process' => false]);

    $this->storageMock->expects($this->exactly(2))
        ->method('setJobStatus')
        ->willReturn(React\Promise\resolve(true));

    $promise = $this->jobManager->processJob($data);
    expect($promise)->toBeInstanceOf(React\Promise\PromiseInterface::class);
});