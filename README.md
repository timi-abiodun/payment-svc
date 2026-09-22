# Payment & Disbursement Service

A small Laravel API that pays vendors in two tranches: a **deposit** at booking and the **balance** on event-day confirmation. Payouts go through Paystack Transfers, with a fake driver as a demo fallback.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=VendorRecipientSeeder
php artisan serve
```

## Environment

| Variable | Description |
|---|---|
| `PAYMENTS_DRIVER` | `paystack` or `fake` |
| `PAYSTACK_SECRET_KEY` | Paystack secret key (`sk_test_...` for sandbox) |
| `INTERNAL_SERVICE_TOKEN` | Bearer token the main backend must send |

Run `php artisan config:clear` after changing `.env`.

## Endpoints

All routes are versioned under `/api/v1`. Amounts are integers in **kobo**.
Protected routes need `Authorization: Bearer <INTERNAL_SERVICE_TOKEN>`.

| Method | Route | Auth | Purpose |
|---|---|---|---|
| GET | `/api/v1/health` | No | Service status and active driver |
| POST | `/api/v1/budget/{vendorId}/disburse` | Yes | Record both tranches and pay the deposit |
| POST | `/api/v1/bookings/{bookingId}/confirm-event` | Yes | Pay the balance |
| GET | `/api/v1/budget/{vendorId}` | Yes | Paginated paid/outstanding totals for the dashboard (`?page=`, `?per_page=`, max 100) |
| POST | `/api/v1/webhooks/paystack` | Signature | Paystack transfer status updates |

Example:

```json
POST /api/v1/budget/v1/disburse
{ "booking_id": "demo-1", "total_kobo": 5000000, "deposit_percent": 40 }
```

## Postman

Set the environment's `base_url` to:

```
http://127.0.0.1:8000/api/v1
```

Requests then read `{{base_url}}/health`, `{{base_url}}/budget/v1/disburse`, etc.

## How it works

- Each booking gets two rows in `disbursements` (`deposit`, `balance`), unique per `(booking_id, tranche)`, so repeated calls never pay twice.
- Statuses: `scheduled` → `pending` → `processing` → `success` / `failed`. A row is atomically claimed before any Paystack call, so concurrent or repeated confirmation requests can't double-pay.
- The Paystack webhook is verified with an HMAC-SHA512 signature and updates transfer status.

## Demo fallback

If the Paystack sandbox misbehaves, set `PAYMENTS_DRIVER=fake` and restart. The API contract and responses stay the same, and every transfer succeeds instantly. `GET /api/v1/health` shows which driver is live.

## Tests

```bash
php artisan test
```

## Next steps (not needed for the demo)

- Move Paystack transfers to a queued job so requests don't block on the provider.
- Sweep and retry disbursements stuck in `processing` (e.g. after a crash mid-transfer).
- Cache vendor recipient lookups.

## Notes

- Vendors need a row in `vendor_recipients` (a Paystack recipient code) before they can be paid.
- For local webhook testing, expose the app with ngrok and set the URL in the Paystack test dashboard.
- Never commit `.env`.

## Known limitation

Real Paystack transfers are blocked in this environment — new Paystack accounts default to "starter" tier, which cannot initiate third-party payouts (vendor disbursement) until business verification is complete. The `paystack` driver is implemented and tested against the sandbox API up to that point; `fake` is the driver used for demos until verification clears.