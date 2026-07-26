# Transactions

Database transactions are used to keep critical flows consistent.

Key files:
- `app/Services/Tokens/RefreshTokenService.php`

Notes:
- Refresh token rotation runs inside a DB transaction.
- The refresh token row is locked with `FOR UPDATE` to avoid concurrent reuse.
