# QA-ENV-01 — Isolated, Fail-Closed Test Database

Branch: `fix/qa-isolated-test-database` · base: `main`

Prevents a repeat of the incident where a PHPUnit run executed `migrate:fresh`
(through `RefreshDatabase`) against the development MySQL database
`pos_dashboard`.

---

## 1. Root cause (why `phpunit.xml` was not enough)

Three things combined to make the standard suite reach `pos_dashboard`:

1. **OS-level environment variables were set globally**, e.g.:

   ```
   APP_ENV=local
   DB_CONNECTION=mysql
   DB_DATABASE=pos_dashboard
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   ```

2. **`php artisan test` leaks its loaded `.env` into the PHPUnit child
   process.** The child then inherits `DB_CONNECTION=mysql`, etc.

3. **Laravel resolves `env()` from `$_SERVER` before `$_ENV`.** PHPUnit's
   `<env force="true">` only writes `putenv()` + `$_ENV`; it never touches
   `$_SERVER`. So even a *forced* `<env DB_CONNECTION="sqlite">` lost to the
   value already present in `$_SERVER`.

   Evidence (before the fix, with `phpunit.xml` already forcing sqlite):

   ```
   getenv(APP_ENV)='testing' | _ENV='testing' | _SERVER='local'
   getenv(DB_CONNECTION)='sqlite' | _SERVER(DB_CONNECTION)='mysql'
   ```

`RefreshDatabase` then ran `migrate:fresh` against whatever `config('database.*')`
resolved to — the dev DB.

---

## 2. Protection layers

### Layer 1 — Forced environment in both PHPUnit configs

`phpunit.xml` and `phpunit.p38concurrency.xml` now pin **every** variable twice:

* `<env name="..." value="..." force="true"/>` — overrides `putenv()` / `$_ENV`.
* `<server name="..." value="..."/>` — overrides `$_SERVER`, which Laravel reads
  first. `<server>` is applied unconditionally by PHPUnit, so this is what
  actually defeats an OS-level variable.

Keep the two lists in sync when adding variables.

### Layer 2 — Fail-closed guard before any migration

`tests/Support/TestDatabaseGuard.php`, invoked from `tests/TestCase.php`
(`refreshApplication()`), which Laravel calls **after the app boots but before
`setUpTraits()`** — i.e. before `RefreshDatabase`, `DatabaseMigrations` or
`DatabaseTruncation` can run a single statement.

If the resolved connection is not the approved target, it throws
`Tests\Support\UnsafeTestDatabaseException` with the resolved config and the
violations. Migrations never run.

Profiles are selected with the forced `QA_TEST_DB_PROFILE` variable:

| Profile         | Selected by                    | Accepted connection                                  |
| --------------- | ------------------------------ | ---------------------------------------------------- |
| `sqlite_memory` | `phpunit.xml` (default)        | `sqlite` + `:memory:`, empty `DB_URL`, `SESSION_DRIVER=array`, `APP_ENV=testing` |
| `p38_mysql`     | `phpunit.p38concurrency.xml`   | `mysql`/`mariadb` + `pos_p38_concurrency_test` only   |

Anything else is rejected. Any database whose name is `pos_dashboard`
(or starts with `pos_dashboard`), `production` or `prod` is rejected in **every**
profile, including `p38_mysql`.

### Layer 3 — Dedicated concurrency database

The P38 row-lock gate needs real MySQL locks. It is the only suite allowed to
use MySQL, it requires the dedicated `phpunit.p38concurrency.xml` config, and
the guard refuses any target other than `pos_p38_concurrency_test`.

---

## 3. Safe setup with two Git worktrees

Both worktrees share the same repository and the same machine-wide environment
variables. They must **never** share a test database.

* The application `.env` (dev) is irrelevant to tests: the standard suite is
  forced to SQLite `:memory:` and does not read `pos_dashboard` at all.
* Each worktree runs its tests in its own process with its own in-memory
  SQLite database — no shared state.
* The concurrency gate uses one dedicated MySQL database
  (`pos_p38_concurrency_test`) that is **not** the dev database. Create it once
  per machine:

  ```bash
  php -r "new PDO('mysql:host=127.0.0.1;port=3306','root','')->exec(
      'CREATE DATABASE IF NOT EXISTS pos_p38_concurrency_test
       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"
  ```

* Development commands (`php artisan migrate`, `db:seed`, `serve`) are the only
  things that should ever touch `pos_dashboard` — never a test run.

> If your shell exports `APP_ENV` / `DB_CONNECTION` / `DB_DATABASE`, that is now
> harmless for tests (layer 1 overrides `$_SERVER`), but it is still worth
> cleaning up for `artisan` commands.

---

## 4. Running the quality gates

Run from the worktree root.

```bash
# Standard suite + linters + static analysis (same as CI)
composer ci:check        # config:clear -> pint --test -> phpstan -> artisan test
composer test            # identical chain

# Individual gates
vendor/bin/phpunit -c phpunit.xml          # standard suite (SQLite :memory:)
vendor/bin/pint --test                     # code style
vendor/bin/phpstan analyse --memory-limit=1G
npm run build                              # front-end assets
git diff --check                           # whitespace

# MySQL row-lock concurrency gate (dedicated DB, see section 3)
vendor/bin/phpunit -c phpunit.p38concurrency.xml
```

To run a single class:

```bash
vendor/bin/phpunit -c phpunit.xml --filter=CustomersPageTest
```

---

## 5. What NOT to do

* Do **not** remove `force="true"` or the `<server>` mirrors from the PHPUnit
  configs.
* Do **not** add `DB_CONNECTION` / `DB_DATABASE` to `<env>` without a matching
  `<server>` entry.
* Do **not** point `phpunit.p38concurrency.xml` at anything other than
  `pos_p38_concurrency_test`.
* Do **not** disable or weaken `TestDatabaseGuard`.

---

## 6. Verification (isolated, never against `pos_dashboard`)

* `vendor/bin/phpunit -c phpunit.xml --filter=TestDatabaseGuardTest` — 9 tests
  covering acceptance (sqlite `:memory:`, dedicated P38 DB) and rejection (MySQL
  probe, file-backed SQLite, non-empty `DB_URL`, non-testing `APP_ENV`,
  `pos_dashboard`, arbitrary MySQL DB).
* A throwaway config pointing the standard profile at the harmless
  `pos_qa_guard_probe` MySQL database aborts in ~0.2s with
  `UnsafeTestDatabaseException` **before** any migration.
* `vendor/bin/phpunit -c phpunit.p38concurrency.xml` runs the concurrency gate
  against `pos_p38_concurrency_test` only.
