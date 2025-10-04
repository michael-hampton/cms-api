<?php

namespace App\Jobs;

use App\Framework\Queue\Job;
use App\Framework\Support\Logger;
use App\Framework\Support\Mail;

class SendEmailJob extends Job
{
    private $to;
    private $subject;
    private $body;

    public function __construct(string $to, string $subject, string $body)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->body = $body;
    }

    public function handle(): void
    {
        Mail::send($this->to, $this->subject, $this->body);
        Logger::info('Email sent via queue', ['to' => $this->to]);
    }
}