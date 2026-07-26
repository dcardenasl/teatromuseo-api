# Queue & Jobs (CI4)

This project ships with a simple database-backed queue and a CLI worker. It is used by email flows, request logging, and asynchronous audit logging.

## How It Works

- Jobs are stored in the `jobs` table.
- Failed jobs are moved to `failed_jobs`.
- A CLI worker processes jobs from a named queue.
- Email jobs use the `emails` queue.
- Request logging uses the `logs` queue.
- Audit logging uses the `audit` queue for non-critical events.

Main code locations:
- Queue config: `app/Config/Queue.php`
- Audit async config: `app/Config/Audit.php`
- Queue manager: `app/Libraries/Queue/QueueManager.php`
- Worker command: `app/Commands/QueueWork.php`
- Email jobs: `app/Libraries/Queue/Jobs/SendEmailJob.php`, `SendTemplateEmailJob.php`
- Request log job: `app/Libraries/Queue/Jobs/LogRequestJob.php`
- Audit job: `app/Libraries/Queue/Jobs/WriteAuditLogJob.php`
- Email enqueuing: `app/Services/System/EmailService.php`
- Audit orchestration: `app/Services/System/AuditService.php`

## Configuration (.env)

```
QUEUE_DRIVER = database
QUEUE_MAX_ATTEMPTS = 3
QUEUE_RETRY_AFTER = 90
AUDIT_ASYNC_ENABLED = true
AUDIT_QUEUE_NAME = audit
AUDIT_MAX_PAYLOAD_BYTES = 60000
```

Notes:
- The current `QueueManager` implementation uses **database** tables.
- If you set `QUEUE_DRIVER=redis`, it will still use the database unless the manager is extended.
- In `ENVIRONMENT=testing`, queue DB connection defaults to `tests` (can be overridden by `QUEUE_DATABASE_CONNECTION`).

## Required Migrations

Run migrations to create queue tables:

```
php spark migrate
```

Migrations are:
- `app/Database/Migrations/2026-01-29-200038_CreateJobsTable.php`
- `app/Database/Migrations/2026-01-29-200102_CreateFailedJobsTable.php`

## Running the Worker

Process the `emails` queue continuously:

```
php spark queue:work --queue=emails
```

Process one job and exit:

```
php spark queue:work --queue=emails --once
```

Limit processing:

```
php spark queue:work --queue=emails --max-jobs=10
```

Process audit jobs continuously:

```
php spark queue:work --queue=audit
```

Process multiple queues with separate workers (recommended):

```
php spark queue:work --queue=emails
php spark queue:work --queue=logs
php spark queue:work --queue=audit
```

## Verifying It Works

1. Trigger a job:
   - Register a user (verification email).
   - Request password reset.
   - Hit any API endpoint with request logging enabled.
2. Confirm rows exist in `jobs` for expected queues (`emails`, `logs`, `audit`).
3. Run the worker:
   - `php spark queue:work --queue=emails --once`
4. Confirm job is removed from `jobs` and email is sent.
5. If it fails, it will retry and then move to `failed_jobs`.

## Troubleshooting

- No jobs in `jobs`:
  - The action that should enqueue the job didn’t run or failed before enqueue.
- Jobs never process:
  - Worker not running, or wrong `--queue` name.
- Jobs keep failing:
  - Check email configuration and app logs.
- Queue health:
  - `app/Libraries/Monitoring/HealthChecker.php` validates `jobs` and reports counts.

## Recommended Process (Dev/Prod)

Development:
- Run the worker in a separate terminal while testing features.

Production:
- Run queue workers under a process supervisor (systemd, supervisor, PM2, etc.) for each active queue (`emails`, `logs`, `audit`).
