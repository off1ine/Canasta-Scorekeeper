# Canasta Scorekeeper

A web application for tracking Canasta card game scores across sessions and rounds. Built with PHP, MySQL, and vanilla JavaScript.

## Features

- **Session & round management** — organize games into named sessions with configurable target scores
- **Score entry** — add game scores with automatic dealer rotation and round auto-completion
- **Meld minimums** — configurable score thresholds that determine minimum meld requirements
- **Statistics** — player stats, high/low scores, win tracking, dealer impact analysis, and Chart.js visualizations
- **Multi-user auth** — PIN-based login with admin/user roles
- **Mobile-first** — responsive design for use on phones during gameplay

## Requirements

- PHP 8.1+
- MySQL / MariaDB
- Web server (Apache/nginx)

## Setup

### 1. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE scorekeeper CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 2. Run the installer

From the project directory:

```bash
php install.php
```

The installer checks PHP requirements, prompts for DB host/name/user/password, applies `schema.sql`, and writes `db.local.php` (mode 0600). It is safe to re-run — schema statements use `CREATE TABLE IF NOT EXISTS` / `CREATE OR REPLACE VIEW`.

### 3. First run

Point your web server at the project directory and open the app. If no users exist, you'll be prompted to create the first admin account with a username and 4-digit PIN.

## Project structure

```
scorekeeper/
├── index.php              # Overview — score entry, scoreboard, round management
├── stats.php              # Player statistics and charts
├── setup.php              # Admin — sessions, players, thresholds, users
├── account.php            # Change PIN
├── login.php              # Authentication
├── logout.php             # Session cleanup
├── _head.php              # Shared <head> partial (fonts, CSS)
├── _header.php            # Shared top app bar
├── _nav.php               # Shared bottom tab bar
├── auth.php               # Auth helpers
├── config.php             # App config (loads config.local.php)
├── db.php                 # DB connection (loads db.local.php)
├── schema.sql             # Database schema
├── assets/
│   ├── app.css            # Stylesheet (design tokens + components)
│   ├── utils.js           # Shared helpers (api, esc, fmtNum, meldChipClass)
│   ├── overview.js        # Score entry logic
│   ├── setup.js           # Admin panel logic
│   └── stats.js           # Statistics and charts
└── api/
    ├── session.php         # Get session data (rounds, scores, totals)
    ├── sessions.php        # List/create sessions
    ├── session_update.php  # Update session settings
    ├── session_archive.php # Archive/restore sessions
    ├── round_start.php     # End current round, start next
    ├── game_add.php        # Add a game with scores
    ├── game_update.php     # Edit an existing game
    ├── player_stats.php    # Player statistics
    ├── chart_data.php      # Chart data for stats page
    ├── wins_over_time.php  # Cumulative wins by date
    ├── users.php           # User CRUD (admin)
    ├── meld_thresholds.php # Meld threshold CRUD
    └── change_pin.php      # Change user PIN
```

## Deployment

A GitHub Actions workflow in `.github/workflows/deploy.yml` rsyncs the project to a remote server on every push to `main`. `db.local.php` and `config.local.php` are excluded from deployment and must be created on the server (the installer handles this).

Required GitHub repository secrets:

- `DEPLOY_HOST` — SSH host
- `DEPLOY_USER` — SSH user
- `DEPLOY_SSH_KEY` — private SSH key
- `DEPLOY_PORT` — SSH port
- `DEPLOY_PATH` — remote directory to deploy into

## License

[MIT](LICENSE)
