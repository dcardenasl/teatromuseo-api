<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Interfaces\System\EmailServiceInterface;
use App\Libraries\Queue\Jobs\SendEmailJob;
use App\Libraries\Queue\Jobs\SendTemplateEmailJob;
use dcardenasl\Ci4ApiCore\Queue\QueueManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Standard Email Service
 *
 * Handles sending and queuing of system emails using templates.
 */
readonly class EmailService implements EmailServiceInterface
{
    public function __construct(
        protected ?MailerInterface $mailer = null,
        protected ?QueueManagerInterface $queueManager = null,
        protected string $fromAddress = 'no-reply@example.com',
        protected string $fromName = \Config\Project::NAME,
        protected string $defaultLocale = 'en'
    ) {
    }

    /**
     * Send an email immediately (Synchronous)
     */
    public function send(string $to, string $subject, string $message, ?string $textMessage = null): bool
    {
        if ($this->mailer === null) {
            log_message('debug', 'EmailService: No mailer driver configured. Skipping send.');
            return true; // Assume success in dev environments without mailer
        }

        try {
            $email = (new Email())
                ->from(new Address($this->fromAddress, $this->fromName))
                ->to($to)
                ->subject($subject)
                ->html($message);

            if ($textMessage !== null && $textMessage !== '') {
                $email->text($textMessage);
            }

            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            log_message('error', '[Email] Send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue an email to be sent later (Asynchronous)
     */
    public function queue(string $to, string $subject, string $message, ?string $textMessage = null): int
    {
        if ($this->queueManager === null) {
            log_message('error', 'EmailService: QueueManager not available.');
            return 0;
        }

        return $this->queueManager->push(SendEmailJob::class, [
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $message,
            'textMessage' => $textMessage,
        ], 'emails');
    }

    /**
     * Send an email using a template immediately
     *
     * @param array<string, mixed> $data
     */
    public function sendTemplate(string $template, string $to, $data): bool
    {
        $previousLocale = null;
        $requestedLocale = null;

        try {
            if (is_array($data) && isset($data['locale']) && is_string($data['locale'])) {
                $requestedLocale = strtolower(trim($data['locale']));
            }

            if ($requestedLocale !== null && $requestedLocale !== '') {
                $previousLocale = $this->currentLocale();
                $this->applyLocale($requestedLocale);
            }

            $html = view('emails/' . $template, $data);
            $subject = (string) ($data['subject'] ?? ('Email: ' . $template));
            $textMessage = isset($data['textMessage']) && is_string($data['textMessage']) ? $data['textMessage'] : null;

            return $this->send($to, $subject, $html, $textMessage);
        } catch (\Throwable $e) {
            log_message('error', "[Email] Template '{$template}' render/send failed: " . $e->getMessage());
            return false;
        } finally {
            if ($previousLocale !== null) {
                $this->applyLocale($previousLocale);
            }
        }
    }

    /**
     * Queue a template email
     *
     * @param array<string, mixed> $data
     */
    public function queueTemplate(string $template, string $to, $data = []): int
    {
        if ($this->queueManager === null) {
            log_message('error', 'EmailService: QueueManager not available.');
            return 0;
        }

        // Set locale for template rendering if not provided
        if (!isset($data['locale']) || !is_string($data['locale']) || $data['locale'] === '') {
            $data['locale'] = $this->defaultLocale;
        }

        return $this->queueManager->push(SendTemplateEmailJob::class, [
            'template' => $template,
            'to'       => $to,
            'data'     => $data,
        ], 'emails');
    }

    private function currentLocale(): ?string
    {
        try {
            return (string) service('request')->getLocale();
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyLocale(string $locale): void
    {
        try {
            service('request')->setLocale($locale);
        } catch (\Throwable) {
            // no-op in CLI contexts without a request
        }

        try {
            service('language')->setLocale($locale);
        } catch (\Throwable) {
            // no-op if language service is unavailable
        }
    }
}
