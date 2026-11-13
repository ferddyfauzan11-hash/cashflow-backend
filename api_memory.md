# API Memory — Cashflow RS Backend

> Catatan semua endpoint yang sudah di-build dan di-test.
> Acuan utama saat develop Mobile App (Flutter).

---

## Auth Endpoints

### POST `/api/register`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "name": "Test User",
  "email": "test@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response (201 Created):**
```json
{
  "user": {
    "name": "Test User",
    "email": "test@example.com",
    "id": "01a1d329-13ea-70ca-9a86-5bde3d9ffa45",
    "updated_at": "2026-11-13T04:27:27.000000Z",
    "created_at": "2026-11-13T04:27:27.000000Z"
  },
  "token": "1|QOl0dCtfS2HrhzjIAhnvosWB9Ns4Cy81yFPeOgVOdab93107"
}
```

**Validation Errors (422):**
- `name` → required, max:100
- `email` → required, email, unique, max:255
- `password` → required, min:8, confirmed

**Error Example (duplicate email):**
```json
{
  "message": "Email sudah terdaftar.",
  "errors": {
    "email": ["Email sudah terdaftar."]
  }
}
```

---

### POST `/api/login`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "email": "test@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "user": {
    "id": "01a1d329-13ea-70ca-9a86-5bde3d9ffa45",
    "name": "Test User",
    "email": "test@example.com",
    "created_at": "2026-11-13T04:27:27.000000Z",
    "updated_at": "2026-11-13T04:27:27.000000Z"
  },
  "token": "2|m81E41tCfI5VVrkzfkoFYQNlWb9Y7k9OsAeBLLXY988780c4"
}
```

**Error (401 — wrong credentials):**
```json
{
  "message": "Email atau password salah."
}
```

---

### POST `/api/logout`

**Status:** ✅ Tested & Working

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "message": "Berhasil logout."
}
```

**Error (401 — expired/revoked token):**
```json
{
  "message": "Unauthenticated."
}
```

---

## Notes

- **Auth:** Laravel Sanctum v4.4.0 (token-based)
- **UUID:** All user IDs are UUID v4, generated server-side via `HasUuids` trait
- **Token format:** `{id}|{plaintext}` — client stores full string
- **Header pattern:** `Authorization: Bearer {token}` on all protected endpoints
- **Validation messages:** Bahasa Indonesia
- **Framework:** Laravel 13.42.0



---

## Categories Endpoints

### GET `/api/categories`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "name": "Makan & Minum",
      "type": "expense",
      "icon": "🍔",
      "is_default": true,
      "is_deleted": false,
      "created_at": "2026-11-13T04:35:07.000000Z",
      "updated_at": "2026-11-13T04:35:07.000000Z"
    }
  ]
}
```

**Sorting:** type ASC → is_default DESC → name ASC
**Filter:** Only active (is_deleted=false), only user's own categories.

---

### POST `/api/categories`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "name": "Sedekah",
  "type": "expense",
  "icon": "🕌"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": "uuid",
    "user_id": "uuid",
    "name": "Sedekah",
    "type": "expense",
    "icon": "🕌",
    "is_default": false,
    ...
  }
}
```

**Validation:**
- `name` → required, string, max:50
- `type` → required, in:income,expense
- `icon` → nullable, string, max:20

---

### PUT `/api/categories/{id}`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "name": "Sedekah & Infaq",
  "icon": "🕌"
}
```

**Response (200 OK):**
```json
{
  "data": { ...updated category... }
}
```

---

### DELETE `/api/categories/{id}`

**Status:** ✅ Tested & Working

**Response (200 OK):**
```json
{
  "message": "Kategori berhasil dihapus."
}
```

**Guard:** Jika masih ada transaksi aktif yang pakai kategori ini → 422:
```json
{
  "message": "Kategori tidak bisa dihapus karena masih digunakan oleh X transaksi."
}
```


---

## Pockets Endpoints

### GET `/api/pockets`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Dana Darurat",
      "icon": "🛡️",
      "color": "#22C55E",
      "target_amount": "0.00",
      "current_balance": "0.00",
      "has_auto_target": true,
      "target_months": 6,
      "order_index": 0,
      "is_deleted": false,
      ...
    }
  ]
}
```

**Sorting:** order_index ASC.
**Auto-target:** Jika `has_auto_target=true`, `target_amount` di-recalculate saat GET (lazy).

---

