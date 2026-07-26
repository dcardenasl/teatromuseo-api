# Request Logging

Requests can be logged asynchronously through a queue job to avoid slowing responses.

Key files:
- `app/Filters/RequestLoggingFilter.php`
- `app/Libraries/Queue/Jobs/LogRequestJob.php`
- `app/Database/Migrations/2026-01-29-201621_CreateRequestLogsTable.php`
- `app/Models/RequestLogModel.php`

Environment variables:
- `REQUEST_LOGGING_ENABLED`
- `SLOW_QUERY_THRESHOLD`

Notes:
- Log entries are stored in the `request_logs` table.
- Logs are queued in the `logs` queue.
