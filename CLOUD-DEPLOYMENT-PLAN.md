# SDPC — Cloud Deployment Strategy

**Written 2026-09-20 · Status: strategy agreed in outline, five decisions open, nothing built yet.**

Covers the Azure for Students deployment, Cloudflare R2 file offloading, and how
local testing relates to the cloud VM. No implementation has been done; the open
decisions at the end gate that.

---

## TL;DR

| Question | Answer |
| --- | --- |
| Is $100 enough for 2 months? | Continuous running is ~$90 of the $100. **Confirmed $100 and 365 days remain**, so yes — but only with the sleep/wake routine. See §3.3. |
| Will storage burn the credit? | **No.** Compute is ~75% of the bill; disk is ~10%. |
| Is R2 still worth doing? | **Yes** — for durability and not filling the disk, not for savings. |
| Is Azure the right shape? | **Yes, one VM running everything.** Do not split layers across Vercel/Render. |
| Biggest risk | **Cost is now a real constraint**, plus an expired TLS cert and an empty demo database on defense day. |

---

## 1. Where we are today

- `sdpc.tech` is **already live and free**, served from a PC via FrankenPHP behind a
  Cloudflare Tunnel, with web / queue / scheduler / Reverb running under NSSM.
- A Railway deployment runs in parallel, pending retirement.
- So Azure buys exactly one thing: **availability that does not depend on that PC
  being powered on and online.** For a defense demo, that is worth having.

## 2. Intended operating model

| Environment | Role | Database |
| --- | --- | --- |
| Local dev machines | Testing only, and emergency fallback | Own DB, seeders, **never synced** |
| Azure VM | Demos and defense dates; deallocated in between | Own DB, seeded once from a dump |
| Cloudflare R2 | File storage shared by both | n/a |

**Only code and schema (migrations) travel between environments. Data does not.**
This is deliberate and protects live demo data from being wiped by a deploy.

## 3. Cost model

> **Corrected 2026-09-20 after checking the live subscription.** The original
> B1ms plan is impossible: see §3.1. Figures below are from the Azure Retail
> Prices API and from `az vm list-skus` against the real subscription.

### 3.1 What this subscription can actually deploy

Two hard limits were found on the Azure for Students subscription:

**Region policy `sys.regionrestriction`** allows only five regions. `southeastasia`
is **not** among them:

`koreacentral` · `centralindia` · **`eastasia`** · `australiaeast` · `indonesiacentral`

**The v1 B-series does not exist for this subscription.** `B1s`, `B1ms` and `B2s`
all report `NotAvailableForSubscription` in every allowed region, and there is
**no 1-vCPU size at all** with 2 GiB or more. The floor is 2 vCPU.

Cheapest deployable options actually offered:

| Region | Size | Spec | Monthly |
| --- | --- | --- | --- |
| **eastasia** | **Standard_B2als_v2** | 2 vCPU, 4 GiB, x64 | **$38.40** |
| koreacentral | Standard_B2ls_v2 | 2 vCPU, 4 GiB | $37.96 |
| eastasia | Standard_B2pls_v2 | 2 vCPU, 4 GiB, **ARM64** | $40.59 |
| eastasia | Standard_B2s_v2 | 2 vCPU, 8 GiB | $91.98 |

**Chosen: `Standard_B2als_v2` in `eastasia`** — Hong Kong is the closest allowed
region to the Philippines, and koreacentral's $0.44/mo saving is not worth the
extra latency. ARM is avoided: it would work, but it is a needless risk for a
capstone.

### 3.2 Cost model

| Item | Unit | Monthly |
| --- | --- | --- |
| B2als_v2 (2 vCPU, 4 GiB), eastasia | $0.0526/hr | **$38.40** |
| Standard SSD E4, 32 GiB LRS + mount | — | **$2.74** |
| Standard static IPv4 | $0.005/hr | **$3.65** |
| Bandwidth (first 100 GB/mo free) | — | $0 |
| Cloudflare R2 (10 GB + zero egress free) | — | $0 |
| **Running** | | **$44.79/mo** |
| **Deallocated** | | **$6.39/mo** |

### 3.3 Runway — the standing cost dominates

**Confirmed 2026-09-21 from the portal: $100 of $100 remaining, 365 days left,
expires 2027-09-19, September spend $0.00.** Nothing has been consumed.

The free-tier "750 hours of B1s" shown on the Education blade is **unusable**:
`B1s` and `B1ms` are `NotAvailableForSubscription` in all five permitted regions,
as a `Location`-type restriction covering every zone. There is nowhere the free
VM can run.

