# DB Copy Runner

This application exposes an authenticated API for running background MySQL database copies using `mydumper` / `myloader`.

## Requirements

- `mydumper` and `myloader` must be installed on the server and available on the `$PATH`.
- Configure your MySQL server credentials in `config/database.php` using one or more connections (for example `mysql`, `mysql_source`, `mysql_destination`).
- Set a webhook signing secret in your environment:

```bash
DB_COPY_WEBHOOK_SECRET=some-long-random-string
```

## Creating a Sanctum API token

1. Ensure migrations have been run (including Sanctum's `personal_access_tokens` table):

```bash
php artisan migrate
```

2. In Tinker, create a personal access token for an existing user:

```bash
php artisan tinker

>>> $user = App\Models\User::first();
>>> $token = $user->createToken('db-copy-runner');
>>> $token->plainTextToken;
// Copy the printed value; this is your Bearer token.
```

Use this token in the `Authorization` header:

```http
Authorization: Bearer <plain-text-token>
```

## API Endpoints

### Start a DB copy

- **Method**: `POST /api/db-copies`
- **Auth**: `Authorization: Bearer <token>` (Sanctum)
- **Body**:

```json
{
  "source": {
    "connection": "mysql",
    "database": "source_db"
  },
  "destination": {
    "connection": "mysql",
    "database": "dest_db"
  },
  "threads": 8,
  "recreateDestination": true,
  "callback_url": "https://example.com/db-copy-webhook"
}
```

- **Response** (`201 Created`):

```json
{
  "id": "uuid-of-db-copy",
  "status": "queued"
}
```

### Get DB copy status

- **Method**: `GET /api/db-copies/{id}`
- **Auth**: `Authorization: Bearer <token>` (must be the user who created the copy)
- **Response** (`200 OK`):

```json
{
  "id": "uuid-of-db-copy",
  "status": "queued|running|succeeded|failed",
  "progress": 0,
  "source_connection": "mysql",
  "source_db": "source_db",
  "dest_connection": "mysql",
  "dest_db": "dest_db",
  "callback_url": "https://example.com/db-copy-webhook",
  "started_at": "2026-02-09T08:46:34Z",
  "finished_at": "2026-02-09T08:48:12Z",
  "last_error": null,
  "created_by_user_id": 1,
  "created_at": "2026-02-09T08:46:34Z",
  "updated_at": "2026-02-09T08:46:34Z"
}
```

## Webhook payload and signature

On each status change (`running`, `succeeded`, `failed`), the job sends a POST request to the configured `callback_url` with JSON:

```json
{
  "id": "uuid-of-db-copy",
  "status": "running|succeeded|failed",
  "started_at": "2026-02-09T08:46:34Z",
  "finished_at": "2026-02-09T08:48:12Z",
  "error": null
}
```

The request includes an HMAC signature header:

- **Header**: `X-Signature`
- **Algorithm**: `HMAC-SHA256`
- **Secret**: `config('services.db_copy_webhook.secret')` (from `DB_COPY_WEBHOOK_SECRET`)
- **Payload**: The exact JSON body as sent.

To verify the signature:

1. Read the raw request body as a string.
2. Compute `hash_hmac('sha256', $body, env('DB_COPY_WEBHOOK_SECRET'))`.
3. Compare the result with the value from the `X-Signature` header using a constant-time comparison.

