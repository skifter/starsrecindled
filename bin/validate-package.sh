#!/bin/sh
set -eu

ROOT="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"

find "${ROOT}/src" "${ROOT}/config" "${ROOT}/migrations" "${ROOT}/tests" \
  -type f -name '*.php' -print0 \
  | xargs -0 -n1 php -l

ROOT="${ROOT}" php -r '
$root = getenv("ROOT");
foreach (["composer.json", "frontend/package.json"] as $file) {
    $path = $root."/".$file;
    json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    fwrite(STDOUT, $file." OK\n");
}
'

if command -v composer >/dev/null 2>&1; then
  (cd "${ROOT}" && composer validate --strict)
else
  printf '%s\n' 'composer ikke fundet; composer validate blev sprunget over.' >&2
fi
