# SDPC — Azure Deployment Runbook

**Companion to `CLOUD-DEPLOYMENT-PLAN.md`. Written 2026-09-20.**

Follow the phases in order. Each one states its goal, the exact commands, how to
verify it worked, and what goes wrong. **Do not skip Phase 0.**

Placeholders to decide once and keep consistent:

| Name | Value used here | Notes |
| --- | --- | --- |
| Resource group | `sdpc-rg` | Delete this group to delete *everything* cleanly |
| Region | `eastasia` | Hong Kong. **`southeastasia` is blocked by subscription policy** |
| VM name | `sdpc-vm` | |
| VM size | `Standard_B2als_v2` | 2 vCPU, 4 GiB. **The B1s/B1ms family is unavailable to this subscription** |
| Admin user | `sdpc` | Not `root`, not `admin` |
| App hostname | `demo.sdpc.tech` | Azure only. `sdpc.tech` stays on the PC |
| Reverb hostname | `ws.sdpc.tech` | The browser connects here **directly** |
| App path on VM | `/var/www/sdpc` | |

---

## Correction to the plan: SSH still needs a public IP

`CLOUD-DEPLOYMENT-PLAN.md` §7.1 suggested dropping the public IP entirely. On
reflection that is wrong for a first Azure deployment: without a public IP there
is no way to SSH in to install the tunnel in the first place, and the escape
hatches (Azure Bastion at ~$140/mo, or serial console) are either unaffordable or
fiddly under pressure.

**Revised:** keep a public IP for **SSH only**, locked to your own address in the
NSG. Ports 80 and 443 stay closed — web traffic arrives through Cloudflare Tunnel.

You still get the win that matters: **no Certbot, so nothing expires while the VM
sleeps.** You give up ~$3.65/mo, so runway is ~4 months rather than ~5.

---

## Phase 0 — Pre-flight

**Goal:** know how much credit and calendar you actually have before spending any.

### 0.1 Check the credit

Portal → **Cost Management + Billing** → your subscription → **Credits**.

Write down:

