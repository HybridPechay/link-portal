# Link Portal

A private link directory: nest programs → areas → sub-areas → links as deep
as you like, then decide exactly which branches each user is allowed to see.
Built as plain PHP (no framework) + MySQL, dark mode, brand color `#125D32`.

## How access works

- Every folder (a program like BSBA, an area inside it, an area inside
  that…) and every link can be individually granted to a user.
- Granting a **folder** grants everything nested inside it automatically.
- Granting a single **link** grants just that link — its parent folders
  become visible only as far as needed to navigate down to it.
- This matches: *"user 2 has access to folder 2, and to Area 1 inside
  folder 1"* → user 2 sees all of folder 2, and only Area 1 (not Area 2 or
  other links) inside folder 1.
- Admin accounts always see everything and manage users/structure/access
  from `/admin`.

## Running with Docker

```bash
cp .env.example .env      # then edit .env and set real passwords
docker compose up -d --build
```

The app will be available at **http://localhost:8080**.

The database schema is loaded automatically the first time the `db`
container starts (via `schema.sql` mounted into MySQL's init directory).

Then open **http://localhost:8080/install.php** to create your first admin
account. This page refuses to run again once an admin exists, so it's safe
to leave in place.

To stop:
```bash
docker compose down       # add -v to also wipe the database volume
```

## Running without Docker (traditional hosting)

1. Create a MySQL/MariaDB database and import `schema.sql`.
2. Edit `config.php` with your DB host/name/user/password (or set the
   equivalent `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` environment
   variables — the app checks those first).
3. Point your web server's document root at this folder. Make sure it's
   served over **HTTPS** — the app sends `Strict-Transport-Security` and
   marks cookies `Secure` automatically once it detects HTTPS.
4. Visit `/install.php` to create the first admin account.

## Managing content

- **Admin → Folders & links**: build the tree (add programs, nested areas,
  and links; rename or delete any node — deleting a folder deletes
  everything nested inside it).
- **Admin → Users**: create accounts, promote/demote admins, disable
  accounts, reset passwords, delete accounts.
- **Admin → Permissions**: pick a user, check the folders/links they should
  see, save. Checking a folder auto-checks (and greys out) everything
  nested inside it in the UI, since that access is implied.

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt/argon2 depending on
  your PHP build) and transparently re-hashed if the default algorithm
  ever changes.
- All database queries use PDO prepared statements — no string-built SQL.
- Every state-changing form is protected by a CSRF token.
- Failed logins are throttled: 5 attempts per username+IP locks that
  combination out for 15 minutes (tune in `config.php`).
- Sessions use `HttpOnly`, `SameSite=Lax`, and `Secure` (once served over
  HTTPS) cookies, are regenerated on login, and idle out after 30 minutes.
- Security headers (CSP, X-Frame-Options, X-Content-Type-Options, etc.)
  are sent on every response.
- Only `http://` and `https://` URLs are accepted when adding links.
- `.htaccess` blocks direct downloads of `.sql`, `.env`, and other
  non-runtime files if you deploy on Apache.

Before going live: change every default password in `.env` / `config.php`,
confirm the site is only reachable over HTTPS, and take regular database
backups.