### POST `/api/pockets`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "name": "Dana Darurat",
  "icon": "🛡️",
  "color": "#22C55E",
  "has_auto_target": true,
  "target_months": 6
}
```

**Response (201 Created):** `{ "data": { ...pocket... } }`

**Validation:** name required max:50, color hex format, target_amount min:0, target_months 1-24.

---

### PUT `/api/pockets/{id}`

**Status:** ✅ Tested & Working

**Request (partial):**
```json
{
  "name": "DP Rumah Impian",
  "target_amount": 75000000
}
```

**Response (200 OK):** `{ "data": { ...updated pocket... } }`

---

### DELETE `/api/pockets/{id}`

**Status:** ✅ Tested & Working

**Response (200 OK):**
```json
{
  "message": "Pocket berhasil dihapus. Saldo dikembalikan ke Unallocated."
}
```

**Side effects:**
- `pocket.current_balance` → 0
- All transactions with this `pocket_id` → set to NULL (Unallocated)
- `pocket.is_deleted` → true


---

## Pocket Allocations Endpoints

### GET `/api/pocket-allocations`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Query Params (Optional):**
- `pocket_id`: filter by pocket (either source or destination)
- `start_date`: YYYY-MM-DD
- `end_date`: YYYY-MM-DD

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "from_pocket_id": "uuid-or-null",
      "to_pocket_id": "uuid-or-null",
      "amount": "2000000.00",
      "note": "Transfer ke liburan",
      "allocation_date": "2026-11-13T00:00:00.000000Z",
      "from_pocket": { ... },
      "to_pocket": { ... }
    }
  ]
}
```

---

### POST `/api/pocket-allocations`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "from_pocket_id": "uuid-or-null",
  "to_pocket_id": "uuid-or-null",
  "amount": 2000000,
  "allocation_date": "2026-11-13",
  "note": "Transfer dana"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": "uuid",
    "amount": "2000000.00",
    ...
  }
}
```

**Validation & Rules:**
- `from_pocket_id` and `to_pocket_id` cannot be identical.
- Cannot both be null (Unallocated -> Unallocated is invalid).
- Source pocket balance check: error 422 if insufficient funds.
- Unallocated balance check: error 422 if insufficient funds.

---

### DELETE `/api/pocket-allocations/{id}`

**Status:** ✅ Tested & Working

**Response (200 OK):**
```json
{
  "message": "Transfer dibatalkan."
}
```

**Side effects:**
- Reverts balance for `from_pocket` (adds amount back)
- Reverts balance for `to_pocket` (deducts amount)
- Soft-deletes record (`is_deleted = true`)


---

## Transactions Endpoints

### GET `/api/transactions`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Query Params (Optional):**
- `type`: `income` or `expense`
- `category_id`: UUID
- `pocket_id`: UUID or `unallocated`
- `start_date`: YYYY-MM-DD
- `end_date`: YYYY-MM-DD
- `page`: int
- `per_page`: int (default 50)

**Response (200 OK - Paginated):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "category_id": "uuid",
      "pocket_id": "uuid-or-null",
      "type": "expense",
      "amount": "500000.00",
      "description": "Makan siang",
      "transaction_date": "2026-11-04",
      "is_deleted": false,
      "category": { "id": "uuid", "name": "Makan & Minum", ... },
      "pocket": { "id": "uuid", "name": "Dana Darurat", ... }
    }
  ],
  "total": 1
}
```

---

### POST `/api/transactions`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "id": "optional-client-generated-uuid",
  "category_id": "uuid",
  "pocket_id": "uuid-or-null",
  "type": "income",
  "amount": 10000000,
  "description": "Gaji November",
  "transaction_date": "2026-11-01"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": "uuid",
    "amount": "10000000.00",
    "type": "income",
    ...
  }
}
```

**Pocket Balance Update Rules:**
- Income with `pocket_id` -> adds to `pocket.current_balance`.
- Expense with `pocket_id` -> checks balance first (422 if insufficient), then subtracts from `pocket.current_balance`.
- Without `pocket_id` (null) -> affects Unallocated balance dynamically, pocket records remain untouched.

---

### PUT `/api/transactions/{id}`

**Status:** ✅ Tested & Working

**Request (partial updates supported):**
```json
{
  "amount": 1000000,
  "description": "Updated note"
}
```

**Response (200 OK):**
```json
{
  "data": { ...updated transaction... }
}
```

**Side effects:** Reverts previous pocket balance effect, applies new pocket balance effect.

---

### DELETE `/api/transactions/{id}`

**Status:** ✅ Tested & Working

**Response (200 OK):**
```json
{
  "message": "Transaksi berhasil dihapus."
}
```

**Side effects:** Reverts pocket balance effect and marks `is_deleted = true`.


---

## Loans Endpoints

### GET `/api/loans`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Query Params (Optional):**
- `status`: `active`, `completed`, `overdue`
- `type`: `lend`, `borrow`

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "uuid",
      "user_id": "uuid",
      "contact_name": "Budi Santoso",
      "type": "lend",
      "principal_amount": "2000000.00",
      "remaining_amount": "2000000.00",
      "due_date": "2026-12-01",
      "status": "active",
      "notes": "Pinjam modal usaha",
      "is_deleted": false,
      "installments_count": 0
    }
  ]
}
```

