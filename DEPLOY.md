# Deploying Aviate

These are my notes from putting the demo on AWS at https://aviate.glofly.app. It runs on one small EC2 instance with Docker Compose, and Caddy handles HTTPS.

## The server

I used a t3.micro running Ubuntu 24.04 with a 20 GB disk. In the security group I opened ports 80 and 443 to everyone and kept SSH limited to my own IP. Port 80 has to stay open even though the site is HTTPS only: Let's Encrypt uses it to verify the domain, and Caddy uses it to redirect to HTTPS.

I allocated an Elastic IP and associated it with the instance so the address doesn't change when the instance restarts. Then I added an A record for `aviate` in the `glofly.app` DNS, pointing at that IP. Before going further, it's worth checking that it resolves:

```
nslookup aviate.glofly.app
```

## Setting up the instance

I connected through Session Manager, which logs you in as `ssm-user` rather than `ubuntu`, in a plain `sh` shell outside your home folder. So the first thing I do in each session is:

```bash
bash
cd ~
```

Install Docker:

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
```

The `usermod` line matters. Docker kept giving me "permission denied" until I realized the install had added `ubuntu` to the docker group, not `ssm-user`. Using `$USER` covers whichever account you're logged in as.

A t3.micro only has 1 GB of memory, which isn't enough to build the Angular app, so I added 2 GB of swap:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

## Getting the code and configuring it

```bash
git clone https://github.com/chipmullins/aviate2.git
cd aviate2
./setup.sh
```

`setup.sh` creates `.env` and fills in the app key, database password and Reverb keys with random values. It won't overwrite an existing `.env`.

The defaults are for running locally, so the server needs a few changes:

```bash
sed -i \
  -e 's|^DEMO_MODE=.*|DEMO_MODE=true|' \
  -e 's|^APP_URL=.*|APP_URL=https://aviate.glofly.app|' \
  -e 's|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=aviate.glofly.app|' \
  -e 's|^REVERB_ALLOWED_ORIGINS=.*|REVERB_ALLOWED_ORIGINS=aviate.glofly.app|' \
  -e 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' \
  -e 's|^APP_ENV=.*|APP_ENV=production|' \
  .env

cat >> .env <<'EOF'
COMPOSE_PROFILES=https
AVIATE_DOMAIN=aviate.glofly.app
WEB_BIND=127.0.0.1
EOF
```

What these do:
- `COMPOSE_PROFILES=https` turns on the Caddy container.
- `AVIATE_DOMAIN` is the domain Caddy gets a certificate for.
- `WEB_BIND=127.0.0.1` keeps nginx private to the server, so all outside traffic has to come through Caddy.
- `DEMO_MODE=true` adds the one-click demo logins, locks the sample accounts' settings, and resets everything nightly.

Check them before starting anything:

```bash
grep -E '^(COMPOSE_PROFILES|AVIATE_DOMAIN|WEB_BIND|DEMO_MODE|APP_URL)=' .env
```

I learned that one the hard way. My first start came up without Caddy because these settings never made it into `.env`, and `docker compose ps` showed nginx open on `0.0.0.0` with no `caddy` container at all.

## Starting it

```bash
docker compose up -d --build
```

The first build took around 15 to 20 minutes on the t3.micro, mostly the Angular build running in swap. After that, restarts take seconds.

```bash
docker compose ps
docker compose logs -f caddy
```

Everything should be running, with `api` marked healthy. Once Caddy logs that it obtained the certificate, the site is live.

To run the backend tests on the server:

```bash
docker compose run --rm api php artisan test
```

## Day to day

Deploying changes:

```bash
cd ~/aviate2
git pull
docker compose up -d --build
```

Some other commands I use:

```bash
docker compose logs -f api          # API logs (or caddy, reverb, queue, scheduler)
docker compose exec api php artisan aviate:reset-demo --force   # reset the demo now
docker compose restart              # restart everything
docker system prune -f              # clear out old build images
```

The demo resets itself every night at 4 AM Eastern. The reset command only works when `DEMO_MODE=true`, so it can't wipe a real database by accident.

## If something's wrong

- **Caddy can't get a certificate:** DNS isn't pointing at the Elastic IP yet, or port 80 or 443 is closed in the security group. Fix it, then `docker compose restart caddy`.
- **Login fails or says the session expired:** `SANCTUM_STATEFUL_DOMAINS` has to match the domain in the browser exactly.
- **Messages only show up after a refresh:** check `REVERB_ALLOWED_ORIGINS`, and look at `docker compose logs reverb`.
- **The build gets killed:** it ran out of memory. Make sure swap is on with `free -h`.
