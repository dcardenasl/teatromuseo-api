<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Email extends BaseConfig
{
    /**
     * Email provider selector used by App\Services\System\EmailService wiring.
     * Supported values: smtp, none.
     */
    public string $provider = 'none';

    public string $fromEmail  = '';
    public string $fromName   = '';
    public string $recipients = '';

    /**
     * The "user agent"
     */
    public string $userAgent = 'CodeIgniter';

    /**
     * The mail sending protocol: mail, sendmail, smtp
     */
    public string $protocol = 'mail';

    /**
     * The server path to Sendmail.
     */
    public string $mailPath = '/usr/sbin/sendmail';

    /**
     * SMTP Server Hostname
     */
    public string $SMTPHost = '';

    /**
     * SMTP Username
     */
    public string $SMTPUser = '';

    /**
     * SMTP Password
     */
    public string $SMTPPass = '';

    /**
     * SMTP Port
     */
    public int $SMTPPort = 25;

    /**
     * SMTP Timeout (in seconds)
     */
    public int $SMTPTimeout = 5;

    /**
     * Enable persistent SMTP connections
     */
    public bool $SMTPKeepAlive = false;

    /**
     * SMTP Encryption.
     *
     * @var string '', 'tls' or 'ssl'. 'tls' will issue a STARTTLS command
     *             to the server. 'ssl' means implicit SSL. Connection on port
     *             465 should set this to ''.
     */
    public string $SMTPCrypto = 'tls';

    /**
     * Enable word-wrap
     */
    public bool $wordWrap = true;

    /**
     * Character count to wrap at
     */
    public int $wrapChars = 76;

    /**
     * Type of mail, either 'text' or 'html'
     */
    public string $mailType = 'text';

    /**
     * Character set (utf-8, iso-8859-1, etc.)
     */
    public string $charset = 'UTF-8';

    /**
     * Whether to validate the email address
     */
    public bool $validate = false;

    /**
     * Email Priority. 1 = highest. 5 = lowest. 3 = normal
     */
    public int $priority = 3;

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $CRLF = "\r\n";

    /**
     * Newline character. (Use “\r\n” to comply with RFC 822)
     */
    public string $newline = "\r\n";

    /**
     * Enable BCC Batch Mode.
     */
    public bool $BCCBatchMode = false;

    /**
     * Number of emails in each BCC batch
     */
    public int $BCCBatchSize = 200;

    /**
     * Enable notify message from server
     */
    public bool $DSN = false;

    public function __construct()
    {
        parent::__construct();

        $this->provider = strtolower(trim((string) env('EMAIL_PROVIDER', 'none')));
        $this->fromEmail = (string) env('EMAIL_FROM_ADDRESS', $this->fromEmail);
        $this->fromName = (string) env('EMAIL_FROM_NAME', $this->fromName);
        $this->SMTPHost = (string) env('EMAIL_SMTP_HOST', $this->SMTPHost);
        $this->SMTPPort = (int) env('EMAIL_SMTP_PORT', (string) $this->SMTPPort);
        $this->SMTPUser = (string) env('EMAIL_SMTP_USER', $this->SMTPUser);
        $this->SMTPPass = (string) env('EMAIL_SMTP_PASS', $this->SMTPPass);
        $this->SMTPCrypto = strtolower(trim((string) env('EMAIL_SMTP_CRYPTO', $this->SMTPCrypto)));
    }
}
