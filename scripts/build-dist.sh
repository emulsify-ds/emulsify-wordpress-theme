#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
artifact_dir="${repo_root}/dist-artifact"
artifact_path="${artifact_dir}/emulsify.zip"

for command_name in composer node zip; do
  if ! command -v "${command_name}" >/dev/null 2>&1; then
    echo "Required command not found: ${command_name}" >&2
    exit 1
  fi
done

mkdir -p "${artifact_dir}"
staging_parent="$(mktemp -d "${artifact_dir}/.build.XXXXXX")"
staging_root="${staging_parent}/emulsify"
temporary_archive="${staging_parent}/emulsify.zip"

cleanup() {
  rm -rf "${staging_parent}"
}
trap cleanup EXIT

mkdir -p "${staging_root}"

runtime_files=(
  "404.php"
  "LICENSE"
  "README.md"
  "archive.php"
  "author.php"
  "functions.php"
  "index.php"
  "page.php"
  "screenshot.png"
  "search.php"
  "single.php"
  "style.css"
  "theme.json"
)

runtime_directories=(
  "includes"
  "src"
  "templates"
)

for relative_path in "${runtime_files[@]}" "${runtime_directories[@]}"; do
  source_path="${repo_root}/${relative_path}"
  if [[ ! -e "${source_path}" ]]; then
    echo "Required runtime path not found: ${relative_path}" >&2
    exit 1
  fi

  cp -R "${source_path}" "${staging_root}/"
done

for composer_file in composer.json composer.lock; do
  if [[ ! -f "${repo_root}/${composer_file}" ]]; then
    echo "Required Composer metadata not found: ${composer_file}" >&2
    exit 1
  fi

  cp "${repo_root}/${composer_file}" "${staging_root}/"
done

echo "Installing production Composer dependencies..."
composer \
  --working-dir="${staging_root}" \
  install \
  --no-dev \
  --no-interaction \
  --no-progress \
  --prefer-dist \
  --optimize-autoloader

rm -f "${staging_root}/composer.json" "${staging_root}/composer.lock"

if [[ ! -f "${staging_root}/vendor/autoload.php" ]]; then
  echo "Composer did not create the staged vendor/autoload.php file." >&2
  exit 1
fi

if [[ -z "${SOURCE_DATE_EPOCH:-}" ]]; then
  if git -C "${repo_root}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    SOURCE_DATE_EPOCH="$(git -C "${repo_root}" log -1 --format=%ct)"
  else
    SOURCE_DATE_EPOCH=315532800
  fi
fi

if [[ ! "${SOURCE_DATE_EPOCH}" =~ ^[0-9]+$ ]] || (( SOURCE_DATE_EPOCH < 315532800 )); then
  echo "SOURCE_DATE_EPOCH must be an integer at or after 1980-01-01." >&2
  exit 1
fi

node - "${staging_root}" "${SOURCE_DATE_EPOCH}" <<'NODE'
const fs = require('fs');
const path = require('path');

const root = process.argv[2];
const epoch = Number(process.argv[3]);

function normalize(entryPath) {
  const stat = fs.lstatSync(entryPath);

  if (stat.isDirectory()) {
    for (const entry of fs.readdirSync(entryPath).sort()) {
      normalize(path.join(entryPath, entry));
    }
    fs.chmodSync(entryPath, 0o755);
    fs.utimesSync(entryPath, epoch, epoch);
    return;
  }

  if (stat.isFile()) {
    fs.chmodSync(entryPath, 0o644);
    fs.utimesSync(entryPath, epoch, epoch);
  }
}

normalize(root);
NODE

echo "Creating ${artifact_path}..."
(
  cd "${staging_parent}"
  find emulsify -type f -print | LC_ALL=C sort | zip -X -q "${temporary_archive}" -@
)

mv "${temporary_archive}" "${artifact_path}"
echo "Built ${artifact_path}"