| Usage | Cost | $100 lasts |
| --- | --- | --- |
| Always on | $44.79/mo | **~2.2 months** |
| Demos only, VM kept, ~8 h × 2 days/month | $76.68 standing (12 mo) + ~$10 compute | fits, with ~$13 spare |
| Demos only, VM **deleted** between demo seasons | ~$0 standing + compute only | comfortable |

The thing to understand: **over a year the standing cost is the dominant term.**
Keeping a deallocated VM for 12 months costs **$76.68** in disk and IP before a
single hour of compute. Levers, cheapest effort first:

| Lever | Saves | Cost of doing it |
| --- | --- | --- |
| Delete the public IP while deallocated | $3.65/mo | New IP on each rebuild; SSH target changes |
| 16 GiB disk (E3) instead of 32 GiB | $1.20/mo | Only safe once files live on R2 |
| **Delete the VM between demo seasons** | **all $6.39/mo** | ~30 min rebuild from this runbook + a DB dump |

If the demos are clustered — one in November, one in February, defense in April —
tearing the VM down between them is the most credit-efficient option by a wide
margin, and the runbook plus a dump on R2 makes the rebuild repeatable rather
than frightening.

### 3.4 Sizing note

The B-series v1 sizing debate is moot — none of it is available. B2als_v2's 4 GiB
runs MySQL + PHP-FPM + Nginx + Reverb + a queue worker comfortably. **Add 2 GB of
swap anyway**; it costs nothing and protects against a runaway build.

**Never run `npm run build` on the VM at any size** — the vite/rolldown build peaks
well past 1 GiB. Build locally or in CI and ship `public/build`.

---

## 4. Risk audit — what actually drains the credit

| Trap | Reality |
| --- | --- |
| **`sudo shutdown` from inside the VM** | Does **not** deallocate. Full compute keeps billing. Use "Stop (Deallocate)" in the portal or `az vm deallocate`. |
| Deallocated VM | Still bills **disk + static IP**. |
| Deleting a VM | Leaves **orphaned disks, NICs and public IPs** that keep billing. Delete the whole resource *group*. |
| Static IP | Standard SKU bills hourly whether attached or not. (Basic SKU retired Sept 2025.) |
| **Azure Database for MySQL** | ~$12–25/mo. **Do not use it** — self-host MySQL on the VM. |
| Defender for Cloud / Log Analytics | Silently auto-enables on some subscriptions. Check and disable. |
| Egress | 100 GB/mo free, then ~$0.087/GB. Not a realistic concern at capstone scale. |

**Set budget alerts at $25 / $50 / $75 on day one.**

Azure for Students requires **no credit card**, and when the credit is exhausted
services are **disabled, not billed**. You cannot accidentally run up a debt — but
you can accidentally end up with a dead site.

---

## 5. Findings from the codebase

Four things verified in the repo that change the plan.

### 5.1 The S3 driver is not installed

`config/filesystems.php` already defines the `s3` disk, but
`league/flysystem-aws-s3-v3` is **not** in `vendor/`. R2 needs it. That is a
dependency change and needs approval per `CLAUDE.md`.

### 5.2 R2 alone will not eliminate egress

The two private file paths are deliberately streamed **through PHP** so
authorization is checked on every byte:

- `ConversationController::image()` — `$disk->response(...)`, with a comment
  recording that a public URL previously leaked private conversation pictures.
- `AgreementTask::PROOF_DISK = 'public'`, `StudentCredentialController::DISK = 'local'`.

Move those to R2 unchanged and bytes travel **R2 → VM → browser**: a double hop,
and Azure egress is still paid. Eliminating egress requires **pre-signed URLs**,
which changes the model from "checked on every byte" to "a capability URL usable
by anyone holding it until it expires".

**There is precedent:** `ProjectAttachment::temporaryUrl(..., now()->addMinutes(15))`
already makes exactly that trade, and the model carries a per-row `disk` column.
But it is a security decision, not a config flip.

### 5.3 The upload ceiling is 100 MB per file

`config/uploads.php` — `max_image_kilobytes` and `max_document_kilobytes` both
default to 102400 KB. This is the strongest argument for R2, independent of cost:
a full VM disk is a **down site**, not a bigger bill.

### 5.4 Four processes must run continuously

Web, queue worker, scheduler, and Reverb (WebSockets). This requirement alone
rules out the serverless/free tier of every alternative platform.

---

## 6. Three traps specific to an intermittently-running VM

**a) Let's Encrypt will expire while the VM is off.** Certs last 90 days; Certbot
renews on a timer that never fires on a deallocated VM. Demo day → browser
security warning on the projector. **This is the most likely way the plan fails.**

