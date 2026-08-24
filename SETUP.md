# eDoc Local Dev Setup

## One-time setup (each teammate)

1. Install Docker Desktop (Windows/Mac) or Docker Engine + Docker Compose (Linux).
2. Clone the repo: `git clone <repo-url>`
3. Copy the site's PHP files into a `src/` folder in the repo root
   (this is what gets mounted into the web container).
4. Put the **schema-only** SQL dump into `db-init/` — name it something
   like `01-schema.sql`. Files in `db-init/` run automatically the first
   time the DB container starts.
5. From the repo root, run:
   ```
   docker-compose up -d
   ```

## What you get

- Website: http://localhost:8080
- phpMyAdmin: http://localhost:8081 (login: root / rootpass)
- MySQL: localhost:3306 (user: edoc_user / edoc_pass)

## Daily use

- Start: `docker-compose up -d`
- Stop: `docker-compose down`
- Rebuild after changes to docker-compose.yml: `docker-compose up -d --build`
- View logs: `docker-compose logs -f`

## Notes

- DO NOT put the full data dump (with real-looking patient info) into
  `db-init/` or commit it to git — schema only. Share the full dump
  privately (Drive link, direct file transfer) if actually needed.
- The `db_data/` folder is git-ignored — your local DB state won't be
  committed, so everyone starts from the same schema.
- This environment is completely separate from the live deployment
  server — safe to break, test, and experiment freely here.