---

### POST `/api/loans`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "contact_name": "Budi Santoso",
  "type": "lend",
  "principal_amount": 2000000,
  "due_date": "2026-12-01",
  "notes": "Pinjam modal usaha"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": "uuid",
    "contact_name": "Budi Santoso",
    "type": "lend",
    "principal_amount": "2000000.00",
    "remaining_amount": "2000000.00",
    "status": "active",
    ...
  }
}
```

---

### GET `/api/loans/{id}`

**Status:** ✅ Tested & Working

**Response (200 OK):** Returns single loan object with its active `installments` relation.

---

### PUT `/api/loans/{id}`

**Status:** ✅ Tested & Working

**Request (partial updates):**
```json
{
  "notes": "Updated note"
}
```

**Response (200 OK):** `{ "data": { ...updated loan... } }`

---

### DELETE `/api/loans/{id}`

**Status:** ✅ Tested & Working

**Response (200 OK):**
```json
{
  "message": "Pinjaman berhasil dihapus."
}
```

**Side effects:** Soft deletes the loan and its installments (`is_deleted = true`).


---

## Loan Installments Endpoints

### GET `/api/loans/{id}/installments`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "uuid",
      "loan_id": "uuid",
      "user_id": "uuid",
      "amount_paid": "500000.00",
      "overpayment_amount": "0.00",
      "payment_date": "2026-11-13",
      "auto_income_generated": false,
      "notes": "Cicilan pertama",
      "is_deleted": false
    }
  ]
}
```

---

### POST `/api/loans/{id}/installments`

**Status:** ✅ Tested & Working

**Request:**
```json
{
  "amount_paid": 1700000,
  "payment_date": "2026-11-13",
  "notes": "Pelunasan + lebih"
}
```

**Response (201 Created):**
```json
{
  "message": "Pinjaman ke Budi Santoso LUNAS! Kelebihan Rp 200.000 dicatat sebagai pemasukan.",
  "data": {
    "id": "uuid",
    "loan_id": "uuid",
    "amount_paid": "1700000.00",
    "overpayment_amount": "200000.00",
    "auto_income_generated": true,
    ...
  },
  "loan": {
    "id": "uuid",
    "remaining_amount": "0.00",
    "status": "completed"
  }
}
```

**Business Logic Handled:**
1. If `amount_paid < remaining_amount`: updates `loan.remaining_amount = remaining - amount_paid`.
2. If `amount_paid == remaining_amount`: sets `loan.remaining_amount = 0`, marks `loan.status = 'completed'`.
3. If `amount_paid > remaining_amount`:
   - Calculates `overpayment = amount_paid - remaining_amount`
   - Sets `loan.remaining_amount = 0`, marks `loan.status = 'completed'`
   - Auto-generates an `income` transaction categorized as "Kelebihan Bayar Pinjaman" into Unallocated (`pocket_id = null`).
4. If loan is already `completed`: returns 422: `{"message": "Pinjaman sudah lunas."}`.


---

## Dashboard Summary Endpoint

### GET `/api/dashboard/summary`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Response (200 OK):**
```json
{
  "month": {
    "period": "2026-11",
    "income": 11700000,
    "expense": 0,
    "net": 11700000
  },
  "overall": {
    "total_income": 11700000,
    "total_expense": 0,
    "total_pocket_balance": 6500000,
    "unallocated_balance": 5200000
  },
  "pockets": [
    {
      "id": "uuid",
      "name": "Dana Darurat",
      "icon": "🛡️",
      "color": "#22C55E",
      "current_balance": "6500000.00",
      "target_amount": "0.00",
      "has_auto_target": true,
      "order_index": 0
    }
  ],
  "loans": {
    "active_count": 0,
    "total_lend_remaining": 0,
    "total_borrow_remaining": 0
  },
  "recent_expense_7_days": [
    { "date": "2026-11-07", "amount": 0 },
    ...
    { "date": "2026-11-13", "amount": 0 }
  ]
}
```


