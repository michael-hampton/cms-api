<?php

namespace App\Repositories\Members\Consents;

use App\Models\ConsentAuditLog;

class ConsentAuditLogRepository
{
    public function create(array $data): ConsentAuditLog
    {
        $log = new ConsentAuditLog();

        foreach ($data as $key => $value) {
            $log->{$key} = $value;
        }

        $log->save();

        return $log;
    }
}