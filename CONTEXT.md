# Domain context

## File ingestion

File ingestion is the complete lifecycle that turns an accepted multipart or
base64 upload into durable file metadata: validation, malware scanning,
content hashing, storage-key allocation, original storage, image variants,
metadata persistence, replacement retirement, and compensation on failure.

Both file creation and binary replacement cross the same
`BinaryIngestionInterface` Seam. Authorization and visibility policy remain in
`FileService`; the ingestion Module owns binary durability and rollback.
