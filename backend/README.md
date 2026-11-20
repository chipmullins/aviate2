# Aviate API (Laravel)

The JSON API, WebSocket broadcasting and database layer for Aviate. See the [main README](../README.md) for setup.

Useful commands (inside Docker: prefix with `docker compose exec api`):

```bash
php artisan test            # run the test suite (in-memory SQLite)
php artisan migrate         # apply migrations
php artisan db:seed         # add demo crew (password: aviate-demo-1)
php artisan route:list --path=api
```

All endpoints live in `routes/api.php`. Every route except register, login, and two public config lookups requires a signed-in session.
