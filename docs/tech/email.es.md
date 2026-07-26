# Envío de correos electrónicos

El sistema de correos usa Symfony Mailer y soporta mensajes con plantillas. El envío normalmente se encola para no ralentizar las solicitudes.

Archivos clave:
- `app/Services/System/EmailService.php`
- `app/Views/emails/`
- `app/Libraries/Queue/Jobs/SendEmailJob.php`
- `app/Libraries/Queue/Jobs/SendTemplateEmailJob.php`

Variables de entorno:
- `EMAIL_FROM_ADDRESS`
- `EMAIL_FROM_NAME`
- `EMAIL_PROVIDER` (smtp, sendmail, mail)
- `EMAIL_SMTP_HOST`
- `EMAIL_SMTP_PORT`
- `EMAIL_SMTP_USER`
- `EMAIL_SMTP_PASS`
- `EMAIL_SMTP_CRYPTO`

Cola:
- Los jobs de email se encolan en `emails`.
- Inicia el worker con `php spark queue:work --queue=emails`.

Plantillas:
- Las plantillas viven en `app/Views/emails/`.
- `queueTemplate()` renderiza una vista y la envía vía cola.
