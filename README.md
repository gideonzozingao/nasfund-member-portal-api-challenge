
# Nasfund Member Registration API

A clean Laravel REST API for registering and managing fund members.

---

## Architecture

```
Client (Postman / Portal)
    ↓
API Layer         — MemberController
    ↓
Service Layer     — MemberService, BulkUploadService
    ↓
Action Layer      — CreateMemberAction
    ↓
Domain Layer      — MemberValidator, MemberRepository
    ↓
Infrastructure    — Database (SQLite / PostgreSQL), Logger, CsvParser
```

---

## Setup

```bash
git clone git@github.com:gideonzozingao/nasfund-member-portal-api-challenge.git
cd nasfund-member-portal-api-challenge
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

---

## API Endpoints

### POST `/api/v1/members/create`

Create a single member.

**Request body (JSON):**
```json
{
  "firstName":        "Gideon",
  "lastName":         "Zozingao",
  "dateOfBirth":      "1995-05-10",
  "gender":           "M",
  "email":            "gideon@test.com",
  "phone":            "+675 71234567",
  "employerName":     "Nasfund",
  "employmentStatus": "Active",
  "taxFileNumber":    "12345678"
}
```

**Success (201):**
```json
{
  "status": "success",
  "message": "Member created successfully.",
  "data": { "member_id": "M000000001", ... },
  "errors": null
}
```

**Warning – soft duplicate (201):**
```json
{
  "status": "warning",
  "message": "Member created but a potential duplicate was detected...",
  "data": { ... },
  "errors": null
}
```

**Error – hard duplicate / validation (422):**
```json
{
  "status": "error",
  "message": "A member with this email already exists.",
  "data": null,
  "errors": { "duplicate": ["..."] }
}
```

---

### POST `/api/v1/members/bulk-upload`

Upload a CSV file. Processing is row-by-row — partial success is supported.

**Request:** `multipart/form-data`, field `file` (CSV, max 10 MB)

**CSV format:**
```
firstName,lastName,dateOfBirth,gender,email,phone,employerName,employmentStatus,taxFileNumber
Gideon,Zozingao,1995-05-10,M,gideon@test.com,+675 71234567,Nasfund,Active,12345678
```

**Response (200):**
```json
{
  "status":  "partial",
  "summary": { "total": 4, "success": 2, "warnings": 1, "failed": 1 },
  "results": [
    { "row": 2, "status": "success",  "message": "...", "data": { "member_id": "M000000001" } },
    { "row": 3, "status": "warning",  "message": "Potential duplicate...", "data": {...} },
    { "row": 4, "status": "error",    "message": "Age must be 18–65",  "errors": {...} },
    { "row": 5, "status": "error",    "message": "Phone already exists", "errors": {...} }
  ]
}
```

---

### GET `/api/v1/members/{memberId}`

Retrieve a member by their ID (e.g. `M000000001`).

---

### GET `/api/v1/health`

Health check.

```json
{ "status": "ok", "timestamp": "2024-01-01T00:00:00+00:00", "version": "1.0.0" }
```

---

## Validation Rules

| Field              | Rule                                   |
| ------------------ | -------------------------------------- |
| `firstName`        | Required, string, max 100              |
| `lastName`         | Required, string, max 100              |
| `dateOfBirth`      | Required, date (YYYY-MM-DD), age 18–65 |
| `gender`           | Required, enum: M / F / Other          |
| `email`            | Required, valid email, unique          |
| `phone`            | Required, PNG format (`+675 XXXXXXXX`) |
| `employerName`     | Required, string                       |
| `employmentStatus` | Required, enum                         |
| `taxFileNumber`    | Required, exactly 8 digits             |

---

## Duplicate Detection

| Type | Condition            | Result              |
| ---- | -------------------- | ------------------- |
| Hard | Email already exists | Reject (422)        |
| Hard | Phone already exists | Reject (422)        |
| Soft | Same name + DOB      | Create + warn (201) |

---

## Running Tests

```bash
php artisan test
# or
./vendor/bin/phpunit
```

---

## Production Considerations

- Replace SQLite with **PostgreSQL** via `DB_CONNECTION=pgsql` in `.env`
- Offload large CSV uploads to a **queue** (Laravel Horizon + Redis)
- Add **API token auth** via `Sanctum` middleware on member routes
- Add **rate limiting**: `throttle:60,1` per minute per IP
- Instrument with **Prometheus + Grafana** via `spatie/laravel-prometheus`
