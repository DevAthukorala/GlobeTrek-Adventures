# GlobeTrek Adventures

A responsive PHP and MySQL travel booking application for a Sri Lankan tour operator. Built from the supplied CSE5009 project report with customer, staff and administrator portals.

## Features

- **Visitors:** browse tours, search destinations, filter by experience and sort by price or duration; view itineraries, inclusions and per-person pricing.
- **Customers:** register, sign in, request trips, see booking history, cancel eligible bookings, submit enquiries and read staff replies.
- **Staff:** create/edit/archive/restore packages, delete packages without bookings, confirm/cancel bookings, mark past trips completed, and answer enquiries.
- **Administrators:** all staff features, user roles and account deactivation, date-filtered business reports and CSV export.
- **Booking integrity:** server-side price snapshots, integer monetary amounts, per-departure capacity checks inside database transactions, duplicate-submission protection and explicit status transitions.
- **Security:** password hashing, PDO prepared statements, role and ownership checks, CSRF tokens, session regeneration, output escaping, login throttling and restrictive browser headers.

The visual design uses dark navy, warm orange, responsive Bootstrap 5.3 components, a local compass mark and externally hosted travel photography. Bootstrap assets are vendored so the interface does not depend on a CDN. The six sample tours, prices and itineraries are illustrative content; replace them with the operator's real offerings. Some photography is illustrative rather than location-specific.

No online payments or email notifications are implemented, matching the report's scope. Confirmation and enquiry replies are available in the customer's account. No default user or shared administrator password is included.

## Requirements

- PHP 8.1+ with `pdo_mysql` and sessions (PHP 8.3 is the Docker/CI baseline).
- MySQL 8.0+ with InnoDB.
- Apache, WampServer, or PHP's development server.
- Python 3.10+ only for running the optional integration suite.

## Local setup (PHP / WampServer)

1. Clone this repository and create a database using a MySQL administration account:

   ```sql
   CREATE DATABASE globetrek CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'globetrek'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
     ON globetrek.* TO 'globetrek'@'localhost';
   ```

2. Copy `config.example.php` to `config.local.php` and enter your database credentials. The local file is ignored by Git. Alternatively, use the environment variables `DB_DSN`, `DB_USER` and `DB_PASSWORD`.

3. From the project directory, initialize the schema and sample catalogue:

   ```sh
   php scripts/setup.php
   ```

   This creates missing tables and adds sample tours only when the catalogue is empty. It does not create or drop the database and is not a schema migration framework.

4. Create your own administrator:

   ```sh
   php scripts/create-user.php admin you@example.com "Your Name"
   ```

   The CLI prompts for a password of 12–72 characters. Terminal input is visible, so use a private terminal. For automation, pass the password through the `GLOBETREK_USER_PASSWORD` environment variable instead of command-line arguments. The same command supports `staff` and `customer`; administrators can also promote registered users in the Users screen.

5. Start the local development server:

   ```sh
   php -S 127.0.0.1:8080 -t public
   ```

6. Open **http://127.0.0.1:8080** and sign in. Customer accounts can be registered through the website.

On Windows, add the PHP executable directory to PATH or replace `php` with its full path. Enable `extension=pdo_mysql` and the correct `extension_dir` in `php.ini`. With WampServer, create a virtual host whose document root is this repository's **public** directory. The site expects to be served at the host root, not in a URL subdirectory. Never expose the repository root as the document root: it contains configuration, SQL and CLI scripts.

## Docker alternative

Copy `.env.example` to `.env` and replace both database password placeholders with different random values. Then run:

```sh
docker compose up --build -d
docker compose exec web php scripts/setup.php
docker compose exec web php scripts/create-user.php admin you@example.com "Your Name"
```

Visit **http://127.0.0.1:8080**. MySQL persists in a named Docker volume and has no published host port. The web port binds to loopback for local development. `docker compose down` stops the services without deleting the database volume.

## Booking rules

- All prices are in LKR, stored as integer cents. A booking retains its original title and price if a package is later edited.
- Dates must be valid and between today and two years from today in the Asia/Colombo time zone. Each selected date represents one departure of a package.
- Capacity is the maximum party total per package/departure date. Pending and confirmed requests reserve capacity; cancelled requests release it. The package row is locked while availability is checked and a booking is inserted.
- Staff can move `pending → confirmed/cancelled` and `confirmed → completed/cancelled`. Future trips cannot be completed. Cancelled/completed bookings cannot be reopened.
- Customers may cancel their own pending/confirmed bookings through the departure date. No charge or refund is processed.
- Packages with booking history are archived rather than deleted. Users are deactivated rather than deleted, preserving business records.
- Reports filter by **travel date**. Confirmed/completed booking value is an operational measure, not collected revenue. Booking/traveller counts include every status and are labelled accordingly.
- Roles and active status are read from the database on every request. Permission changes apply to existing sessions. Administrators cannot demote or disable themselves.

## Tests

Use a **separate test database** and run the PHP server against it. The test suite creates uniquely named fixture users, packages, bookings and enquiries; it leaves these records in the test database for inspection. It does not connect to GitHub or use production accounts.

Set these environment variables in both the server terminal and test terminal:

```text
DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=globetrek_test;charset=utf8mb4
DB_USER=your_test_database_user
DB_PASSWORD=your_test_database_password
```

Create `globetrek_test`, then run `php scripts/setup.php` and `php -S 127.0.0.1:8080 -t public`. In the second terminal:

```sh
# bash
GLOBETREK_TEST=1 python tests/integration.py
```

```powershell
# PowerShell
$env:GLOBETREK_TEST = '1'
python tests/integration.py
```

Optional variables: `PHP_BIN` (PHP executable path) and `TEST_BASE_URL` (default `http://127.0.0.1:8080`). Run PHP syntax checks with:

```sh
find app public scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

The integration suite verifies public pages, registration and login, CSRF and access restrictions, trusted pricing, capacity, invalid dates, cancellation ownership, status transitions, package CRUD, escaped enquiry content, reply isolation, role updates, account deactivation and reporting. GitHub Actions runs the suite with MySQL 8 and PHP 8.3 on pushes and pull requests.

See [docs/TESTING.md](docs/TESTING.md) for the actual local verification record and remaining verification limits, and [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the schema and requirement mapping. Statements in the supplied report about prior UAT are not treated as evidence that this implementation has passed human acceptance testing.

## Project structure

```text
app/                 Shared database, authentication, view and booking functions
public/              Web document root and static assets
  admin/             User management and business reports
  staff/             Tour, booking and enquiry management
scripts/             CLI schema setup, sample data and user creation
tests/               Isolated database fixtures and HTTP regression suite
docs/                Architecture, requirement mapping and testing notes
database.sql         MySQL schema
config.example.php   Configuration template (copy locally)
compose.yaml         Optional local PHP/Apache + MySQL environment
```

For a public deployment, use HTTPS, keep PHP errors out of responses, protect database credentials, back up the database and use a runtime database account limited to SELECT/INSERT/UPDATE/DELETE after setup. Pending requests need staff review; automated expiry, payment processing, email delivery, password reset, audit logs and multi-departure scheduling are future work. Third-party images require Internet access. Large operational datasets will need dashboard pagination; catalogue pagination is already included.

## Third-party assets

Bootstrap 5.3.3 is included under its MIT license in `public/assets/vendor/BOOTSTRAP-LICENSE.txt`. Photo URLs point to Unsplash; no image binary or source report is included in this repository. See [docs/ASSETS.md](docs/ASSETS.md).
