# Email Delivery

The email system uses Symfony Mailer and supports template-based messages. Email sending is usually queued to avoid slowing down API requests.

Key files:
- `app/Services/System/EmailService.php`
- `app/Views/emails/`
- `app/Libraries/Queue/Jobs/SendEmailJob.php`
- `app/Libraries/Queue/Jobs/SendTemplateEmailJob.php`

Environment variables:
- `EMAIL_FROM_ADDRESS`
- `EMAIL_FROM_NAME`
- `EMAIL_PROVIDER` (smtp, sendmail, mail)
- `EMAIL_SMTP_HOST`
- `EMAIL_SMTP_PORT`
- `EMAIL_SMTP_USER`
- `EMAIL_SMTP_PASS`
- `EMAIL_SMTP_CRYPTO`

Queue usage:
- Email jobs are pushed to the `emails` queue.
- Start a worker with `php spark queue:work --queue=emails`.

Templates:
- Templates live in `app/Views/emails/`.
- `queueTemplate()` renders a view and sends it through the queue.