---

## Analytics Endpoints

### GET `/api/analytics/monthly`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Query Params (Optional):**
- `month`: 1-12 (default: current month)
- `year`: 4-digit year (default: current year)

**Response (200 OK):**
```json
{
  "period": "2026-11",
  "summary": {
    "income": 11700000,
    "expense": 0,
    "net": 11700000
  },
  "category_breakdown": [
    {
      "id": "uuid",
      "name": "Makan & Minum",
      "icon": "🍔",
      "total_amount": 500000,
      "percentage": 50.0
    }
  ],
  "daily_spending": [
    { "transaction_date": "2026-11-04", "total_amount": 500000 }
  ],
  "top_expenses": [
    {
      "id": "uuid",
      "amount": "500000.00",
      "description": "Makan siang",
      "transaction_date": "2026-11-04",
      "category": { "id": "uuid", "name": "Makan & Minum", "icon": "🍔" }
    }
  ]
}
```

---

### GET `/api/analytics/yearly`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Query Params (Optional):**
- `year`: 4-digit year (default: current year)

**Response (200 OK):**
```json
{
  "year": 2026,
  "summary": {
    "total_income": 11700000,
    "total_expense": 0,
    "net": 11700000,
    "avg_monthly_income": 975000,
    "avg_monthly_expense": 0,
    "avg_monthly_saving": 975000
  },
  "monthly_trend": [
    { "month": 1, "month_name": "Jan", "income": 0, "expense": 0, "net": 0 },
    ...
    { "month": 11, "month_name": "Nov", "income": 11700000, "expense": 0, "net": 11700000 }
  ],
  "highlights": {
    "most_expensive_month": null,
    "least_expensive_month": null
  }
}
```

---

### GET `/api/analytics/pocket/{id}`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Response (200 OK):**
```json
{
  "pocket": {
    "id": "uuid",
    "name": "Dana Darurat",
    "icon": "🛡️",
    "color": "#22C55E",
    "current_balance": 6500000,
    "target_amount": 0,
    "progress_percentage": 0
  },
  "recent_allocations": [ ... ],
  "recent_transactions": [ ... ]
}
```


---

## Sync Endpoints

### POST `/api/sync/push`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Request Payload:**
```json
{
  "categories": [
    {
      "id": "uuid",
      "name": "Investasi Saham",
      "type": "expense",
      "icon": "📈",
      "is_default": false,
      "is_deleted": false,
      "created_at": "2026-11-13T10:00:00Z",
      "updated_at": "2026-11-13T10:00:00Z"
    }
  ],
  "pockets": [ ... ],
  "pocket_allocations": [ ... ],
  "transactions": [ ... ],
  "loans": [ ... ],
  "loan_installments": [ ... ]
}
```

**Response (200 OK):**
```json
{
  "status": "ok",
  "synced_ids": {
    "categories": ["e3369ed7-cd34-40c3-b5f0-779fec6b7a20"],
    "pockets": ["2048357a-ecfc-4262-9b09-338111efdb2c"],
    "pocket_allocations": [],
    "transactions": [],
    "loans": [],
    "loan_installments": []
  },
  "errors": []
}
```

**Conflict Resolution:**
- Record does not exist on server -> Inserts directly.
- Record exists on server:
  - If client's `updated_at > server's updated_at` -> updates record (**Last-Write-Wins**).
  - If client's `updated_at <= server's updated_at` -> ignores client changes (server wins).
- Always sets/validates `user_id` to current authenticated user.

---

### GET `/api/sync/pull`

**Status:** ✅ Tested & Working
**Auth:** Bearer token required

**Query Params (Optional):**
- `last_synced_at`: ISO8601 timestamp string (e.g. `2026-11-13T09:00:00Z`). If omitted, returns all records of the user.

**Response (200 OK):**
```json
{
  "status": "ok",
  "data": {
    "categories": [ ... ],
    "pockets": [ ... ],
    "pocket_allocations": [ ... ],
    "transactions": [ ... ],
    "loans": [ ... ],
    "loan_installments": [ ... ]
  },
  "server_timestamp": "2026-11-13T09:14:54+00:00"
}
```


---

## Rate Limiting

**Status:** ✅ Tested & Working (Verified via `X-RateLimit-*` headers)
- **Middleware:** `throttle:api`
- **Limit:** 60 requests per minute per user (fallback to IP for public endpoints like register/login).
- **Protection:** Prevents load spikes on the 1-vCPU VPS server.
