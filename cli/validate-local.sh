#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VALIDATOR="${MOODLE_VALIDATOR_DIR:-$HOME/src/moodle-plugin-validate}"
if [[ ! -f "$VALIDATOR/.chatgpt/run.py" ]]; then
    git clone https://github.com/EduardoKrausME/moodle-plugin-validate.git "$VALIDATOR"
else
    git -C "$VALIDATOR" pull --ff-only
fi
cat "$VALIDATOR/.chatgpt/INSTRUCTIONS.md"
python3 "$VALIDATOR/.chatgpt/run.py" "$ROOT"
python3 "$VALIDATOR/.chatgpt/run.py" --check "$ROOT"
find "$ROOT" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
moodle-plugin-ci validate
moodle-plugin-ci phpcs --max-warnings 0
moodle-plugin-ci phpunit --fail-on-warning
moodle-plugin-ci behat --profile chrome
