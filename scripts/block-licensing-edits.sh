#!/usr/bin/env bash
set -euo pipefail
# Allow override for emergencies: ALLOW_LICENSE_EDIT=1 git commit -m "..."
if [[ "${ALLOW_LICENSE_EDIT:-}" == "1" ]]; then exit 0; fi
PROTECTED_REGEX='^(includes/License/|leadstream-licserver/)'
changed=$(git diff --cached --name-only || true)
if echo "$changed" | grep -E "$PROTECTED_REGEX" >/dev/null; then
  echo "❌ Licensing code is protected. Use a dedicated PR with the 'licensing:approved' label or set ALLOW_LICENSE_EDIT=1 if you really must."
  exit 1
fi