- [ ] Remaining balance (not the original $100)
- [ ] **Expiry date** — the student grant is 12 months *or* $100, whichever first
- [ ] Anything already running under **All resources** (delete what you don't want)

> The Azure for Students subscription on the STI account was activated some time
> ago. If that clock is well advanced, the expiry date matters more than the
> balance, and that may change the plan.

### 0.2 Install the Azure CLI

```powershell
winget install -e --id Microsoft.AzureCLI
```

Close and reopen the terminal, then:

```bash
az login
az account show --output table
```

### 0.3 Region and size are already settled

Do not re-derive these. The subscription's `sys.regionrestriction` policy allows
only `koreacentral`, `centralindia`, `eastasia`, `australiaeast` and
`indonesiacentral`, and the entire v1 B-series is unavailable in all of them.

**`Standard_B2als_v2` in `eastasia` — $38.40/mo.** See `CLOUD-DEPLOYMENT-PLAN.md`
§3.1 for the evidence.

**Report back before Phase 1:** remaining credit and expiry date.

---

## Phase 1 — Provision the VM

**Goal:** an Ubuntu 24.04 VM you can SSH into, and nothing else exposed.

```bash
RG=sdpc-rg
LOC=eastasia
VM=sdpc-vm
ADMIN=sdpc

az group create --name $RG --location $LOC

az vm create \
  --resource-group $RG \
  --name $VM \
  --location $LOC \
  --image Canonical:ubuntu-24_04-lts:server:latest \
  --size Standard_B2als_v2 \
  --admin-username $ADMIN \
  --generate-ssh-keys \
  --public-ip-sku Standard \
  --os-disk-size-gb 32 \
  --storage-sku StandardSSD_LRS \
  --nsg-rule NONE
```

`--location` is explicit on purpose: without it the VM inherits the resource
group's region, and a group created in a blocked region fails the policy again.

`--nsg-rule NONE` opens nothing. Now allow SSH **from your address only**:

```bash
MYIP=$(curl -s https://api.ipify.org)
echo "Your address: $MYIP"

az network nsg rule create \
  --resource-group $RG \
  --nsg-name ${VM}NSG \
  --name AllowSSHFromMe \
  --priority 1000 \
  --protocol Tcp \
  --destination-port-ranges 22 \
  --source-address-prefixes $MYIP \
  --access Allow
```

Get the address and connect:

```bash
az vm show -d -g $RG -n $VM --query publicIps -o tsv
ssh sdpc@<that-ip>
```

> **Why not B1ms, and why not `southeastasia`?** The subscription carries policy
> `sys.regionrestriction`, which allows only `koreacentral`, `centralindia`,
> `eastasia`, `australiaeast` and `indonesiacentral`. On top of that the whole
> v1 B-series (`B1s`, `B1ms`, `B2s`) reports `NotAvailableForSubscription` in
> every one of them, and no 1-vCPU size with 2 GiB or more is offered at all.
> `Standard_B2als_v2` in `eastasia` is the cheapest thing that will actually
> deploy, at **$38.40/mo** against the $19.27 originally planned. See
> `CLOUD-DEPLOYMENT-PLAN.md` §3.1.

### Verify

- [ ] SSH connects
- [ ] `az network nsg rule list -g $RG --nsg-name ${VM}NSG -o table` shows **only**
      `AllowSSHFromMe`

### Set a budget alert now

Portal → **Cost Management** → **Budgets** → Add. Monthly, amount `50`, alerts at
50% / 80% / 100% — the VM alone is $44.79/mo if left running. Do this before you forget.

### What goes wrong

| Symptom | Cause |
| --- | --- |
| SSH times out | Your ISP gave you a new IP. Re-run the `MYIP` block with `rule update` instead of `create` |
| `image not found` | Older CLI. Try `--image Ubuntu2404`, or `az vm image list --publisher Canonical --all -o table` |
| Quota error | 6 vCPUs are available in `eastasia`; B2als_v2 uses 2 |
| `RequestDisallowedByPolicy` | You used a region outside the five allowed. Use `eastasia` |
| `SkuNotAvailable` | You used a v1 B-series size. Use `Standard_B2als_v2` |

---

## Phase 2 — Base server

**Goal:** PHP 8.4, Nginx, MySQL, and a little swap as insurance.

### 2.1 Swap first

B2als_v2 has 4 GiB, which is comfortable — but swap costs nothing and stops a
runaway `composer install` or build from killing MySQL.

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
free -h
```

### 2.2 Packages

Ubuntu 24.04 ships PHP 8.3. **This app requires `php: ^8.4`**, so the PPA is not
optional.

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt install -y \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-opcache \
  nginx mysql-server

php -v   # must report 8.4.x
```

### 2.3 Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 2.4 Upload limits

`config/uploads.php` allows 100 MB per file. PHP must allow at least as much or
uploads fail on the `uploaded` rule with a misleading message.

```bash
sudo tee /etc/php/8.4/fpm/conf.d/99-sdpc.ini > /dev/null <<'INI'
upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 256M
max_execution_time = 120
INI

sudo systemctl restart php8.4-fpm
```

### 2.5 PHP-FPM sizing for 4 GiB

```bash
sudo sed -i \
  -e 's/^pm = .*/pm = dynamic/' \
  -e 's/^pm.max_children = .*/pm.max_children = 12/' \
  -e 's/^pm.start_servers = .*/pm.start_servers = 2/' \
  -e 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 1/' \
  -e 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 3/' \
  /etc/php/8.4/fpm/pool.d/www.conf

sudo systemctl restart php8.4-fpm
```

### Verify

- [ ] `php -v` → 8.4.x
- [ ] `php -m` includes `pdo_mysql`, `mbstring`, `gd`, `intl`, `bcmath`, `zip`
- [ ] `free -h` shows ~4 GiB RAM and 2 GiB swap

---

> **Running long installs over SSH.** A `nohup ... &` job dies when the SSH
> session closes, so the install silently never runs. Use
> `sudo systemd-run --unit=name --collect bash /tmp/script.sh` and read it back
> with `journalctl -u name`. Do not pass `--property=StandardOutput=append:/tmp/...`
> — systemd cannot write there and the unit fails with `209/STDOUT` before your
> script starts.
>
> Also avoid `cmd | head -n` inside `set -o pipefail` scripts: `head` exiting
> early SIGPIPEs the producer, the pipeline reports failure, and `set -e` kills
> the script at that line.

## Phase 3 — MySQL

**Goal:** a database and a user, tuned not to eat the box.

```bash
sudo mysql_secure_installation
```

Answer: no to the validate-password plugin (it fights Laravel's generated
passwords), yes to everything else.

```bash
sudo mysql
```

```sql
CREATE DATABASE sdpc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sdpc'@'localhost' IDENTIFIED BY 'PUT-A-LONG-RANDOM-PASSWORD-HERE';
GRANT ALL PRIVILEGES ON sdpc.* TO 'sdpc'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Keep the buffer pool small so MySQL and PHP-FPM coexist:

```bash
sudo tee /etc/mysql/mysql.conf.d/99-sdpc.cnf > /dev/null <<'CNF'
[mysqld]
innodb_buffer_pool_size = 512M
max_connections = 40
CNF

sudo systemctl restart mysql
```

### Verify

```bash
mysql -u sdpc -p -e "SELECT VERSION();" sdpc
```

---

## Phase 4 — Get the code and assets onto the VM

**Goal:** the app on disk, with built frontend assets.

### 4.1 The critical detail

**`/public/build` is gitignored.** A `git clone` gives you no built assets and the
site dies with:

```
Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest
```

And **`VITE_REVERB_*` are compiled into the bundle at build time**, so the build
must be made with the *production* Reverb values, not your local ones.

Therefore: **build on your PC with production values, then ship `public/build`.**
Never `npm run build` on the VM.

### 4.2 Clone

```bash
sudo mkdir -p /var/www
sudo chown $USER:$USER /var/www
cd /var/www
git clone https://github.com/AquaMioo/SDPC-RepoTest.git sdpc
cd sdpc
composer install --no-dev --optimize-autoloader
```

### 4.3 Build locally with production values, then ship

On your **PC**, temporarily point the VITE vars at production:

```powershell
# in the repo, build with production Reverb values
$env:VITE_REVERB_APP_KEY="<the production key>"
$env:VITE_REVERB_HOST="ws.sdpc.tech"
$env:VITE_REVERB_PORT="443"
$env:VITE_REVERB_SCHEME="https"
npm run build
```

Ship it:

```powershell
scp -r public/build sdpc@<vm-ip>:/var/www/sdpc/public/
```

> **`VITE_APP_NAME` comes from `APP_NAME`, which is `Laravel` in the dev
> `.env`.** Build without overriding it and every browser tab on the demo box
> reads "… - Laravel". Set `VITE_APP_NAME=SDPC` in the override alongside the
> Reverb values.
>
> Re-run this **every time you deploy a frontend change**. This is the step people
> forget, and the symptom is a blank page or stale UI, not an error.

### 4.4 Environment

```bash
cd /var/www/sdpc
cp .env.production.example .env
nano .env
```

Set at minimum:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://demo.sdpc.tech

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sdpc
DB_USERNAME=sdpc
DB_PASSWORD=<the password from Phase 3>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<generate>
REVERB_APP_KEY=<generate>
REVERB_APP_SECRET=<generate>
REVERB_HOST=ws.sdpc.tech
REVERB_PORT=443
REVERB_SCHEME=https

BREVO_API_KEY=<the existing key>
MAIL_FROM_ADDRESS=no-reply@sdpc.tech
```

Note `DB_HOST=127.0.0.1`, **not** the Railway `${{MySQL.*}}` placeholders in the
template.

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
```

### 4.5 Permissions — two traps, both hit for real on 2026-09-20

```bash
sudo chown -R www-data:www-data /var/www/sdpc/storage /var/www/sdpc/bootstrap/cache
sudo find /var/www/sdpc/storage -type d -exec chmod 775 {} \;

# .env must be readable by the web user. 600 looks safer and is not: php-fpm
# and the queue worker then never see it at all, silently fall back to the
# sqlite default, and you get "Database file at path .../database.sqlite does
# not exist" from a queue worker you just configured for MySQL.
sudo chown root:www-data /var/www/sdpc/.env
sudo chmod 640 /var/www/sdpc/.env

# scp -r from Windows creates directories as 700. The manifest inside is
# world-readable but www-data cannot traverse the directory, so the app dies
# with "Vite manifest not found" pointing at a file that visibly exists.
chmod -R a+rX /var/www/sdpc/public/build
```

**Run the `chmod -R a+rX public/build` after every asset upload**, not just the
first. It is the single easiest step to forget and the error message actively
misleads you.

### Verify

- [ ] `php artisan about` runs and reports env `production`
- [ ] `ls public/build/manifest.json` exists

---

## Phase 5 — Nginx

**Goal:** serve the app on `localhost:80`. Nothing public yet — the tunnel comes next.

```bash
sudo tee /etc/nginx/sites-available/sdpc > /dev/null <<'NGINX'
server {
    listen 80;
    server_name demo.sdpc.tech;
    root /var/www/sdpc/public;

    index index.php;
    charset utf-8;

    client_max_body_size 128M;

    # Cloudflare terminates TLS; trust its forwarded scheme.
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;

        # Without these /login answers 502 "upstream sent too big header" for
        # any browser carrying a real session, while curl (no cookies) sees
        # 200 — so it looks fine until somebody actually tries to sign in.
        # SESSION_ENCRYPT=true plus the Google OAuth state cookie is what
        # pushes the headers past nginx's defaults.
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 16k;
        fastcgi_busy_buffers_size 64k;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX

sudo ln -s /etc/nginx/sites-available/sdpc /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### Trust the proxy

Cloudflare Tunnel delivers over HTTP to localhost. Without trusted proxies Laravel
generates `http://` URLs and marks secure cookies wrongly. Confirm
`bootstrap/app.php` trusts proxies — SDPC already has a `trustedproxy` config
(see `.ai/rules/config.md`); set it to trust all, since the only thing that can
reach port 80 is the local tunnel.

### Verify

```bash
curl -I -H "Host: demo.sdpc.tech" http://127.0.0.1/up   # expect 200
```

---

## Phase 6 — Cloudflare Tunnel

**Goal:** `demo.sdpc.tech` and `ws.sdpc.tech` reachable over HTTPS, with no
inbound ports and no certificates to renew.

### 6.1 Create the tunnel

Cloudflare dashboard → **Zero Trust** → **Networks** → **Tunnels** → *Create a
tunnel* → **Cloudflared**. Name it `sdpc-azure`. Copy the install command — it
contains the token.

### 6.2 Install on the VM

```bash
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb -o cloudflared.deb
sudo dpkg -i cloudflared.deb

# paste the command Cloudflare gave you:
sudo cloudflared service install <YOUR-TUNNEL-TOKEN>

sudo systemctl status cloudflared
```

### 6.3 Public hostnames

In the tunnel's **Published application routes**, add two:

| Hostname | Service |
| --- | --- |
| `demo.sdpc.tech` | `http://localhost:80` |
| `ws.sdpc.tech` | `http://localhost:8080` |

Cloudflare creates the DNS records automatically. **Leave `sdpc.tech` alone** — it
stays on the PC tunnel.

WebSockets are on by default for proxied hostnames; confirm under **Network** →
**WebSockets** if chat fails to connect.

> **If you edit a route and the hostname stops resolving.** Editing a duplicated
> route can delete its DNS record while leaving the ingress rule in place. The
> tunnel then listens for a hostname nothing points at, and Cloudflare answers
> **530 / error 1016**. Fix by deleting that published application route and
> adding it again — re-adding is what recreates the record.
>
> **Your ISP will cache the NXDOMAIN.** After the record exists, `1.1.1.1` will
> resolve it immediately while your own resolver keeps saying "does not exist"
> for up to an hour. That is negative caching, not a misconfiguration — verify
> with `Resolve-DnsName ws.sdpc.tech -Server 1.1.1.1` before changing anything.
>
> **Testing a WebSocket with curl needs `--http1.1`.** Without it curl
> negotiates HTTP/2, where `Connection: Upgrade` is illegal, and Cloudflare
> returns a misleading **500**. Forced to HTTP/1.1 the same request returns
> `101 Switching Protocols`.

### Verify

- [ ] `https://demo.sdpc.tech/up` returns 200 from another network (use your phone
      on mobile data)
- [ ] Padlock is valid — Cloudflare's edge certificate, nothing you manage

---

## Phase 7 — The three background processes

**Goal:** queue, scheduler and Reverb running and surviving reboot.

### 7.1 Queue worker

```bash
sudo tee /etc/systemd/system/sdpc-queue.service > /dev/null <<'UNIT'
[Unit]
Description=SDPC queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/sdpc
ExecStart=/usr/bin/php artisan queue:work --tries=3 --max-time=3600 --sleep=3

[Install]
WantedBy=multi-user.target
UNIT
```

### 7.2 Reverb

```bash
sudo tee /etc/systemd/system/sdpc-reverb.service > /dev/null <<'UNIT'
[Unit]
Description=SDPC Reverb WebSocket server
After=network.target

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/sdpc
ExecStart=/usr/bin/php artisan reverb:start --host 127.0.0.1 --port 8080

[Install]
WantedBy=multi-user.target
UNIT
```

Bind to `127.0.0.1`, not `0.0.0.0` — only the tunnel needs to reach it.

### 7.3 Scheduler

There is one daily task (expired team invitations). A systemd timer is tidier than
cron:

```bash
sudo tee /etc/systemd/system/sdpc-schedule.service > /dev/null <<'UNIT'
[Unit]
Description=SDPC scheduler tick

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/var/www/sdpc
ExecStart=/usr/bin/php artisan schedule:run
UNIT

sudo tee /etc/systemd/system/sdpc-schedule.timer > /dev/null <<'UNIT'
[Unit]
Description=Run the SDPC scheduler every minute

[Timer]
OnCalendar=*:0/1
AccuracySec=1s

[Install]
WantedBy=timers.target
UNIT
```

### 7.4 Start everything

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now sdpc-queue sdpc-reverb sdpc-schedule.timer
sudo systemctl status sdpc-queue sdpc-reverb --no-pager
```

### Verify

- [ ] All three active
- [ ] Open the site, send a chat message, confirm it arrives without a refresh
- [ ] `php artisan queue:failed` is empty

> **Never** run `queue:restart` or `reverb:restart` expecting systemd to recover —
> use `systemctl restart sdpc-queue sdpc-reverb`. (The same rule the PC host has.)

---

## Phase 8 — Third-party wiring for the new hostname

Easy to miss and it breaks sign-in.

### 8.1 Google OAuth

`GOOGLE_REDIRECT_URI` derives from `APP_URL`, so the new hostname needs **both**
callback URIs registered in Google Cloud Console → Credentials → your OAuth client:

- `https://demo.sdpc.tech/auth/google/callback`
- `https://demo.sdpc.tech/settings/google/callback`

Two URIs, not one — `auth/google/callback` is guest-only and account *binding*
uses the settings one. (See `.ai/rules/student.md`.)

### 8.2 Brevo

No DNS change needed: mail still sends from `no-reply@sdpc.tech`, which is already
verified. Just copy `BREVO_API_KEY` into the VM's `.env`.

### 8.3 Microsoft

Currently off and blocked on STI IT. Leave `MICROSOFT_*` blank — the button hides
itself.

### Verify

- [ ] Google sign-in completes on `demo.sdpc.tech`
- [ ] A school-email code arrives (check Junk)

---

## Phase 9 — Demo data

The Azure database is empty. A defense demo of an empty system is bad.

**One-way, one-time.** From the PC host:

```powershell
mysqldump -u <user> -p --single-transaction --routines sdpc > sdpc-demo.sql
scp sdpc-demo.sql sdpc@<vm-ip>:/tmp/
```

On the VM:

```bash
mysql -u sdpc -p sdpc < /tmp/sdpc-demo.sql
php artisan migrate --force      # in case the dump predates a migration
rm /tmp/sdpc-demo.sql
```

Uploaded files referenced by that data live on the PC's disk, so avatars and
message pictures will 404 until R2 is in place (Phase 11) or you copy
`storage/app/public` across as well.

### Rules

1. **Never** `migrate:fresh`, `db:seed` or `ResetUserContent` against the VM.
2. Deploys run `migrate --force` only.
3. Local and VM databases are independent and are never reconciled.

---

## Phase 10 — The sleep/wake routine

### Going to sleep

```bash
az vm deallocate -g sdpc-rg -n sdpc-vm
```

**`sudo shutdown` does not deallocate** — you keep paying full compute. Always use
`az vm deallocate`, or Stop in the portal.

While deallocated you pay for the disk and the public IP only (**$6.39/mo**).
Running, it is **$44.79/mo** — leaving it on for a month by accident costs ~45%
of the whole grant.

### Waking up (do this the day *before* a demo, not an hour)

```bash
az vm start -g sdpc-rg -n sdpc-vm
```

Then:

```bash
ssh sdpc@<ip>
sudo systemctl status nginx php8.4-fpm mysql cloudflared sdpc-queue sdpc-reverb --no-pager
curl -I https://demo.sdpc.tech/up
```

Checklist before a demo:

- [ ] Public IP unchanged (it is static, but confirm)
- [ ] Your NSG SSH rule still matches your current address
- [ ] All six services active
- [ ] `/up` returns 200 from mobile data
- [ ] Send a chat message — confirms Reverb, queue and the tunnel at once
- [ ] Sign in with Google — confirms the OAuth redirect URIs

### Deploying an update

```bash
cd /var/www/sdpc
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl restart sdpc-queue sdpc-reverb php8.4-fpm
```

**Then ship `public/build` from your PC again** (§4.3) if the frontend changed —
and re-run `chmod -R a+rX public/build` afterwards, or the app 500s with
"Vite manifest not found" on a file that is plainly there.

---

## Phase 11 — Cloudflare R2 *(blocked)*

Not started. Two decisions gate it:

1. **May `league/flysystem-aws-s3-v3` be added?** It is not installed; nothing R2
   works without it.
2. **How are private files served?** Pre-signed URLs (zero egress, but a
   capability URL anyone holding it can open until it expires) or keep streaming
   through PHP (authorization on every byte, but egress stays).

See `CLOUD-DEPLOYMENT-PLAN.md` §5.2 for why this is a security decision rather
than a config change.

---

## Teardown, when the capstone is over

```bash
az group delete --name sdpc-rg --yes --no-wait
```

Deleting the **resource group** is the only reliable way to avoid orphaned disks,
NICs and public IPs continuing to bill. Deleting just the VM leaves all three.

Also remember to delete the tunnel in Cloudflare Zero Trust and the two DNS
records it created.