**b) A dynamic IP changes on every boot.** Deallocate, restart, get a new address,
DNS now points nowhere. A static IP is what the ~$3.65/mo buys.

**c) The demo database will be empty.** If Azure is only on for demos, nothing ever
accumulates on it. A defense demo of an empty system is bad.

---

## 7. Recommended architecture

### 7.1 Use Cloudflare Tunnel on the VM instead of a public IP

| | Public IP + Certbot | Cloudflare Tunnel |
| --- | --- | --- |
| Static IP cost | ~$3.65/mo | **$0** |
| TLS certificates | Certbot; **expires if the VM sleeps** | **Cloudflare edge — nothing to renew** |
| Inbound firewall | 80, 443, 22 exposed | **No inbound ports at all** |
| IP changes on boot | Breaks DNS | **Irrelevant** |
| WebSockets (Reverb) | Works | **Works** — already proven on the PC host |
| Familiarity | New setup | **Already how `sdpc.tech` runs today** |

This removes traps (a) and (b) outright and saves ~$3.65/mo.

> **Caveat:** some capstone rubrics want to *see* a VM with a public IP and a
> conventional Nginx + Certbot setup. If the panel expects that, do it the
> conventional way and accept the cert-renewal chore.

### 7.2 Give Azure its own hostname

Point `demo.sdpc.tech` at the Azure tunnel and leave `sdpc.tech` on the PC. No DNS
edits on defense day, no TTL propagation gamble, and both can run at once.

### 7.3 Seed the demo database once, from a dump

A single `mysqldump` from the PC-hosted instance, restored to Azure when it is
built. One-way, one-time — completely different from ongoing sync, and it gives a
credible demo without coupling the two systems.

### 7.4 Shape

```
sdpc.tech        → Cloudflare Tunnel → PC host   (always on, real data, fallback)
demo.sdpc.tech   → Cloudflare Tunnel → Azure VM  (deallocated between demos)

Azure VM — Ubuntu 24.04 LTS, B1ms, 32 GiB SSD, NO public IP
  ├── Nginx → PHP-FPM 8.4
  ├── MySQL 8            (seeded once from a PC dump)
  ├── systemd: queue · scheduler · reverb · cloudflared
  └── no Certbot, no inbound NSG rules

Cloudflare R2 — shared by both hosts
  ├── public  → avatars, business logos
  └── private → message images, task proofs, credentials
```

All cloud bindings live in `.env` only — `FILESYSTEM_DISK`, `AWS_ACCESS_KEY_ID`,
`AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_URL`. No cloud-specific
paths in core services.

---

## 8. Data safety rules

1. **Never** run `migrate:fresh`, `db:seed` or `ResetUserContent` against the VM.
2. Deploys run `migrate --force` only. Schema travels; data does not.
3. Seeders and factories are for local and test environments only.
4. Take a `mysqldump` before any migration that drops or renames a column.
5. Local and VM databases are independent by design and are never reconciled.

---

## 9. Pre-flight check — do this first

**The Azure for Students credit is $100 _or_ 12 months, whichever comes first, and
the subscription on the STI account was already activated.** If that clock started
months ago there is less calendar left than the $100 suggests.

Before provisioning anything, check the portal for:

- [ ] Remaining credit balance
- [ ] Credit expiry date
- [ ] Whether any resources are already running and billing

That single number may change the whole plan.

---

## 10. Open decisions

Nothing gets built until these are settled.

| # | Decision | Recommendation |
| --- | --- | --- |
| 1 | Cloudflare Tunnel, or conventional public IP + Certbot? | **Tunnel**, unless the rubric requires a visible public IP |
| 2 | Separate `demo.sdpc.tech`, or flip `sdpc.tech` over? | **Separate hostname** |
| 3 | VM size | **B1ms** (2 GiB) + 2 GB swap |
| 4 | Private files: pre-signed R2 URLs, or keep streaming through PHP? | Pre-signed for zero egress; **streaming if the security model must not change** |
| 5 | May `league/flysystem-aws-s3-v3` be added? | Required for any R2 work |

### Rejected alternatives, and why

| Option | Why not |
| --- | --- |
| Vercel | Cannot host Laravel or Reverb. Inertia is server-routed — splitting gains nothing |
| Render free tier | Web services **spin down when idle** (~1 min cold start); free Postgres is reaped after 30 days |
| Railway | No real free tier; already running in parallel and due for retirement |
| Azure App Service F1 | 60 min CPU/day, no always-on, cannot run Reverb or a queue worker |
| Fly.io | Genuinely viable, but more ops complexity than one VM for no added benefit |
| Azure Database for MySQL | ~$12–25/mo for something the VM can host for free |
