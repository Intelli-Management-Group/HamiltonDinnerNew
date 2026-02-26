#!/usr/bin/env python3
"""
Deep value comparison between two API environments.

Usage:
  python3 scripts/value_compare_api.py \
    --base-a=http://127.0.0.1:8000/api \
    --base-b=https://hamiltondinnerapp.staging.intelligrp.com/api \
    --endpoints=scripts/endpoints.json \
    --token="Bearer <jwt>"

Options:
  --max-diffs=N     Maximum differences to show per endpoint (default: 20)
  --skip-keys=a,b   Comma-separated JSON keys to skip in comparison (e.g. 'updated_at,created_at')
  --skip-url-host   Normalize http(s)://host/ in string values before comparing (default: on)
"""

import json
import sys
import argparse
import re
import urllib.request
import urllib.parse
import ssl
from typing import Any

def normalize(value: Any, skip_url_host: bool) -> Any:
    if isinstance(value, str) and skip_url_host:
        return re.sub(r'https?://[^/]+', '<HOST>', value)
    return value

def deep_diff(a: Any, b: Any, path: str, skip_keys: set, skip_url_host: bool, diffs: list, max_diffs: int) -> None:
    if len(diffs) >= max_diffs:
        return

    if isinstance(a, dict) and isinstance(b, dict):
        all_keys = sorted(set(a.keys()) | set(b.keys()))
        for k in all_keys:
            if k in skip_keys:
                continue
            p = f"{path}.{k}"
            if k not in a:
                diffs.append(f"{p}: missing in A, B={repr(b[k])[:60]}")
            elif k not in b:
                diffs.append(f"{p}: A={repr(a[k])[:60]}, missing in B")
            else:
                deep_diff(a[k], b[k], p, skip_keys, skip_url_host, diffs, max_diffs)
            if len(diffs) >= max_diffs:
                return
    elif isinstance(a, list) and isinstance(b, list):
        if len(a) != len(b):
            diffs.append(f"{path}: array len {len(a)} vs {len(b)}")
        limit = min(len(a), len(b), 5)  # compare first 5 elements to keep output focused
        for i in range(limit):
            deep_diff(a[i], b[i], f"{path}[{i}]", skip_keys, skip_url_host, diffs, max_diffs)
            if len(diffs) >= max_diffs:
                return
    else:
        na = normalize(a, skip_url_host)
        nb = normalize(b, skip_url_host)
        if na != nb:
            diffs.append(f"{path}: {repr(a)[:80]} vs {repr(b)[:80]}")

def fetch(method: str, url: str, headers: dict, body: Any, ssl_ctx) -> tuple[int, Any]:
    data = None
    if body is not None:
        data = json.dumps(body).encode('utf-8')
        headers['Content-Type'] = 'application/json'

    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, context=ssl_ctx, timeout=30) as resp:
            raw = resp.read().decode('utf-8')
            return resp.status, json.loads(raw)
    except urllib.error.HTTPError as e:
        raw = e.read().decode('utf-8')
        try:
            return e.code, json.loads(raw)
        except Exception:
            return e.code, {'_raw': raw[:200]}
    except Exception as e:
        return 0, {'_error': str(e)}

def build_url(base: str, path: str, query: dict | None) -> str:
    url = base.rstrip('/') + '/' + path.lstrip('/')
    if query:
        url += '?' + urllib.parse.urlencode(query)
    return url

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--base-a', required=True)
    parser.add_argument('--base-b', required=True)
    parser.add_argument('--endpoints', required=True)
    parser.add_argument('--token', default=None)
    parser.add_argument('--max-diffs', type=int, default=20)
    parser.add_argument('--skip-keys', default='created_at,updated_at,deleted_at,last_activity_at')
    parser.add_argument('--no-skip-url-host', action='store_true')
    args = parser.parse_args()

    with open(args.endpoints) as f:
        config = json.load(f)

    endpoints = config.get('endpoints', config if isinstance(config, list) else [])

    skip_keys = set(k.strip() for k in args.skip_keys.split(',') if k.strip())
    skip_url_host = not args.no_skip_url_host

    # SSL context that skips certificate verification (for staging self-signed certs)
    ssl_ctx = ssl.create_default_context()
    ssl_ctx.check_hostname = False
    ssl_ctx.verify_mode = ssl.CERT_NONE

    base_headers: dict = {'Accept': 'application/json'}
    if args.token:
        base_headers['Authorization'] = args.token

    for ep in endpoints:
        name = ep.get('name', ep.get('path', 'unknown'))
        method = ep.get('method', 'GET').upper()
        path = ep.get('path', '/')
        query = ep.get('query')
        body = ep.get('body')
        expect = ep.get('expectStatus')

        url_a = build_url(args.base_a, path, query)
        url_b = build_url(args.base_b, path, query)

        status_a, data_a = fetch(method, url_a, dict(base_headers), body, ssl_ctx)
        status_b, data_b = fetch(method, url_b, dict(base_headers), body, ssl_ctx)

        print(f"\n=== {name} ({method} {path}) ===")
        print(f"A: {status_a} | B: {status_b}")

        if expect is not None:
            ok_a = '✓' if status_a == expect else f'✗ expected {expect}'
            ok_b = '✓' if status_b == expect else f'✗ expected {expect}'
            print(f"Status: A={ok_a}, B={ok_b}")

        if status_a != status_b:
            print("⚠ Status codes differ — skipping value comparison")
            continue

        if '_error' in data_a or '_error' in data_b:
            print("Request error — skipping value comparison")
            continue

        diffs: list = []
        deep_diff(data_a, data_b, '$', skip_keys, skip_url_host, diffs, args.max_diffs)

        if not diffs:
            print("Values match.")
        else:
            print(f"Value differences ({len(diffs)} shown, max {args.max_diffs}):")
            for d in diffs:
                print(f"  {d}")
            if len(diffs) >= args.max_diffs:
                print(f"  ... (limit reached — use --max-diffs to see more)")

if __name__ == '__main__':
    main()
