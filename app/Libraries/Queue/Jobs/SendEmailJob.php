<?php

declare(strict_types=1);

namespace App\Libraries\Queue\Jobs;

use Config\Services;
use dcardenasl\Ci4ApiCore\Queue\Job;

class SendEmailJob extends Job
{
    /**
     * Handle the job
     *
     * @return void
     */
    public function handle(): void
    {
        $to = $this->data['to'] ?? '';
        $subject = $this->data['subject'] ?? '';
        $message = $this->data['message'] ?? '';
        $textMessage = $this->data['textMessage'] ?? null;

        if (empty($to) || empty($subject) || empty($message)) {
            throw new \InvalidArgumentException('Missing required email data');
        }

        $emailService = Services::emailService(false);
        $success = $emailService->send($to, $subject, $message, $textMessage);

        if (! $success) {
            throw new \RuntimeException("Failed to send email to {$to}");
        }
    }
}
