# ProjectConnect

PHP + MySQL project sharing app (XAMPP style deployment).

## Setup

1. Copy the example environment file and fill in real credentials:

   ```bash
   cp .env.example .env
   ```

   `DB_USER` is required — the app refuses to start without it. Use a dedicated
   MySQL account with access to the `projectconnect` database only, not `root`.
   `.env` is git-ignored and must never be committed.

2. Create the schema from the command line (`setup.php` is not reachable over
   HTTP on purpose):

   ```bash
   php setup.php
   ```

   To also seed the demo accounts and sample projects, set
   `SEED_DEMO_PASSWORD` (min. 8 characters) in `.env` before running it.

3. Serve the directory with Apache/PHP and open `index.php`.

## Notes

- `ALLOWED_ORIGINS` in `.env` is the comma separated list of origins allowed to
  call the API from a browser; it defaults to `http://localhost`.
- Authentication is session based. Every endpoint except `get_projects.php`
  requires a logged-in session and derives the acting user from it, so client
  supplied `user_id` values are ignored.
