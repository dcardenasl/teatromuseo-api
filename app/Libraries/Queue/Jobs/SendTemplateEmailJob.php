<?php

declare(strict_types=1);

namespace App\Libraries\Queue\Jobs;

use Config\Services;
use dcardenasl\Ci4ApiCore\Queue\Job;

class SendTemplateEmailJob extends Job
{
    /**
     * Handle the job
     *
     * @return void
     */
    public function handle(): void
    {
        $template = $this->data['template'] ?? '';
        $to = $this->data['to'] ?? '';
        $templateData = $this->data['data'] ?? null;

        if (empty($template) || empty($to) || $templateData === null) {
            throw new \InvalidArgumentException('Missing required email data');
        }

        $emailService = Services::emailService(false);
        $success = $emailService->sendTemplate($template, $to, $templateData);

        if (! $success) {
            throw new \RuntimeException("Failed to send template email '{$template}' to {$to}");
        }
    }
}
