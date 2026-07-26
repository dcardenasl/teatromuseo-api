<?php

declare(strict_types=1);

namespace App\Interfaces\System;

/**
 * Email Service Interface
 *
 * Contract for email sending functionality
 */
interface EmailServiceInterface
{
    /**
     * Send an email immediately
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param string|null $textMessage Plain text version (optional)
     * @return bool
     */
    public function send(string $to, string $subject, string $message, ?string $textMessage = null): bool;

    /**
     * Queue an email to be sent later
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param string|null $textMessage Plain text version (optional)
     * @return int Job ID
     */
    public function queue(string $to, string $subject, string $message, ?string $textMessage = null): int;

    /**
     * Send an email using a template
     *
     * @param string $template Template name (e.g., 'verification')
     * @param string $to Recipient email address
     * @param array<string, mixed> $data Template data
     * @return bool
     */
    /** @param array<string, mixed> $data */
    public function sendTemplate(string $template, string $to, $data): bool;

    /**
     * Queue a template email
     *
     * @param string $template Template name
     * @param string $to Recipient email address
     * @param array<string, mixed> $data Template data
     * @return int Job ID
     */
    /** @param array<string, mixed> $data */
    public function queueTemplate(string $template, string $to, $data = []): int;
}
