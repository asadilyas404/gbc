# POS Order Voucher API — Integration Guide

## Overview

ERP exposes an API so POS can create, recreate, or delete accounting vouchers (`POS` / `RPOS`) when an order is paid, updated, canceled, or returned.

Cash-Visa adjustment vouchers (`CADJ`) remain on the ERP schedule. POS does **not** call anything for cash adjustment.

---

## Endpoint

| Item | Value |
|------|--------|
| Method | `POST` |
| URL | `{ERP_BASE_URL}/api/pos-order-voucher` |
| Auth | None |
| Content-Type | `application/json` |

---

## When to Call

| POS event | API action |
|-----------|------------|
| Order paid / payment completed | Sync |
| Paid order updated (amount, payment method, etc.) | Sync (recreates voucher) |
| Order canceled or unpaid | Sync (deletes voucher) **or** `action: delete` |
| Sale return (`RPOS`) created/updated | Sync |

### Important

Call **after** the order is fully saved and visible to ERP (`VW_REST_SUMMARY_ORDER_WISE`).

If the response says the order was not found, retry after 1–2 seconds.

Do **not** call on draft / unpaid cart saves — only when payment status, cancel, or return changes.

---

## Request Body

### Sync one order (create / update)

```json
{
  "order_id": 12345
}
```

### Sync multiple orders

```json
{
  "order_ids": [12345, 12346]
}
```

### Force delete vouchers for an order

```json
{
  "order_id": 12345,
  "action": "delete"
}
```

### Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `order_id` | number/string | One of `order_id` or `order_ids` | Restaurant order ID (same as ERP view `order_id`) |
| `order_ids` | array | One of `order_id` or `order_ids` | Multiple order IDs |
| `action` | string | No | `sync` (default) or `delete` |

---

## Response

### Success — `200`

```json
{
  "success": true,
  "message": "Order voucher processing completed",
  "data": {
    "results": [
      {
        "order_id": 12345,
        "action": "posted",
        "voucher_type": "POS",
        "voucher_id": "..."
      }
    ]
  }
}
```

### Per-result `action` values

| Value | Meaning |
|-------|---------|
| `posted` | Voucher created/recreated |
| `deleted` | Existing POS/RPOS vouchers removed |
| `skipped` | No voucher posted (e.g. not found, unsupported type, no payment account) |

### Errors

| HTTP | Meaning |
|------|---------|
| `422` | Validation failed (missing `order_id` / `order_ids`) |
| `500` | Server/processing error |

---

## Suggested POS Implementation

1. Trigger the API from order lifecycle hooks: paid, update-after-paid, cancel, return.
2. Call asynchronously (queue/job) so checkout is not blocked by ERP latency.
3. On failure or “order not found”, retry a few times with short delay.
4. Log `order_id`, request, and response body for support/debugging.
5. Use the same `order_id` stored in the POS `orders` table.

---

## Out of Scope for POS

- Building voucher debit/credit lines or chart of accounts
- Cash-Visa adjustment (`CADJ`) posting
- Manual ERP voucher rebuild screen / batch jobs

ERP owns all of the above.

---

## Example (cURL)

```bash
curl -X POST "{ERP_BASE_URL}/api/pos-order-voucher" \
  -H "Content-Type: application/json" \
  -d "{\"order_id\": 12345}"
```
