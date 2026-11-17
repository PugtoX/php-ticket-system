# Ticket Desk — PHP + MySQL support queue

![ci](https://github.com/PugtoX/php-ticket-system/actions/workflows/ci.yml/badge.svg)

A small but complete internal tool: ticket list with filtering and pagination, a status
state machine enforced on the server, an audit trail, and JSON endpoints the browser uses
for no-reload status updates. Plain PHP 8.3 + PDO + MySQL, no framework, no build step.

![Ticket list](docs/tickets.png)

## What it does

- **Session login** with `password_hash` / `password_verify`, `session_regenerate_id()` on
  login, and a CSRF token on every state-changing request (form field `_csrf` or header
  `X-CSRF-Token`).
- **Ticket queue** — filter by status, search subject/body, paginate (page size whitelisted
  to 10/20/50, page clamped to the last real page).
- **Status state machine** — `open → in_progress → closed`, `closed → open`, everything else
  rejected with `409`. The UI only renders the buttons the state machine allows, so a
  stale page cannot offer an impossible action.
- **Audit trail** — every accepted transition writes `(actor, ticket, from, to)`, so
  "who closed this and when" is answerable.
- **JSON API** used by the page itself: status updates and stat counters refresh without a
  reload (vanilla `fetch`, no jQuery, no bundler).

```
GET  /api/tickets?status=&q=&page=&per_page=      list + counts
GET  /api/tickets/{id}                            detail + audit + allowed next states
POST /api/tickets/{id}/status  {"status":"..."}    transition (CSRF required)
GET  /api/stats                                   counters for the four cards
```

## Run it

### Docker

```bash
docker compose up --build
# app on http://localhost:8080, MySQL on 3307
```

### Without Docker (PHP 8.1+, MySQL 8 / MariaDB 10.4+)

```bash
mysql -u root -p -e "CREATE DATABASE tickets CHARACTER SET utf8mb4;"
mysql -u root -p tickets < db/01_schema.sql
mysql -u root -p tickets < db/02_seed.sql

export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=tickets DB_USER=tickets DB_PASS=tickets
php bin/create_user.php admin@example.com demo1234 admin
php bin/create_user.php agent@example.com demo1234 agent
php -S 127.0.0.1:8080 -t public server.php
```

`-t public` matters: the router returns `false` for real files so the built-in server can
stream CSS/JS from the document root.

## Tests

```
$ vendor/bin/phpunit --no-coverage
OK (5 tests, 13 assertions)

$ php tests/http_check.php http://127.0.0.1:8080
29 passed, 0 failed
```

`tests/TicketStatusTest.php` covers the transition table as a unit. `tests/http_check.php`
drives the running app over HTTP and asserts the things a unit test cannot see: anonymous
redirect, CSRF rejection (`419`), wrong password, login, ticket creation, the four legal
transitions plus the illegal ones (`409`), audit rows, stat totals, invalid input (`422`),
page clamping, and that a `' OR 1=1 --` search string does not widen the result set.

CI runs both against a real MySQL 8 service container (`.github/workflows/ci.yml`).

## Notes on the decisions

- **The state machine is one class** (`src/TicketStatus.php`), not scattered `if`s in the
  controller, the repo and the template. The template asks it which buttons to draw and the
  repo asks it whether a transition is legal, so the three layers cannot disagree.
- **No ORM.** The SQL is small enough to read, and the pagination query is explicit
  (`LIMIT/OFFSET` with a whitelisted page size, not string interpolation).
- **LIKE wildcards are escaped** before the search term is wrapped in `%…%`, so a user
  searching for `100%` does not become a wildcard.
- **Docker is for other people's machines.** This copy was verified on a Windows box where
  Docker Desktop could not start, using a local PHP 8.3 build plus MariaDB on port 3307 —
  the same code path Docker runs.

## Next step (not built)

`tickets.body` is the only thing the agent reads today. The obvious addition is a
**suggested-reply** endpoint: POST the ticket text to an LLM-backed RAG service and return a
draft the agent can edit. The seam is one route (`POST /api/tickets/{id}/suggest`) and one
button; the table already carries what the prompt would need.
