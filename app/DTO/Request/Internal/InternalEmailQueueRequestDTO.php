<?php

declare(strict_types=1);

namespace App\DTO\Request\Internal;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;

readonly class InternalEmailQueueRequestDTO extends BaseRequestDTO
{
    public string  $to;
    public string  $subject;
    public string  $message;
    public ?string $text_message;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'to'      => 'required|valid_email',
            'subject' => 'required|string|max_length[500]',
            'message' => 'required|string',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->to           = (string) ($data['to'] ?? '');
        $this->subject      = (string) ($data['subject'] ?? '');
        $this->message      = (string) ($data['message'] ?? '');
        $this->text_message = isset($data['text_message']) && $data['text_message'] !== '' ? (string) $data['text_message'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'to'           => $this->to,
            'subject'      => $this->subject,
            'message'      => $this->message,
            'text_message' => $this->text_message,
        ];
    }
}
