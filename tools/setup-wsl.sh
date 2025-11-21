#!/usr/bin/env bash
# Ultra Pixels Ultra - WSL Environment Bootstrap Script
#
# Usage:
#   bash tools/setup-wsl.sh            # Run from repo root (recommended)
#   chmod +x tools/setup-wsl.sh && ./tools/setup-wsl.sh
#
# What it does (idempotent where practical):
#   1. Checks distro + WSL version hints
#   2. Installs base packages (git, curl, unzip, p7zip, build-essential)
#   3. Installs PHP 8.2 + common extensions
#   4. Installs Composer (if missing)
#   5. Installs nvm + Node 18 (if missing)
#   6. Installs project PHP + Node dependencies
#   7. Runs build + static analysis (phpstan, phpcs)
#
# Safe to re-run; will skip already installed tools.
# Requires sudo for apt operations.

set -euo pipefail
IFS=$'\n\t'

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "[info] Project root: $PROJECT_ROOT"

# --- Helper functions -------------------------------------------------------

have() { command -v "$1" >/dev/null 2>&1; }

append_if_missing() {
  local file="$1" line="$2"
  grep -Fqx "$line" "$file" 2>/dev/null || echo "$line" >> "$file"
}

# --- 1. Environment sanity --------------------------------------------------

echo "[step] Checking WSL environment"
if grep -qi microsoft /proc/version 2>/dev/null; then
  echo "[ok] Running inside WSL"
else
  echo "[warn] Script intended for WSL but non-WSL environment detected"
fi

# --- 2. Base packages -------------------------------------------------------

echo "[step] Installing base packages"
sudo apt update -y
sudo apt install -y git curl unzip p7zip-full build-essential ca-certificates gnupg lsb-release software-properties-common

# --- 3. PHP 8.2 + extensions ------------------------------------------------

if ! php -v 2>/dev/null | grep -q 'PHP 8.2'; then
  echo "[step] Installing PHP 8.2 and extensions"
  sudo add-apt-repository ppa:ondrej/php -y
  sudo apt update -y
  sudo apt install -y php8.2 php8.2-cli php8.2-curl php8.2-xml php8.2-zip php8.2-mbstring php8.2-json php8.2-common
else
  echo "[ok] PHP 8.2 already installed"
fi
php -v || true

# --- 4. Composer ------------------------------------------------------------

if ! have composer; then
  echo "[step] Installing Composer"
  curl -sS https://getcomposer.org/installer -o composer-setup.php
  php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f composer-setup.php
else
  echo "[ok] Composer already present"
fi
composer --version || true

# --- 5. Node.js via nvm -----------------------------------------------------

if [ -z "${NVM_DIR:-}" ]; then
  export NVM_DIR="$HOME/.nvm"
fi
if [ ! -s "$NVM_DIR/nvm.sh" ]; then
  echo "[step] Installing nvm"
  curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
fi
# shellcheck disable=SC1090
. "$NVM_DIR/nvm.sh"

if ! have node || ! node -v | grep -q '^v18'; then
  echo "[step] Installing Node 18 via nvm"
  nvm install 18
fi
nvm use 18 >/dev/null
node -v || true
npm -v || true

# --- 6. Project dependencies -------------------------------------------------

echo "[step] Installing Composer dev dependencies"
composer install --no-interaction || composer update --no-interaction || true

echo "[step] Installing Node dependencies"
npm install

echo "[step] Building assets"
npm run build || echo "[warn] Build script failed; investigate package.json"

# --- 7. Static analysis -----------------------------------------------------

if [ -f phpstan.neon.dist ]; then
  echo "[step] Running PHPStan"
  if [ -f vendor/bin/phpstan ]; then
    vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress || echo "[warn] PHPStan reported issues"
  else
    echo "[warn] phpstan not found in vendor/bin"
  fi
else
  echo "[skip] phpstan.neon.dist missing"
fi

if [ -f phpcs.xml ]; then
  echo "[step] Running PHPCS"
  if [ -f vendor/bin/phpcs ]; then
    vendor/bin/phpcs --standard=phpcs.xml || echo "[warn] PHPCS reported issues"
  else
    echo "[warn] phpcs not found in vendor/bin"
  fi
else
  echo "[skip] phpcs.xml missing"
fi

# --- 8. Developer convenience aliases --------------------------------------

PROFILE_FILE="$HOME/.bashrc"
echo "[step] Adding convenience aliases (if missing)"
append_if_missing "$PROFILE_FILE" "alias upstan='vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress'"
append_if_missing "$PROFILE_FILE" "alias upcs='vendor/bin/phpcs --standard=phpcs.xml'"
append_if_missing "$PROFILE_FILE" "alias upbuild='npm run build'"

echo "[done] WSL setup complete. Start a new shell or run: source ~/.bashrc"
echo "[next] Rebase branch: git checkout main && git pull && git checkout chore/codeql-resigned && git rebase main"
echo "[next] Trigger CI: git commit --allow-empty -S -m 'chore(ci): WSL verified setup' && git push --force-with-lease"
