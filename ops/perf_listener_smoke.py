from __future__ import annotations

import json
import os
import statistics
import sys
import time
from pathlib import Path

from fastapi.testclient import TestClient

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "src"
if str(SRC) not in sys.path:
    sys.path.insert(0, str(SRC))

# Set env before importing app/settings.
os.environ.setdefault("DATABASE_URL", "sqlite:///./data/perf_smoke.db")
os.environ.setdefault("LISTENER_SHARED_SECRET", "perf-secret")
os.environ.setdefault("LISTENER_ALLOW_LEGACY_SECRET", "false")
os.environ.setdefault("LISTENER_REQUIRE_REFERENCE", "false")
os.environ.setdefault("BOT_TOKEN", "")

from app.api.main import app  # noqa: E402
from app.api.security import build_signature  # noqa: E402
from app.db.bootstrap import init_db  # noqa: E402


def _percentile(values: list[float], pct: int) -> float:
    if not values:
        return 0.0
    ordered = sorted(values)
    if len(ordered) == 1:
        return ordered[0]
    safe_pct = min(100, max(1, int(pct)))
    rank = int(round((safe_pct / 100.0) * (len(ordered) - 1)))
    return ordered[rank]


def _headers(secret: str, body: bytes, key: str) -> dict[str, str]:
    ts = str(int(time.time()))
    sig = build_signature(secret, ts, body)
    return {
        "Content-Type": "application/json",
        "X-Timestamp": ts,
        "X-Signature": sig,
        "X-Idempotency-Key": key,
    }


def main() -> int:
    secret = os.getenv("LISTENER_SHARED_SECRET", "perf-secret")
    requests_total = max(10, int(os.getenv("PERF_SMOKE_REQUESTS", "200")))
    warmup_total = max(5, int(os.getenv("PERF_SMOKE_WARMUP", "20")))
    p95_threshold_ms = max(50.0, float(os.getenv("PERF_SMOKE_P95_MS", "400")))

    init_db()
    client = TestClient(app)

    def run_once(idx: int) -> tuple[int, float]:
        payload = {
            "amount": 12345,
            "source_app": "PERF_SMOKE",
            "reference": f"PERF-REF-{idx}",
            "raw_text": "perf smoke request",
            "metadata": {"kind": "perf_smoke", "idx": idx},
        }
        body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
        started = time.perf_counter()
        response = client.post(
            "/listener/payment",
            content=body,
            headers=_headers(secret, body, f"perf-smoke-{idx}"),
        )
        elapsed = (time.perf_counter() - started) * 1000.0
        return response.status_code, elapsed

    for i in range(warmup_total):
        run_once(i)

    latencies: list[float] = []
    success = 0
    for i in range(warmup_total, warmup_total + requests_total):
        status_code, elapsed = run_once(i)
        latencies.append(elapsed)
        if status_code == 200:
            success += 1

    if not latencies:
        print("FAIL: tidak ada data latency")
        return 1

    p50 = _percentile(latencies, 50)
    p95 = _percentile(latencies, 95)
    p99 = _percentile(latencies, 99)
    avg = statistics.fmean(latencies)
    ok = p95 <= p95_threshold_ms

    print("[perf_listener_smoke] requests:", requests_total)
    print("[perf_listener_smoke] success_200:", success)
    print("[perf_listener_smoke] avg_ms:", f"{avg:.2f}")
    print("[perf_listener_smoke] p50_ms:", f"{p50:.2f}")
    print("[perf_listener_smoke] p95_ms:", f"{p95:.2f}")
    print("[perf_listener_smoke] p99_ms:", f"{p99:.2f}")
    print("[perf_listener_smoke] threshold_p95_ms:", f"{p95_threshold_ms:.2f}")

    if ok:
        print("PASS: p95 latency berada di bawah threshold.")
        return 0

    print("FAIL: p95 latency melebihi threshold.")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
