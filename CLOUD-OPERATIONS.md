# SDPC on Azure — Operations Cheat Sheet

**The day-to-day guide. For how it was built, see `CLOUD-DEPLOYMENT-RUNBOOK.md`.**
Deployed 2026-09-20 at commit `81ec1fb`.

---

## The two environments

| | `sdpc.tech` | `demo.sdpc.tech` |
| --- | --- | --- |
| Runs on | Your PC (FrankenPHP, NSSM) | Azure VM (nginx, systemd) |
| Tunnel | `Cloudflared` | `sdpc-azure` |
| Database | MySQL `:3307` on the PC | MySQL on the VM |
| Cost | Free | **$44.79/mo running**, $6.39/mo asleep |
| Purpose | Always-on, real data | Demos and defense dates |

**Which one am I looking at?**

```bash
curl https://sdpc.tech/origin.txt
```
```bash
curl https://demo.sdpc.tech/origin.txt
```

Answers `pc` or `azure`. They can never serve each other's hostname — different
tunnels, different machines.

## Connection details

| | |
| --- | --- |
| SSH | `ssh sdpc@104.208.99.16` |
| App path | `/var/www/sdpc` |
| Resource group | `sdpc-rg` (region `eastasia`) |
| VM | `sdpc-vm`, `Standard_B2als_v2`, 2 vCPU / 4 GiB |
| Tunnel ID | `da651d7b-03ed-4ddb-83f9-2c7dea11ada1` |
| Reverb hostname | `ws.sdpc.tech` → `localhost:8080` |
| DB password | `/home/sdpc/.sdpc-db-pass` on the VM (mode 600) |

---

## 1. Sleeping and waking — the money one

### Sleep (do this whenever you are not demoing)

```bash
az vm deallocate -g sdpc-rg -n sdpc-vm
```

**$44.79/mo → $6.39/mo.** Takes about a minute.

> **`sudo shutdown` does NOT do this.** Shutting down from inside the OS leaves
> the VM allocated and you keep paying full compute for a machine that is off.
> Always deallocate from the CLI or the portal.

### Wake (the day *before* a demo, not an hour before)

```bash
az vm start -g sdpc-rg -n sdpc-vm
```

Everything restarts by itself — nginx, php-fpm, mysql, cloudflared, queue,
reverb and the scheduler are all `enabled`. Give it two minutes, then run the
pre-demo check below.

### While asleep

- `demo.sdpc.tech` returns a Cloudflare error. `sdpc.tech` is unaffected.
- The public IP is static, so it does **not** change across sleep/wake.
- You still pay $6.39/mo for the disk and the IP. That is the price of the VM
  existing at all — see §5 if you want it to be zero.

---

## 2. Pre-demo checklist

Run all of it. Takes thirty seconds.

```bash
az vm start -g sdpc-rg -n sdpc-vm
```

```bash
ssh sdpc@104.208.99.16 'for u in nginx php8.4-fpm mysql cloudflared sdpc-queue sdpc-reverb sdpc-schedule.timer; do printf "%-22s %s\n" "$u" "$(systemctl is-active $u)"; done'
```

All seven must say `active`.

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://demo.sdpc.tech/up
```

Must be `200`.

Then, in a browser:

- [ ] Open `https://demo.sdpc.tech/login` — page renders, title says **SDPC**
- [ ] Sign in with Google — proves the OAuth redirect URIs
- [ ] Send a chat message — proves Reverb, the queue and the tunnel at once
- [ ] Open a page with an avatar — proves storage

> **If SSH times out**, your ISP gave you a new IP. Fix the firewall rule:
> ```bash
> az network nsg rule update -g sdpc-rg --nsg-name sdpc-vmNSG -n AllowSSHFromMe --source-address-prefixes $(curl -s https://api.ipify.org)
> ```
> This does not affect the website — only your SSH access.

---

## 3. Deploying an update

Two halves, and **the second is the one people forget**.

### Backend, on the VM

```bash
ssh sdpc@104.208.99.16
```

```bash
cd /var/www/sdpc && git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && sudo systemctl restart php8.4-fpm sdpc-queue sdpc-reverb
```

### Frontend, from your PC — only if the UI changed

The built assets are **gitignored**, so `git pull` never brings them. And
`VITE_*` values are compiled in at build time, so the build must carry the
*production* values.

