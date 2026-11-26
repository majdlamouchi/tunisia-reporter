# Tunisia Reporter 🇹🇳

A web app where citizens report problems (potholes, fires, flooding, pollution, speed cameras...) on an interactive map of Tunisia.

**Stack:** PHP (PDO), MySQL, JavaScript, Leaflet.js + MarkerCluster, OpenStreetMap / Nominatim.

## Features
- Interactive map with clustered markers, filters by problem type and date
- Report a problem by clicking the map (address found automatically)
- Register / login (passwords hashed with `password_hash`, PHP sessions)
- Up/down voting on reports, view counter, comments and images in the database schema

## Run locally (XAMPP / WAMP)
1. Copy the project folder into `htdocs` (or `www`).
2. Start Apache and MySQL.
3. Import `tunisia_reporter.sql` with phpMyAdmin (it creates the `tunisia_reporter` database).
4. Copy `api/config.example.php` to `api/config.php` and set your DB credentials.
5. Open `http://localhost/tunisia_reporter_full/login.html` and create an account.

## API (`api/reports.php?action=...`)
| action | method | auth | description |
|---|---|---|---|
| `types` | GET | no | list problem types |
| `list` | GET | no | list reports (`types`, `start_date`, `end_date`) |
| `nearby` | GET | no | reports near `lat`, `lng` within `radius` km |
| `get` | GET | no | one report + comments + images (increments views) |
| `create` | POST | yes | create a report |
| `vote` | POST | yes | vote `up` / `down` (repeat to remove) |
| `register` / `login` / `logout` | POST | no | account handling |

## Known limitations
- No CSRF token yet and no login rate limiting
- Image upload is not implemented yet (table exists)
- The demo users in the SQL file have a placeholder password hash: register your own account
