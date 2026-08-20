#!/usr/bin/env bash
# Lightweight, dependency-free smoke test for scripts/valet-watch.sh
# (KOZ-12, rework round 3).
#
# This does NOT start a real filesystem watcher (fswatch would then run
# forever, watching indefinitely) and does NOT touch the host's real Valet
# installation. Instead it stubs `fswatch` and `make` on PATH so it can
# verify valet-watch.sh's *invocation behaviour* — that on a queue-dir
# change it calls `make valet-sync` for the right worktree — without any
# real watching or `valet proxy` call happening.
#
# Usage:
#   scripts/valet-watch.test.sh
#
# Exits 0 and prints "PASS" on success, exits 1 and prints what went wrong
# otherwise. Not wired into `make test-backend` (that's PHPUnit-only) or
# any CI step — run manually when touching scripts/valet-watch.sh.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

STUB_BIN="$WORKDIR/bin"
CALL_LOG="$WORKDIR/make-calls.log"
mkdir -p "$STUB_BIN"
: > "$CALL_LOG"

# --- Stub `fswatch`: instead of watching forever, immediately emit a single
# batch-change event (mimicking one real file-change notification) and
# exit. This lets the `xargs -n1` pipeline in valet-watch.sh run exactly
# once and then terminate, instead of hanging.
cat > "$STUB_BIN/fswatch" <<'EOF'
#!/usr/bin/env bash
# Test stub: ignore all arguments, emit one fake event, exit.
echo "1"
EOF
chmod +x "$STUB_BIN/fswatch"

# --- Stub `make`: record how it was invoked instead of really running
# anything (in particular, instead of really calling valet-sync.sh, which
# would try to run the real `valet` CLI).
cat > "$STUB_BIN/make" <<EOF
#!/usr/bin/env bash
echo "make \$*" >> "$CALL_LOG"
EOF
chmod +x "$STUB_BIN/make"

# Run valet-watch.sh with the stubs first on PATH.
PATH="$STUB_BIN:$PATH" "$SCRIPT_DIR/valet-watch.sh"

if [ ! -s "$CALL_LOG" ]; then
  echo "FAIL: valet-watch.sh never invoked 'make' via the stubbed fswatch event." >&2
  exit 1
fi

if ! grep -q -- "-C ${REPO_ROOT} valet-sync" "$CALL_LOG"; then
  echo "FAIL: expected a 'make -C ${REPO_ROOT} valet-sync' call, got:" >&2
  cat "$CALL_LOG" >&2
  exit 1
fi

echo "PASS: valet-watch.sh ran 'make -C ${REPO_ROOT} valet-sync' on a queue-dir change event."