**Step 1** — create `.env.production.local` in your repo:

```
VITE_APP_NAME=SDPC
VITE_REVERB_APP_KEY=76b4ee6224c571978c8efec2b11c47cb
VITE_REVERB_HOST=ws.sdpc.tech
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

**Step 2** — build, ship, fix permissions, clean up:

```bash
npm run build
```
```bash
scp -r public/build sdpc@104.208.99.16:/var/www/sdpc/public/
```
```bash
ssh sdpc@104.208.99.16 'chmod -R a+rX /var/www/sdpc/public/build'
```
```bash
rm .env.production.local && npm run build
```

> The `chmod` is **not optional**. `scp -r` from Windows creates directories as
> `700`, and the app then 500s with "Vite manifest not found" pointing at a file
> that visibly exists.
>
> The final `rm` + rebuild restores your local assets. Skip it and your local
> dev tries to reach `ws.sdpc.tech` for chat.

---

## 4. When something breaks

| Symptom | Cause | Fix |
| --- | --- | --- |
| **502** on a page, `curl` says 200 | nginx FastCGI buffers too small for a real browser's cookies | Already fixed. If it returns, raise `fastcgi_buffer_size` in `/etc/nginx/sites-available/sdpc` |
| **530** on a hostname | Its DNS record was deleted while the tunnel route survived | Delete and re-add that published application route |
| Hostname says "does not exist" but `1.1.1.1` resolves it | Your ISP cached the old NXDOMAIN | Wait up to an hour, or use `1.1.1.1` |
| "Vite manifest not found" | `public/build` is mode 700 after scp | `chmod -R a+rX /var/www/sdpc/public/build` |
| Tabs read "… - Laravel" | Built without `VITE_APP_NAME=SDPC` | Rebuild with the override |
| Queue says `database.sqlite does not exist` | `www-data` cannot read `.env` | `sudo chown root:www-data .env && sudo chmod 640 .env` |
| Chat silently never connects | `ws.sdpc.tech` route or the baked-in Reverb key is wrong | Check the key in `.env` matches the bundle |
| WebSocket test with curl gives 500 | curl used HTTP/2, where `Connection: Upgrade` is illegal | Add `--http1.1` |

### Where to look

```bash
ssh sdpc@104.208.99.16 'sudo tail -30 /var/www/sdpc/storage/logs/laravel.log'
```
```bash
ssh sdpc@104.208.99.16 'sudo tail -20 /var/log/nginx/error.log'
```
```bash
ssh sdpc@104.208.99.16 'sudo journalctl -u sdpc-reverb -u sdpc-queue -u cloudflared --no-pager -n 40'
```

> Restart services with `systemctl restart sdpc-queue sdpc-reverb`, **not**
> `php artisan queue:restart` — systemd owns these processes.

---

## 5. Cost control

| State | Cost |
| --- | --- |
| Running | **$44.79/mo** |
| Deallocated | **$6.39/mo** (disk $2.74 + IP $3.65) |
| Deleted | $0 |

You have **$100, expiring 2027-09-19**. Budget alerts are set at $50/month and
email you at 50%, 80% and forecast-100%.

Check the spend:

```bash
az consumption usage list --start-date 2026-09-01 --end-date 2026-09-30 --query "sum([].pretaxCost)" -o tsv
```

**If the demos are far apart**, deleting the VM entirely beats keeping it asleep:
12 months of "asleep" costs $76.68 — three quarters of the grant — for a machine
you are not using. Rebuilding from the runbook is about 30 minutes.

```bash
az group delete --name sdpc-rg --yes --no-wait
```

Deleting the **resource group** is the only reliable teardown. Deleting just the
VM leaves the disk, the NIC and the public IP billing forever. Afterwards also
delete the `sdpc-azure` tunnel in Cloudflare Zero Trust and its two DNS records.

---

## 6. Things that are still not done

- **Demo data.** The database has 78 migrations and zero rows. Take a
  `mysqldump` from the PC and restore it once — see runbook Phase 9.
- **Cloudflare R2.** Not started. Needs two decisions: whether
  `league/flysystem-aws-s3-v3` may be added, and whether private files get
  pre-signed URLs or keep streaming through PHP.
- **The Google *binding* URI** (`/settings/google/callback`) has never been
  exercised — it needs a signed-in student. The sign-in URI is verified.
