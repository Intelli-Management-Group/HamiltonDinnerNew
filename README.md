# Hamilton Dinner App

A Laravel 11 REST API for meal ordering and resident management at **Hamilton High Street Senior Residence**. Residents order meals from their rooms via a mobile/web client; administrators manage menus, items, rooms, users, forms, and reports.

---

## Table of Contents

1. [Local Setup](#local-setup)
2. [Architecture](#architecture)
3. [Authentication](#authentication)
4. [API Overview](#api-overview)
5. [Response Formats](#response-formats)
6. [Testing](#testing)
7. [How to Extend the Codebase](#how-to-extend-the-codebase)
8. [What NOT to Do](#what-not-to-do)

---

## Local Setup

### Dependencies

| Tool | Version | Notes |
|------|---------|-------|
| PHP | ≥ 8.2 | Required by Laravel 11 |
| Composer | any | `brew install composer` on Mac |
| XAMPP | ≥ 8.2 | Bundles Apache + MySQL for local development |

### Steps

**1. Start XAMPP services**

Open XAMPP Control Panel and start both **MySQL** and **Apache**.

**2. Create the database and user**

Connect to MySQL as root and run:

```sql
CREATE DATABASE hamilton_dinner_app_staging
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

CREATE USER 'hamilton_dinner_app'@'localhost' IDENTIFIED BY 'hamilton_dinner_app';

GRANT ALL PRIVILEGES ON hamilton_dinner_app_staging.* TO 'hamilton_dinner_app'@'localhost';
FLUSH PRIVILEGES;

-- Verify:
SHOW GRANTS FOR 'hamilton_dinner_app'@'localhost';
```

**3. Seed the database**

Connect as the `hamilton_dinner_app` user to `hamilton_dinner_app_staging` and run the provided SQL seeding script.

> **Known issue:** The seed data contains foreign key constraint violations. If you hit them, disable FK checks first:
> ```sql
> SET FOREIGN_KEY_CHECKS = 0;
> ```

**4. Install PHP dependencies**

```bash
composer install
```

**5. Configure your environment**

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your database connection:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hamilton_dinner_app_staging
DB_USERNAME=hamilton_dinner_app
DB_PASSWORD=hamilton_dinner_app
```

You also need a JWT secret. Generate one with:

```bash
php artisan jwt:secret
```

**6. Run migrations** (only if starting from a fresh DB, not from the seeding script)

```bash
php artisan migrate
```

**7. Start the server**

```bash
php artisan serve
```

The API is now available at `http://127.0.0.1:8000/api`.

**8. Verify**

Send a `POST` to `http://127.0.0.1:8000/api/login` with:

```json
{
  "room_no": "101",
  "password": "wong"
}
```

Headers: `Accept: application/json`, `Content-Type: application/json`

Expect a `200` response with a `ResponseCode` of `"1"`.

---

## Architecture

The codebase follows a strict four-layer pattern:

```
HTTP Request
     ↓
Controller  — validates input, delegates to service, returns HTTP response
     ↓
Service     — business logic; orchestrates repositories and produces data
     ↓
Repository  — database access; one repository per model
     ↓
Model       — Eloquent model, relationships, casts, soft-deletes
```

### Directory Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── Admin/           # Admin panel controllers (JWT-protected)
│   │   │   └── DinningController.php  # Legacy resident-app controller (being broken up)
│   │   └── ...
│   └── Middleware/
│       ├── APIToken.php         # Resident auth middleware
│       └── JwtMiddleware.php    # Admin JWT middleware
├── Models/                      # Eloquent models
├── Repositories/
│   ├── Contracts/               # Interfaces (BaseRepositoryInterface + per-model interfaces)
│   └── Eloquent/                # Concrete implementations
│       ├── BaseRepository.php   # Shared CRUD, filtering, pagination, soft-delete
│       ├── Forms/
│       └── Reports/
├── Services/
│   ├── BaseService.php          # Shared response helpers (successResponse, errorResponse, etc.)
│   ├── DiningAppService.php     # Core resident dining logic (large; being refactored)
│   ├── Forms/
│   └── Reports/
├── Providers/
│   └── AppServiceProvider.php   # Binds all interfaces → concrete repository classes
└── Support/
    └── ApiResponse.php          # Flat response formatter for legacy dining/forms routes
```

### Dependency Injection

All repository interfaces are bound to their concrete implementations in `AppServiceProvider::register()`. Controllers and services declare their dependencies via constructor injection — Laravel's container resolves the concrete class automatically.

**When you add a new repository**, you must add a binding in `AppServiceProvider::register()`:

```php
$this->app->bind(MyNewRepositoryInterface::class, MyNewRepository::class);
```

### BaseRepository

`BaseRepository` (abstract) provides the standard CRUD surface every repository inherits:

| Method | Description |
|--------|-------------|
| `findById($id, $filters, $relations)` | Find a single record by PK |
| `getAll($filters, $relations, $columns)` | Fetch all matching records |
| `paginate($filters, $relations, $perPage, $page)` | Paginated fetch |
| `query($filters, $relations)` | Return a Builder (for custom queries) |
| `create($data)` | Insert a new record |
| `update($id, $data)` | Fill and save by ID |
| `save($model)` | Save an already-hydrated model |
| `delete($model)` | Delete (or soft-delete) a model |
| `bulkDeleteByIds($ids)` | Mass delete by array of IDs |
| `upsertByFilters($filters, $data)` | Update-or-create |

The `query()` method chains four internal hooks in order:

1. `applyRelations()` — eager-loads requested relations
2. `applyFilters()` — **implemented by each concrete repository** — applies WHERE clauses
3. `applyDeletedAtFilter()` — handled by the base class; checks the `deleted_at` filter key
4. `applyOrdering()` — applies ordering (handled by the base class or overridden)

> **Critical rule:** `applyFilters()` is where filter keys become WHERE clauses. If a key is not handled there, it is silently ignored and all records are returned. Every time you add a new filterable field, handle it in `applyFilters()` and add a regression test.

---

## Authentication

There are two completely separate auth systems:

### 1. Resident Auth (`APIToken` middleware)

Used by the mobile/web ordering app. The token is a doubly-base64-encoded JSON blob:

```
Authorization: Bearer <base64(base64(json_encode({user_id, timestamp, role})))>
```

- `role` can be `"user"` (resident), `"admin"`, or `"kitchen"`
- For `"user"` role: `user_id` is the `room_details.id`
- For `"admin"` / `"kitchen"` roles: `user_id` is the `users.id`
- On success the middleware stores the resolved record in the session as `user_details`

Routes protected by this middleware are grouped under `Route::group(['middleware' => 'APIToken'], ...)` in `routes/api.php`.

### 2. Admin Auth (JWT, `auth:api` guard)

Used by the admin panel. Powered by Tymon JWT-Auth v2.2.

- Login: `POST /api/admin/login` — returns a JWT
- All admin routes are under `Route::group(['prefix' => 'admin', 'middleware' => 'auth:api'], ...)`
- JWT secret is stored in `.env` as `JWT_SECRET`

---

## API Overview

### Resident / Dining App routes (`APIToken`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/login` | Room login; returns token |
| POST | `/api/order-list` | Get menu + order status for a date |
| POST | `/api/update-order` | Place / update a single order |
| POST | `/api/multi-order-update` | Bulk-update orders across multiple dates |
| POST | `/api/get-user-data` | Get resident profile data |
| GET | `/api/{room_id}/get-room-details` | Room details |
| POST | `/api/{room_id}/update-room-details` | Update room details |
| POST | `/api/get-category-specific-items` | Items under a specific category |
| GET | `/api/get-move-in-summary-values` | Move-in summary data |
| GET | `/api/reports` | Order report list |
| POST | `/api/get-report-data` | Category-wise report data |
| POST | `/api/get-charges-report` | Charge report |
| POST | `/api/temp-get-charges-report` | Charge report v2 |
| POST | `/api/print-combined-order-data` | Print-ready combined order data |

### Forms App routes (`APIToken`)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/list-forms` | List form responses by `form_type_id` |
| POST | `/api/form-details` | Get a single form response with attachments |
| POST | `/api/delete-form` | Hard-delete a form response |
| POST | `/api/complete-log` | Mark inspection checklist as complete (generates PDF) |
| POST | `/api/general-form-submit-phase1` | Create new form response (with file upload) |
| POST | `/api/edit-form-phase1` | Edit existing form response (with file upload) |
| POST | `/api/add-form-attachment-phase1` | Attach files to an existing form |
| POST | `/api/delete-form-attachment-phase1` | Soft-delete an attachment |

### Admin routes (`auth:api` JWT)

All admin routes are prefixed `/api/admin/`.

| Resource | Prefix | Supported operations |
|----------|--------|---------------------|
| Menus | `/api/admin/menus` | index, store, show, update, destroy, bulk-delete |
| Categories | `/api/admin/categories` | index, store, show, update, destroy, bulk-delete |
| Item Options | `/api/admin/item-options` | index, store, show, update, destroy, bulk-delete |
| Item Details | `/api/admin/item-details` | index, store, show, update, destroy, bulk-delete |
| Item Preferences | `/api/admin/item-preferences` | index, store, show, update, destroy, bulk-delete |
| Rooms | `/api/admin/rooms` | index, store, show, update, destroy, bulk-delete |
| Roles | `/api/admin/roles` | index, store, show, update, destroy, bulk-delete |
| Permissions | `/api/admin/permissions` | index (read-only for now) |
| Users | `/api/admin/users` | index, store, show, update, destroy, bulk-delete |
| Settings | `/api/admin/settings` | index, store, show, update, bulk-upsert, destroy |
| Form Types | `/api/admin/form-types` | index, store, show, update, destroy, bulk-delete |
| Auth | `/api/admin/login`, `/api/admin/logout`, `/api/admin/me` | JWT lifecycle |

---

## Response Formats

There are **two different response formats** in use, depending on which part of the codebase a route belongs to.

### Format A — Admin panel routes (via `BaseService`)

Used by all admin CRUD controllers. The controller calls a service method, which returns a structured array, and the controller returns it as JSON:

```json
{
  "statusCode": 200,
  "payload": {
    "success": true,
    "data": { ... },
    "message": "Fetched successfully"
  }
}
```

Error example:

```json
{
  "statusCode": 404,
  "payload": {
    "success": false,
    "message": "Not found",
    "data": null
  }
}
```

For paginated responses, a `pagination` key (or `meta` key for `SettingService`) is added alongside `data`:

```json
{
  "statusCode": 200,
  "payload": {
    "success": true,
    "data": [ ... ],
    "pagination": {
      "total": 50, "per_page": 15, "current_page": 1, "last_page": 4, ...
    }
  }
}
```

### Format B — Dining/Forms App routes (via `ApiResponse::format()`)

Used by `DinningController` and the dining/forms service layer. The service calls `ApiResponse::format()` and returns a `JsonResponse` directly:

```json
{
  "ResponseCode": "1",
  "ResponseText": "Fetched Order List Successfully",
  "data_key": { ... }
}
```

- `ResponseCode: "1"` = success, `"0"` = application error, `"11"` = unauthorised
- Additional data keys are merged directly into the top-level object

---

## Testing

### Running the test suite

```bash
php artisan test
```

Or to run a specific suite:

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

Or a specific file:

```bash
php artisan test tests/Feature/DiningApp/OrderListTest.php
```

### Test environment

Tests use SQLite in-memory (`:memory:`) for isolation. Configuration lives in `.env.testing`. The `RefreshDatabase` trait wipes and re-runs all migrations before each test class.

You should **not** need to touch the real MySQL database to run tests.

### Test structure

```
tests/
├── TestCase.php                        # Base; calls Mockery::close() in tearDown()
├── Unit/
│   ├── Models/                         # Model attribute, cast, relation tests
│   ├── Repositories/                   # Repository filter/CRUD tests (use RefreshDatabase)
│   └── Services/                       # Service logic tests (use Mockery mocks)
└── Feature/
    ├── DiningApp/
    │   ├── DiningAppTestCase.php        # Shared base: token helpers, model factories, settings seed
    │   ├── LoginTest.php
    │   ├── OrderListTest.php
    │   ├── UpdateOrderTest.php
    │   ├── GetUserDataTest.php
    │   ├── RoomDetailsTest.php
    │   ├── CategoryItemsTest.php
    │   └── MoveInSummaryTest.php
    └── FormsApp/
        ├── FormsAppTestCase.php         # Extends DiningAppTestCase; adds form factories
        ├── ListFormsTest.php
        ├── FormDetailsTest.php
        ├── DeleteFormTest.php
        ├── CompleteLogTest.php
        └── SaveFormTest.php
```

### Testing conventions

**Unit (Service) tests** — use Mockery to mock all repositories. No database.

```php
$this->menuRepo = Mockery::mock(MenuDetailRepositoryInterface::class);
$this->menuRepo->shouldReceive('getAll')
    ->with(['date' => '2026-01-01'])
    ->once()
    ->andReturn(new Collection([...]));
```

**Unit (Repository) tests** — use `RefreshDatabase` and real SQLite. Test that filters produce the correct SQL.

**Feature tests** — full HTTP tests. Use `DiningAppTestCase` or `FormsAppTestCase` as the base class. These provide:
- `makeRoom()`, `makeAdminUser()`, `makeResidentRoom()`, `makeCategory()`, `makeItem()`, `makeMenu()` helpers
- `residentHeaders($room)` / `adminHeaders($user)` to generate valid `Authorization` headers
- `seedSettings()` to populate the settings rows that `DiningAppService` requires

A typical feature test:

```php
public function test_order_list_returns_items_for_date(): void
{
    $this->seedSettings();
    $room  = $this->makeResidentRoom();
    $cat   = $this->makeCategory(['type' => 1]);
    $item  = $this->makeItem($cat, ['item_name' => 'Scrambled Eggs']);
    $this->makeMenu('2026-01-01', [$item->id]);

    $response = $this->withHeaders($this->residentHeaders($room))
        ->postJson('/api/order-list', ['type' => 1, 'room_id' => $room->id, 'date' => '2026-01-01']);

    $response->assertStatus(200)
             ->assertJsonPath('ResponseCode', '1');
}
```

### Use `#[Test]` not `/** @test */`

PHPUnit 11 marks the `@test` docblock as deprecated. Use the attribute instead:

```php
#[Test]
public function it_does_something(): void { ... }
```

### File upload endpoints cannot be fully tested via feature tests

`saveFormPhase1` and `editGeneratedFormResponsePhase1` read `$_FILES` directly (not `$request->file()`). Laravel's HTTP test infrastructure populates `$request->file()` but never `$_FILES`. Only validation-failure paths can be tested for those two endpoints through automated tests.

---

## How to Extend the Codebase

Follow this checklist when adding a new resource or API endpoint.

### Adding a new admin CRUD resource

**1. Create the migration**
```bash
php artisan make:migration create_my_things_table
```

**2. Create the model**

```bash
php artisan make:model MyThing
```

Use `SoftDeletes` unless you have a strong reason not to.

**3. Create the repository interface**

In `app/Repositories/Contracts/MyThingRepositoryInterface.php`, extend `BaseRepositoryInterface`. Declare any additional methods specific to this model (e.g. custom finders).

**4. Create the Eloquent repository**

In `app/Repositories/Eloquent/MyThingRepository.php`, extend `BaseRepository` and implement `MyThingRepositoryInterface`.

Implement `applyFilters()` to handle every filter key the service will pass:

```php
protected function applyFilters(Builder $query, array $filters): Builder
{
    if (!empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }
    // Add more filter keys here as needed
    return $query->latest();
}
```

> Never leave `applyFilters()` empty or returning early. An empty implementation silently ignores all filters and returns the entire table.

**5. Bind the interface in `AppServiceProvider`**

```php
$this->app->bind(MyThingRepositoryInterface::class, MyThingRepository::class);
```

**6. Create the service**

In `app/Services/MyThingService.php`, extend `BaseService`. Inject the repository interface via the constructor. Write one method per business operation, returning `successResponse()` / `errorResponse()` / `paginatedResponse()`.

**7. Create the controller**

In `app/Http/Controllers/Api/Admin/MyThingController.php`, extend `App\Http\Controllers\Api\Admin\Controller`. Inject the service. Each method should:
1. Validate the request
2. Call the service
3. Return `response()->json($result['payload'], $result['statusCode'])`

**8. Register the routes**

Add a prefixed resource group in `routes/api.php` inside the `auth:api` middleware group:

```php
Route::prefix('my-things')->group(function () {
    Route::get('/',              [MyThingController::class, 'index']);
    Route::post('/',             [MyThingController::class, 'store']);
    Route::get('/{id}',          [MyThingController::class, 'show']);
    Route::put('/{id}',          [MyThingController::class, 'update']);
    Route::delete('/bulk-delete',[MyThingController::class, 'bulkDestroy']);
    Route::delete('/{id}',       [MyThingController::class, 'destroy']);
});
```

**9. Write tests**

- Repository test: one test per filter key, asserting correct records are returned
- Service test: mock the repository; assert the correct service methods and response shapes
- Feature test: at least one happy-path test and one auth-failure test per endpoint

---

## What NOT to Do

These are patterns that have caused real bugs or accumulated tech debt in this codebase. Avoid them.

### Do not leave `applyFilters()` empty

```php
// WRONG — silently returns the entire table regardless of filters passed
protected function applyFilters(Builder $query, array $filters): Builder
{
    return $query->latest();
}
```

Every time a caller passes a filter key that is not handled in `applyFilters()`, the filter is silently dropped and all rows are returned. This has caused data leakage bugs (e.g. order-list returning items from all dates instead of just the requested date). **Always handle every filter key you intend to support, and add a test for each one.**

### Do not call delete() on a null model

Repositories type-hint `delete(Model $model)`. If the model is not found, passing `null` throws a `TypeError` that bypasses `catch (\Exception $e)` blocks. Always null-check before deleting:

```php
$record = $this->repo->findById($id);
if (!$record) {
    return $this->errorResponse('Not found', 404);
}
$this->repo->delete($record);
```

### Do not add new logic to `DinningController.php`

`DinningController` is a 166KB legacy file. The ongoing refactor moves logic out of it into clean Service/Repository classes. Do not add new business logic there. New endpoints should follow the full layered pattern from the start.

### Do not bypass the repository for DB queries in services

Services must access the database exclusively through repositories. Do not use `DB::table()`, raw Eloquent model calls (`MyModel::where(...)`), or raw SQL (`DB::select(...)`) in a service. The only exception is `ChargeReportRepository`, which uses raw SQL for a complex report and is intentionally isolated there.

### Do not use `$_FILES` for new file upload endpoints

New upload endpoints should use `$request->file()` (Laravel's abstraction), not `$_FILES`. Using `$_FILES` makes the endpoint impossible to test via feature tests and is not idiomatic Laravel.

### Do not hardcode `form_type_id == 2` for business logic

`completeFormLog` in `FormsAppService` has a hardcoded check `if ($form->form_type_id == 2)` for the Inspection Checklist. This is a known tech-debt item. Do not introduce additional hardcoded type ID checks — use a named constant, a column on the `form_types` table, or a polymorphic approach instead.

### Do not mix response formats

Admin routes use `BaseService` response format. Dining/Forms app routes use `ApiResponse::format()`. Do not mix them — each consumer has a different client expecting a specific shape.

### Do not use `/** @test */` docblocks in new tests

PHPUnit 11 deprecates the `@test` annotation. Use `#[Test]` attribute on new test methods.

### Do not store secrets in `.env.testing`

The `.env.testing` file currently contains real credentials (mail password, JWT secret). This file should not be committed with real credentials. Use placeholder values and document the real values in a secrets manager.
