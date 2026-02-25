<?php

namespace App\Framework\Queue;

use App\Framework\Database\Database;

class DatabaseQueueDriver implements QueueDriverInterface
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function push(Job $job): void
    {
        $this->db->insert('jobs', [
            'queue' => $job->queue ?? 'default',
            'payload' => serialize($job),
            'attempts' => 0,
            'available_at' => time() + $job->delay,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function pop(): ?QueuedJob
    {
        $record = $this->db->fetchOne(
            "SELECT * FROM jobs 
             WHERE available_at <= :now 
             ORDER BY id ASC 
             LIMIT 1",
            ['now' => time()]
        );

        if (!$record) {
            return null;
        }

        $this->db->delete('jobs', ['id' => $record['id']]);

        return new QueuedJob($record);
    }
}