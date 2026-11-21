# Deploying the public demo to EC2

This puts Aviate online at **https://aviate.glofly.app** on a single small EC2 instance, with HTTPS and demo mode on. Every command below runs on the server unless it says otherwise.

What you end up with:

```
Visitor ──HTTPS──▶ Caddy (certificate, port 443) ──▶ web (nginx) ──▶ api (Laravel) ──▶ db (PostgreSQL)
                                                                 └─▶ reverb (live updates)
                   + queue (sends live events) + scheduler (nightly demo reset)
```

## 1. AWS setup (console)

1. **Instance:** EC2 → Launch instance → **Ubuntu 24.04**, **t3.micro**, **20 GB gp3** storage, with a key pair you've saved.
2. **Security group (inbound rules):**

   | Type | Port | Source |
   |---|---|---|
   | SSH | 22 | My IP |
   | HTTP | 80 | Anywhere (0.0.0.0/0) |
   | HTTPS | 443 | Anywhere (0.0.0.0/0) |

   Port 80 has to be open even though the site is HTTPS-only: Let's Encrypt uses it to check you own the domain, and Caddy uses it to redirect to HTTPS.
3. **Elastic IP:** EC2 → Network & Security → Elastic IPs → Allocate → Actions → Associate with the instance.
4. **DNS:** wherever `glofly.app`'s DNS lives (Route 53 hosted zone or your registrar), add:

   | Name | Type | Value | TTL |
   |---|---|---|---|
   | `aviate` | A | your Elastic IP | 300 |

   Check from your own computer that it points at the server before step 5: `nslookup aviate.glofly.app`.

## 2. Prepare the server

Connect (EC2 → Instances → Connect, or `ssh -i your-key.pem ubuntu@YOUR_ELASTIC_IP`), then:

```bash
# Docker
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker ubuntu
exit    # log out and back in so the docker group applies
```

```bash
# 2 GB of swap: a t3.micro's 1 GB isn't enough to build the app
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Install security updates automatically (Ubuntu usually has this on already)
sudo apt-get install -y unattended-upgrades
```

## 3. Get the code

```bash
git clone https://github.com/chipmullins/aviate2.git
cd aviate2
```

If the repo is private, GitHub will ask for a username and password. Use your GitHub username and a [fine-grained personal access token](https://github.com/settings/personal-access-tokens) with read-only **Contents** access to `aviate2` as the password.

## 4. Configure

```bash
./setup.sh      # creates .env with fresh random secrets
nano .env
```

Change these lines (leave the secrets alone):

```ini
COMPOSE_PROFILES=https
AVIATE_DOMAIN=aviate.glofly.app
WEB_BIND=127.0.0.1

DEMO_MODE=true
DEMO_RESET_AT=04:00

APP_URL=https://aviate.glofly.app
WEB_PORT=8080
SANCTUM_STATEFUL_DOMAINS=aviate.glofly.app
REVERB_ALLOWED_ORIGINS=aviate.glofly.app
SESSION_SECURE_COOKIE=true

APP_ENV=production
APP_DEBUG=false
SEED_DEMO_DATA=true
COMPOSER_NO_DEV=1
```

In nano: Ctrl+O, Enter to save, Ctrl+X to exit.

What each setting does:
- `COMPOSE_PROFILES=https` starts Caddy, which gets the HTTPS certificate for `AVIATE_DOMAIN`.
- `WEB_BIND=127.0.0.1` keeps nginx reachable only from the server itself, so everything from outside goes through Caddy.
- `DEMO_MODE=true` adds the one-click "Try the demo" buttons, locks the sample accounts' settings, and wipes and re-seeds everything nightly at `DEMO_RESET_AT` (Eastern time).

## 5. Start it

```bash
docker compose up -d --build
```

The first build takes **15–20 minutes** on a t3.micro: it installs the PHP and Node packages and builds the Angular app, mostly in swap. Watch progress with:

```bash
docker compose ps          # every service should say "running" (api: "healthy")
docker compose logs -f caddy
```

When Caddy logs `certificate obtained successfully`, open **https://aviate.glofly.app**.

## 6. Check it works

- [ ] The padlock shows in the browser, and `http://aviate.glofly.app` redirects to `https://`.
- [ ] "Try the demo" → **Maya Chen** signs you in to a feed with posts.
- [ ] In a private window, try the demo as **Marcus Webb** and message Maya: it appears in Maya's window without refreshing.
- [ ] Settings shows "locked on shared demo accounts".
- [ ] The backend tests pass: `docker compose run --rm api php artisan test` (only works if you built with `COMPOSER_NO_DEV=0`; it's easier to run them on your own computer).

## Everyday operations

| Task | Command (in `~/aviate2`) |
|---|---|
| Deploy new code | `git pull && docker compose up -d --build` |
| See what's running | `docker compose ps` |
| Logs | `docker compose logs -f api` (or `caddy`, `web`, `reverb`, `queue`, `scheduler`) |
| Reset the demo now | `docker compose exec api php artisan aviate:reset-demo --force` |
| Restart everything | `docker compose restart` |
| Stop (keeps data) | `docker compose down` |
| Free disk space from old builds | `docker system prune -f` |

The nightly reset deletes every account, post, message and upload, then restores the sample crew. It only runs when `DEMO_MODE=true`; the command refuses to run otherwise.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Caddy logs certificate errors / "timeout during connect" | DNS doesn't point at the Elastic IP yet, or port 80/443 isn't open in the security group. Fix, then `docker compose restart caddy`. |
| Browser says the site can't be reached | Same as above; also check `docker compose ps` shows `caddy` running. |
| Login spins, then "Session expired" / 419 | `SANCTUM_STATEFUL_DOMAINS` must exactly match the address in the browser (`aviate.glofly.app`). Change `.env`, then `docker compose up -d`. |
| Messages only show after refreshing | `REVERB_ALLOWED_ORIGINS` must be `aviate.glofly.app`. Check `docker compose logs reverb`. |
| Build stops with "Killed" | Out of memory: check the swap is on (`free -h` shows 2.0G under Swap). |
| `api` never becomes healthy | `docker compose logs api` shows the error; send it over. |

## Costs (US East, on-demand)

About **$13 a month** if left running: t3.micro ≈ $7.60, public IPv4 ≈ $3.65, 20 GB disk ≈ $1.60. New AWS accounts' free credits cover this.

To take the demo down completely, terminate the instance **and release the Elastic IP** (an unattached Elastic IP still costs $0.005/hour), then delete the `aviate` DNS record.
