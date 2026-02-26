# API Comparison Scripts

## compare_api.php

Fetches each endpoint from two base URLs and reports **response shape differences** (missing keys, type mismatches). It does not compare values — only the structure of the JSON.

### Requirements

- PHP CLI with `curl` extension enabled
- Valid auth tokens for both environments

---

## Endpoint files

| File | Auth type | Covers |
|------|-----------|--------|
| `endpoints.json` | JWT (`auth:api`) | Admin panel CRUD + reports |
| `endpoints-app.json` | APIToken (double-base64) | Resident dining app endpoints |

Both files share the same format — you run `compare_api.php` twice, once per file.

---

## Usage

### Admin endpoints (JWT)

```bash
php scripts/compare_api.php \
  --base-a=https://hamiltondinnerapp.redesign.intelligrp.com/api \
  --base-b=https://hamiltondinnerapp.staging.intelligrp.com/api \
  --endpoints=scripts/endpoints.json \
  --token="Bearer <your_jwt_token>"
```

Obtain a JWT by hitting `POST /api/admin/login` with valid admin credentials.

### Resident app endpoints (APIToken)

```bash
php scripts/compare_api.php \
  --base-a=https://hamiltondinnerapp.redesign.intelligrp.com/api \
  --base-b=https://hamiltondinnerapp.staging.intelligrp.com/api \
  --endpoints=scripts/endpoints-app.json \
  --token="Bearer <encoded_api_token>"
```

The APIToken value is a double-base64-encoded JSON blob produced by the resident login flow (`POST /api/login`).

---

## Customising sample data

Both endpoint files use placeholder values that you should replace with real IDs and dates from your data:

| Placeholder | Default | Replace with |
|-------------|---------|--------------|
| Resource IDs (paths like `/admin/menus/1`) | `1` | An ID that exists in **both** environments |
| `SAMPLE_DATE` in bodies/queries | `2025-11-01` | A date with order data in both environments |
| `SAMPLE_DATE_END` | `2025-11-30` | End of a range with data in both environments |
| `room_id` in app endpoints | `1` | A valid room ID in both environments |

---

## Reading the output

```
=== Menus: list (paginated) (GET /admin/menus) ===
A: 200 | B: 200
Shapes match.

=== Menus: show (GET /admin/menus/1) ===
A: 200 | B: 404
Missing in A:
  - $.error
Missing in B:
  - $.data.menu_name
```

- **Shapes match** — identical JSON structure, safe to deploy
- **Missing in A/B** — one environment returns a key the other doesn't
- **Type mismatches** — same key, different JSON type (e.g. `string` vs `integer`)

---

## Options reference

| Flag | Default | Description |
|------|---------|-------------|
| `--base-a` | _(required)_ | First API base URL |
| `--base-b` | _(required)_ | Second API base URL |
| `--endpoints` | `scripts/endpoints.json` | Path to endpoint config file |
| `--token` | _(none)_ | Sent as the global `Authorization` header |
| `--header` | _(none)_ | Additional header(s), repeatable |
| `--timeout` | `20` | Per-request timeout in seconds |
