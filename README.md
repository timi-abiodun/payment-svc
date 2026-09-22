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
https://payment-svc-4jdo.onrender.com/api/v1
```

Requests then read `{{base_url}}/health`, `{{base_url}}/budget/v1/disburse`, etc.

Protected routes need `Authorization: Bearer <INTERNAL_SERVICE_TOKEN>` — request the current token value from the team rather than using a placeholder; it's a shared secret, not committed anywhere in the repo.

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

## API Reference

All routes are versioned under `/api/v1`. Amounts are integers in **kobo** (e.g. ₦10,000 = `1000000`). Protected routes require `Authorization: Bearer <INTERNAL_SERVICE_TOKEN>` — request the current token from the team; it's a shared secret, not committed anywhere in the repo.

---

### GET /health

No auth required. Confirms the service is up and which payment driver is active.

**Response (200):**
```json
{
  "ok": true,
  "driver": "fake"
}
```

---

### POST /budget/{vendorId}/disburse

Records both tranches (deposit + balance) for a booking and pays the deposit immediately. Safe to call more than once with the same `booking_id` — it will not create duplicate rows or pay twice.

**Path params:** `vendorId` — must already exist in `vendor_recipients`.

**Request:**
```json
{
  "booking_id": "demo-1",
  "total_kobo": 5000000,
  "deposit_percent": 40
}
```
`deposit_percent` is optional, 1–99, defaults to 50.

**Response (201):**
```json
{
  "deposit": {
    "id": 1,
    "vendor_id": "v1",
    "booking_id": "demo-1",
    "tranche": "deposit",
    "amount_kobo": 2000000,
    "status": "success",
    "reference": "dsb_ea6ea6fb-f7be-4b27-9e76-6b65f8c67e23",
    "provider_ref": "TRF_fake_LD6vskLJ",
    "meta": null,
    "created_at": "2026-09-22T09:43:51.000000Z",
    "updated_at": "2026-09-22T09:45:19.000000Z"
  },
  "balance": {
    "id": 2,
    "vendor_id": "v1",
    "booking_id": "demo-1",
    "tranche": "balance",
    "amount_kobo": 3000000,
    "status": "scheduled",
    "reference": "dsb_2ef1ffec-7db5-4df0-82f6-3225f19faff7",
    "provider_ref": null,
    "meta": null,
    "created_at": "2026-09-22T09:43:51.000000Z",
    "updated_at": "2026-09-22T09:43:51.000000Z"
  }
}
```

`status` is one of `scheduled`, `pending`, `processing`, `success`, `failed`. `meta` holds the provider error message when a payment fails.

**Errors:**
- `401` — missing/invalid bearer token
- `422` — validation failure (missing `booking_id` or `total_kobo`)
- `500` — `vendorId` has no row in `vendor_recipients`

---

### POST /bookings/{bookingId}/confirm-event

Releases the balance tranche for a booking. Safe to call more than once — only the first call actually pays; repeats are no-ops.

**Path params:** `bookingId` — must match a `booking_id` already created via `/disburse`.

**Request:** no body.

**Response (200):**
```json
{
  "id": 2,
  "vendor_id": "v1",
  "booking_id": "demo-1",
  "tranche": "balance",
  "amount_kobo": 3000000,
  "status": "success",
  "reference": "dsb_2ef1ffec-7db5-4df0-82f6-3225f19faff7",
  "provider_ref": "TRF_fake_gy9LESU6",
  "meta": null,
  "created_at": "2026-09-22T09:43:51.000000Z",
  "updated_at": "2026-09-22T09:45:49.000000Z"
}
```

**Errors:**
- `401` — missing/invalid bearer token
- `404` — no `balance` row exists for that `bookingId` (i.e. `/disburse` was never called for it)

---

### GET /budget/{vendorId}

Returns totals plus a paginated list of the vendor's disbursements, for the dashboard budget view.

**Path params:** `vendorId`

**Query params (optional):** `page`, `per_page` (max 100, default 50)

**Response (200):**
```json
{
  "vendor_id": "v1",
  "paid_kobo": 5000000,
  "outstanding_kobo": 0,
  "disbursements": {
    "current_page": 1,
    "data": [
      {
        "id": 2,
        "booking_id": "demo-1",
        "tranche": "balance",
        "amount_kobo": 3000000,
        "status": "success",
        "provider_ref": "TRF_fake_gy9LESU6",
        "created_at": "2026-09-22T09:43:51.000000Z"
      },
      {
        "id": 1,
        "booking_id": "demo-1",
        "tranche": "deposit",
        "amount_kobo": 2000000,
        "status": "success",
        "provider_ref": "TRF_fake_LD6vskLJ",
        "created_at": "2026-09-22T09:43:51.000000Z"
      }
    ],
    "per_page": 50,
    "total": 2,
    "last_page": 1,
    "next_page_url": null,
    "prev_page_url": null
  }
}
```

`disbursements.data` rows omit `meta` (never exposed here) — fetch a single record via your own tooling if you need the failure reason.

---

### POST /webhooks/paystack

Called by Paystack, not by the backend. Verified via `x-paystack-signature` (HMAC-SHA512 of the raw body, signed with your Paystack secret key) — no bearer token, and calling it manually without a valid signature returns `401`.

**Request (from Paystack):**
```json
{
  "event": "transfer.success",
  "data": {
    "reference": "dsb_2ef1ffec-7db5-4df0-82f6-3225f19faff7"
  }
}
```

**Response:** `204 No Content` (always, to prevent Paystack retry storms — failures are logged internally, not surfaced in the response).

---

## Known limitation

Real Paystack transfers are blocked in this environment — new Paystack accounts default to "starter" tier, which cannot initiate third-party payouts (vendor disbursement) until business verification is complete. The `paystack` driver is implemented and tested against the sandbox API up to that point; `fake` is the driver used for demos until verification clears.

## Next steps (not needed for the demo)

- Move Paystack transfers to a queued job so requests don't block on the provider.
- Sweep and retry disbursements stuck in `processing` (e.g. after a crash mid-transfer).
- Cache vendor recipient lookups.

## Notes

- Vendors need a row in `vendor_recipients` (a Paystack recipient code) before they can be paid.
- For local webhook testing, expose the app with ngrok and set the URL in the Paystack test dashboard.
- Never commit `.env`.