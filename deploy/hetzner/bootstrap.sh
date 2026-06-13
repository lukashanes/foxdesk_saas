#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root on a fresh Ubuntu Hetzner server." >&2
  exit 1
fi

apt-get update
apt-get install -y ca-certificates curl git nodejs npm ufw unattended-upgrades

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

. /etc/os-release
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
  > /etc/apt/sources.list.d/docker.list

apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

systemctl enable --now docker

echo "Bootstrap complete. Next:"
echo "1. Clone repo to /opt/foxdesk_saas"
echo "2. Copy .env.production.example to .env.production and fill secrets"
echo "3. Copy config.production.example.php to config.php"
echo "4. Run npm ci && npx playwright install --with-deps chromium"
echo "5. Run deploy/hetzner/deploy.sh"
