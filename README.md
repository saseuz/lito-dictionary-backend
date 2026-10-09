# Lito Dictionary backend

Laravel 13 backend for the Lito E2E dictionary platform. It serves the protected React/Inertia Admin dashboard and a small public read-only lookup API used by the Chrome extension.

## Architecture

- **Admin dashboard:** React 19 + Inertia 3, protected by the `admin` session guard.
- **Admins:** `App\Models\Admin` uses UUIDs and Spatie Laravel Permission with one `admin`-guard role.
- **Users:** the default `User` model and `web` guard remain reserved for future end users, the public website, and browser-extension accounts.
- **Dictionary lookup:** `LookupWord` calls the shared `ProviderLookup` action, which normalizes provider responses into one internal shape.
- **Public API:** `GET /api/v1/dictionary/lookup?word=hello&language=en` validates input, applies rate limiting, and returns an allowlisted response. It does not expose Admin sessions, provider keys, or raw provider payloads.
- **Provider:** the public endpoint currently uses the server-side Merriam-Webster provider selected by `MERRIAM_WEBSTER_PRODUCT`. The extension never calls Merriam-Webster directly.

Public Merriam-Webster responses use live lookup with no permanent database read or write. This keeps the extension database-first boundary separate from the Admin dictionary storage while retention rights are clarified. Review [`docs/public-lookup-api.md`](docs/public-lookup-api.md) for the licensing and response contract.

## Local setup

From this directory:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure the database and server-side provider settings in `.env`:

```dotenv
DICTIONARY_PROVIDER=merriam_webster
MERRIAM_WEBSTER_PRODUCT=learners
MERRIAM_WEBSTER_LEARNERS_API_KEY=your-server-side-key
MERRIAM_WEBSTER_INTERMEDIATE_API_KEY=your-server-side-key
```

Keep provider keys in Laravel environment configuration. Never put them in an extension `VITE_` variable or return them from an API response.

Run the application:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

The Admin dashboard is available under `/admin`. The public lookup endpoint is available at `/api/v1/dictionary/lookup`.

## Admin provisioning

Create the initial Admin interactively:

```bash
php artisan db:seed --class=AdminRolesSeeder
php artisan app:create-admin
```

The command requires an existing `admin`-guard role and asks for the password through hidden input. It creates only an Admin account and assigns its selected role through the transaction-safe `AssignAdminRole` action. No public Admin registration route is enabled.

## Tests and formatting

Use the isolated test database configured by the project test harness:

```bash
php artisan test --compact
vendor/bin/pint
```

Dictionary feature tests cover normalization, provider fallback, attribution, public errors, rate limiting, and permanent Admin-side result reuse. The Chrome extension has its own README and build:

```bash
cd ../extension
npm ci
npm run build:dev
node --test tests/context-menu.test.mjs
```

Load `../extension/dist` as an unpacked extension from `chrome://extensions`.

## Safety rules

Do not run `migrate:fresh`, `db:wipe`, `reset`, or `rollback` against an application database. Do not edit vendor files. Use isolated test databases for tests. Public provider responses are currently live and non-persistent; do not add permanent external-data storage without reviewing the source terms and updating the documented policy.

Further architecture and milestone notes are in [`docs/architecture.md`](docs/architecture.md) and [`docs/project-status.md`](docs/project-status.md).
