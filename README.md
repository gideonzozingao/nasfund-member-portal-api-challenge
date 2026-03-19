# Nasfund Member Registration API

A clean Laravel REST API for registering and managing fund members.

## Prerequisites

Ensure the following are installed:

* **PHP >= 8.3**
* **Composer**
* **Laravel CLI** (optional but recommended)
* **SQLite** (default) or PostgreSQL/MySQL
* **Git**

### Required PHP Extensions:

* BCMath
* Ctype
* Fileinfo
* JSON
* Mbstring
* OpenSSL
* PDO
* Tokenizer
* XML

Verify installation:

```bash
php -m
php -v
composer -V
git --version
```

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
Domain Layer      — MemberValidator, MemberRepository, DTOs
    ↓
Infrastructure    — Database, Observers, Logger, CsvParser
```

---

## Local Setup

### 1. Clone Repository

```bash
git clone git@github.com:gideonzozingao/nasfund-member-portal-api-challenge.git
cd nasfund-member-portal-api-challenge

```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Database (SQLite - Quick Start)

```bash
touch database/database.sqlite
```

Update `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### 5. Run Migrations + Seeders

```bash
php artisan migrate:fresh --seed
```
API Token Seeder Output
Seeding database example.

Important: Copy the tokens immediately and store it safely, they cannot be recovered.

### 6. Start  the php server Server

```bash
php artisan serve --port=30325 --host=localhost
```
or  Execute the Start bash script to start the laravel on octane and frankenphp 
```bash
./start
```


API will be available at:

```
http://127.0.0.1:8000/api/v1
```

---

## Authentication (Bearer Token)

All member endpoints require a Bearer token.

### Header:

```http
Authorization: Bearer <your-token>
```

### Notes:

* Token is validated via `ApiTokenMiddleware`
* Must exist, be active, and not expired
* `last_used_at` is updated automatically

---

## API Endpoints

### POST `/api/v1/members/create`

Create a single member.

### POST `/api/v1/members/bulk-upload`

Upload CSV (row-by-row processing, partial success supported)

### GET `/api/v1/members/{memberId}`

Fetch member by ID

### GET `/api/v1/health`

Health check (no auth required)

---

## Validation Rules

| Field            | Rule                  |
| ---------------- | --------------------- |
| firstName        | Required, string      |
| lastName         | Required, string      |
| dateOfBirth      | YYYY-MM-DD, age 18–65 |
| gender           | M / F                 |
| email            | Valid, unique         |
| phone            | +675 format, unique   |
| employerName     | Required              |
| employmentStatus | Enum                  |
| taxFileNumber    | 8 digits              |

---

## Duplicate Detection

| Type | Condition        | Result        |
| ---- | ---------------- | ------------- |
| Hard | Email exists     | Reject (422)  |
| Hard | Phone exists     | Reject (422)  |
| Soft | Name + DOB match | Create + warn |

---

## Production Notes

* Use PostgreSQL/MySQL instead of SQLite
* Add queues (Redis + Horizon) for bulk processing
* Enable rate limiting per employer
* Integrate with DMP APIs
* Add monitoring (logs + metrics)

---

## Summary

* Clean layered architecture
* Token-based authentication
* Strong validation & duplicate detection
* Bulk + individual processing supported
