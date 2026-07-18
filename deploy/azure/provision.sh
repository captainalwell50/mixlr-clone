#!/usr/bin/env bash
# Provision Azure Linux VM + NSG for Church Live (Laravel + MediaMTX + Caddy).
# Prerequisites: az login, SSH public key at ~/.ssh/id_ed25519.pub
set -euo pipefail

RG="${RG:-rg-church-live}"
LOCATION="${LOCATION:-eastus}"
VM_NAME="${VM_NAME:-vm-church-live}"
# B-series often hits capacity limits on Free Trial; D2as_v7 is a reliable fallback.
VM_SIZE="${VM_SIZE:-Standard_D2as_v7}"
ADMIN_USER="${ADMIN_USER:-azureuser}"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/id_ed25519.pub}"
IMAGE="${IMAGE:-Canonical:ubuntu-24_04-lts:server:latest}"
# Deploy scripts clone this branch (never main for this project).
REPO_BRANCH="${REPO_BRANCH:-project/mixlr-clone}"

if [[ ! -f "$SSH_KEY" ]]; then
  echo "SSH public key not found: $SSH_KEY" >&2
  exit 1
fi

echo "==> Resource group: $RG ($LOCATION)"
az group create --name "$RG" --location "$LOCATION" --output none

echo "==> Creating VM: $VM_NAME ($VM_SIZE)"
az vm create \
  --resource-group "$RG" \
  --name "$VM_NAME" \
  --image "$IMAGE" \
  --size "$VM_SIZE" \
  --admin-username "$ADMIN_USER" \
  --ssh-key-values "$SSH_KEY" \
  --public-ip-sku Standard \
  --os-disk-size-gb 64 \
  --output json > /tmp/az-vm-create.json

NSG="$(az vm show -g "$RG" -n "$VM_NAME" --query networkProfile.networkInterfaces[0].id -o tsv | xargs -I{} az network nic show --ids {} --query networkSecurityGroup.id -o tsv)"
if [[ -z "$NSG" || "$NSG" == "None" ]]; then
  # Default NIC NSG name from az vm create
  NSG_NAME="${VM_NAME}NSG"
else
  NSG_NAME="$(basename "$NSG")"
fi

echo "==> Opening NSG ports on $NSG_NAME"
open_port() {
  local name="$1" port="$2" proto="${3:-Tcp}" prio="$4"
  az network nsg rule create \
    --resource-group "$RG" \
    --nsg-name "$NSG_NAME" \
    --name "$name" \
    --priority "$prio" \
    --access Allow \
    --protocol "$proto" \
    --direction Inbound \
    --source-address-prefixes '*' \
    --source-port-ranges '*' \
    --destination-address-prefixes '*' \
    --destination-port-ranges "$port" \
    --output none 2>/dev/null || \
  az network nsg rule update \
    --resource-group "$RG" \
    --nsg-name "$NSG_NAME" \
    --name "$name" \
    --access Allow \
    --protocol "$proto" \
    --destination-port-ranges "$port" \
    --output none
}

# 22 and 80/443 often already exist from az vm create defaults — ensure media ports
open_port Allow-HTTP 80 Tcp 1000
open_port Allow-HTTPS 443 Tcp 1001
open_port Allow-WebRTC-UDP 8189 Udp 1002
open_port Allow-WebRTC-TCP 8189 Tcp 1003
open_port Allow-RTMP 1935 Tcp 1004

IP="$(az vm list-ip-addresses -g "$RG" -n "$VM_NAME" --query '[0].virtualMachine.network.publicIpAddresses[0].ipAddress' -o tsv)"
echo ""
echo "VM ready."
echo "  Public IP: $IP"
echo "  SSH:       ssh ${ADMIN_USER}@${IP}"
echo "  App host:  ${IP}.sslip.io"
echo "$IP" > /tmp/church-live-vm-ip.txt
