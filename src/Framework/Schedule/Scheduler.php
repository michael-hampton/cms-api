<?php

namespace App\Framework\Schedule;

use App\Framework\Queue\JobInterface;
use App\Framework\Queue\Queue;
use App\Framework\Queue\QueueInterface;
use App\Framework\Support\Logger;

class Scheduler
{
    private array $events = [];
    private QueueInterface $queue;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    public function job(JobInterface $job): ScheduledEvent
    {
        $event = new ScheduledEvent($job);
        $this->events[] = $event;
        return $event;
    }

    public function run(): void
    {
        $now = time();

        foreach ($this->events as $event) {
            if ($event->isDue($now)) {
                try {
                    // Push to queue
                    $this->queue->push($event->getJob());
                    $event->markAsRun($now);

                    Logger::info('Scheduled job queued', [
                        'job' => get_class($event->getJob()),
                        'schedule' => $event->getExpression()
                    ]);
                } catch (\Exception $e) {
                    Logger::error('Failed to queue scheduled job', [
                        'job' => get_class($event->getJob()),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}