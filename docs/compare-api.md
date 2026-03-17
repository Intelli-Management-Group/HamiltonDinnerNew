# API Response Comparator

This utility compares response **shapes** (keys + types) for the same endpoints between two branches/environments.

## What it does

- Sends the same request to two base URLs.
- Compares status codes.
- Compares JSON response shapes (paths + types).
- Reports missing keys and type mismatches.

## Setup

1. Copy the sample config and edit it for your endpoints:
   - `scripts/endpoints.sample.json` → `scripts/endpoints.json`
2. Run two servers:
   - One for your main branch (e.g. `http://localhost:8000`)
   - One for your refactor branch (e.g. `http://localhost:8001`)

## Run

```bash
php scripts/compare_api.php \
  --base-a=http://localhost:8000 \
  --base-b=http://localhost:8001 \
  --endpoints=scripts/endpoints.json \
  --token="Bearer <token>"
```

### Optional flags

- `--header="Header: Value"` (repeatable)
- `--timeout=30`

## Notes

- The comparator uses JSON shapes, not value-by-value comparison.
- If a response isn’t valid JSON, the script reports a parse error for that endpoint.
