# SQLite-only tests

Pest always uses SQLite `:memory:` via `.env.testing` and `phpunit.xml`. Never point tests at MySQL. Do not change `DB_CONNECTION` or `DB_DATABASE` in the test env to mysql, and do not assert that runtime drivers are Redis or MySQL. Leave `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, and `APP_EVENTS_CACHE` pointed at missing `bootstrap/cache/*.testing.php` files so a local `optimize` / `config:cache` / `route:cache` cannot leak MySQL config or stale routes into Pest.
