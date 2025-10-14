# HouzzHunt Dashboard Data Wiring

## Schema Detection
- Connects to the existing MySQL database using credentials from `includes/config.php` and the shared `hh_db()` PDO helper.
- During bootstrap (`config/datamap.php`) the table and column names are mapped after inspecting `INFORMATION_SCHEMA` with the recommended queries (see below).
- To refresh the map, run the following read-only statements in MySQL Workbench or CLI:
  ```sql
  SHOW DATABASES;
  SELECT DATABASE();
  SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
  ORDER BY TABLE_NAME, ORDINAL_POSITION;
  ```
  Update `config/datamap.php` so each logical entity matches the discovered schema.

## Updating the Data Map
- `config/datamap.php` centralises the UI ↔ table/column relationship.
- Adjust any column names directly in that file; repositories automatically consume the latest mapping on the next request.
- If a concept does not exist in your database, keep the key and set the optional columns to `null` so the repositories can guard against them gracefully.

## Date Ranges
- All dashboard endpoints accept `?range=` with presets: `last_7_days`, `last_30_days`, `last_month`, `last_quarter`.
- Update the default range exposed to the front-end in `index.php` (`$dashboardConfig['defaultRange']`).
- The helper `HouzzHunt\Support\DateRange::fromPreset()` contains the range logic if you need to add new presets.

## Authentication & Authorisation
- Reuses the existing session authentication in `includes/config.php` (`$_SESSION['loggedin']`).
- API controllers resolve the current user context via `api/bootstrap.php`, applying role-based visibility (`admin`, `manager`, `agent`).
- Repository queries respect the visibility filter; agents are restricted to their assigned leads, managers see their team, and admins can view all records.
- CSRF is inherited from existing forms; POST endpoints should call the existing `hh_verify_csrf()` helper before write operations.

## Front-end Wiring
- `public/js/dashboard.api.js` fetches live JSON from the API routes declared in `index.php` and renders KPI cards, charts, activity feed, heatmap, performance metrics, inventory table, and global search results without altering the layout.
- The script expects specific `data-*` attributes already present in the DOM; ensure any HTML updates keep those markers intact.

## Tests
- Feature tests live in `tests/` and rely on real database records (no seeding).
- Run the suite after configuring database credentials:
  ```bash
  composer install
  ./vendor/bin/phpunit
  ```
  Tests validate the structure of lead statistics and performance metrics responses.

## Notes
- No demo data is generated—ensure the production database is reachable before loading the dashboard.
- If your installation introduces new entities (e.g., channel partners table), extend the repositories/services to read them without altering existing schema.
- For additional ranges or widgets, add a new service/controller method and reference it from `public/js/dashboard.api.js`.
