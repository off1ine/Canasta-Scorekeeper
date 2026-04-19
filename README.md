# Canasta Scorekeeper

*Auf [Deutsch lesen](#canasta-scorekeeper-deutsch) ↓*

A web application for tracking **Canasta** and **Rommé** (German Rummy) card game scores across sessions and rounds. Built with PHP, MariaDB/MySQL, and vanilla JavaScript — no framework, no build step.

## Features

- **Two game types** — Canasta (high-score-wins, reach target) and Rommé (low-score-wins, exceed ceiling). Game type is chosen on session creation and fixed for the life of the session.
- **Session & round management** — organize games into named sessions with configurable target scores and automatic round completion.
- **Score entry** — add game scores with automatic dealer rotation and round auto-completion when the target is reached (Canasta) or the ceiling is exceeded (Rommé).
- **Meld minimums** — Canasta uses per-score-bracket thresholds (different minimum melds depending on current standing); Rommé uses a single fixed meld minimum per session.
- **Statistics** — player stats, high/low scores, win tracking, dealer impact analysis, and Chart.js visualizations, ordered appropriately per game type.
- **Multi-user auth** — PIN-based login with admin/user roles.
- **Bilingual UI** — English and German (informal "du"), resolved from session → cookie → `Accept-Language` → English default.
- **Mobile-first** — responsive design for use on phones during gameplay.

## Requirements

- PHP 8.1+
- MariaDB / MySQL
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

The installer checks PHP requirements, prompts for DB host/name/user/password (password entry is masked), applies `schema.sql`, and writes `db.local.php` (mode 0600). It is safe to re-run — schema statements use `CREATE TABLE IF NOT EXISTS` / `CREATE OR REPLACE VIEW`.

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
├── helpers.php            # Shared domain logic (round winner, target checks)
├── i18n.php               # Locale resolution and translation helper t()
├── schema.sql             # Database schema
├── lang/
│   ├── en.json            # English strings
│   └── de.json            # German strings (informal "du")
├── assets/
│   ├── app.css            # Stylesheet (design tokens + components)
│   ├── utils.js           # Shared helpers (api, esc, fmtNum, t, meldChipClass)
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
    ├── meld_thresholds.php # Meld threshold CRUD (Canasta)
    ├── locale.php          # Switch UI language
    └── change_pin.php      # Change user PIN
```

## Game-type differences at a glance

| Aspect            | Canasta                                    | Rommé                                  |
|-------------------|--------------------------------------------|----------------------------------------|
| Winner            | Highest total                              | Lowest total                           |
| Round ends when   | Any player reaches the target (`≥ target`) | Any player exceeds the ceiling (`> target`) |
| Meld minimums     | Per-score-bracket thresholds               | Single fixed threshold per session     |
| Stats ordering    | Totals descending                          | Totals ascending                       |

## License

[MIT](LICENSE)

---

# Canasta Scorekeeper (Deutsch)

*[Read in English](#canasta-scorekeeper) ↑*

Eine Web-Anwendung zum Festhalten von Spielständen für **Canasta** und **Rommé** über mehrere Sessions und Runden hinweg. Entwickelt mit PHP, MariaDB/MySQL und Vanilla-JavaScript — ohne Framework, ohne Build-Schritt.

## Funktionen

- **Zwei Spielarten** — Canasta (wer am meisten hat, gewinnt; Zielpunktzahl erreichen) und Rommé (wer am wenigsten hat, gewinnt; Obergrenze überschreiten). Die Spielart wird beim Anlegen der Session festgelegt und ist danach nicht mehr änderbar.
- **Session- und Rundenverwaltung** — Spiele werden in benannten Sessions mit konfigurierbarer Zielpunktzahl organisiert; Runden werden automatisch abgeschlossen.
- **Punkteingabe** — Spiele mit automatischer Geber-Rotation erfassen; Runden enden automatisch, sobald das Ziel erreicht (Canasta) bzw. die Obergrenze überschritten wird (Rommé).
- **Meld-Mindestwerte** — Canasta nutzt Schwellwerte pro Punktestand (unterschiedliche Mindestmelds je nach aktueller Lage); Rommé nutzt einen festen Mindestwert pro Session.
- **Statistiken** — Spieler-Statistiken, Höchst- und Niedrigstwerte, Siegverfolgung, Geber-Einfluss und Chart.js-Visualisierungen, jeweils passend zur Spielart sortiert.
- **Mehrbenutzer-Login** — PIN-basierte Anmeldung mit Admin- und Benutzerrollen.
- **Zweisprachige Oberfläche** — Deutsch (informelles "du") und Englisch, Reihenfolge: Session → Cookie → `Accept-Language` → Englisch als Fallback.
- **Mobile First** — responsives Design für die Nutzung am Handy während des Spiels.

## Voraussetzungen

- PHP 8.1+
- MariaDB / MySQL
- Webserver (Apache/nginx)

## Einrichtung

### 1. Datenbank anlegen

```bash
mysql -u root -p -e "CREATE DATABASE scorekeeper CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 2. Installer ausführen

Aus dem Projektverzeichnis:

```bash
php install.php
```

Der Installer prüft die PHP-Voraussetzungen, fragt nach Host, Datenbankname, Benutzer und Passwort (die Passworteingabe wird maskiert), spielt `schema.sql` ein und schreibt `db.local.php` (Modus 0600). Das Skript ist idempotent und kann gefahrlos erneut ausgeführt werden — das Schema verwendet `CREATE TABLE IF NOT EXISTS` bzw. `CREATE OR REPLACE VIEW`.

### 3. Erster Start

Den Webserver auf das Projektverzeichnis zeigen lassen und die Anwendung öffnen. Existiert noch kein Benutzer, wirst du aufgefordert, den ersten Admin-Account mit Benutzername und 4-stelliger PIN anzulegen.

## Projektstruktur

```
scorekeeper/
├── index.php              # Übersicht — Punkteingabe, Tableau, Rundenverwaltung
├── stats.php              # Spieler-Statistiken und Diagramme
├── setup.php              # Admin — Sessions, Spieler, Schwellwerte, Benutzer
├── account.php            # PIN ändern
├── login.php              # Anmeldung
├── logout.php             # Session beenden
├── _head.php              # Geteiltes <head>-Partial (Fonts, CSS)
├── _header.php            # Geteilte obere Leiste
├── _nav.php               # Geteilte untere Tab-Leiste
├── auth.php               # Auth-Helfer
├── config.php             # App-Konfiguration (lädt config.local.php)
├── db.php                 # DB-Verbindung (lädt db.local.php)
├── helpers.php            # Geteilte Domänenlogik (Rundensieger, Ziel-Checks)
├── i18n.php               # Sprachauflösung und Übersetzungs-Helfer t()
├── schema.sql             # Datenbankschema
├── lang/
│   ├── en.json            # Englische Texte
│   └── de.json            # Deutsche Texte (informelles "du")
├── assets/
│   ├── app.css            # Stylesheet (Design-Tokens + Komponenten)
│   ├── utils.js           # Geteilte Helfer (api, esc, fmtNum, t, meldChipClass)
│   ├── overview.js        # Punkteingabe-Logik
│   ├── setup.js           # Admin-Panel-Logik
│   └── stats.js           # Statistiken und Diagramme
└── api/
    ├── session.php         # Session-Daten abrufen (Runden, Spielstände, Summen)
    ├── sessions.php        # Sessions auflisten/anlegen
    ├── session_update.php  # Session-Einstellungen aktualisieren
    ├── session_archive.php # Session archivieren/wiederherstellen
    ├── round_start.php     # Aktuelle Runde beenden, nächste starten
    ├── game_add.php        # Spiel mit Punkten hinzufügen
    ├── game_update.php     # Bestehendes Spiel bearbeiten
    ├── player_stats.php    # Spieler-Statistiken
    ├── chart_data.php      # Diagrammdaten für die Statistik-Seite
    ├── wins_over_time.php  # Kumulative Siege nach Datum
    ├── users.php           # Benutzer-CRUD (Admin)
    ├── meld_thresholds.php # Meld-Schwellwerte-CRUD (Canasta)
    ├── locale.php          # Oberflächensprache wechseln
    └── change_pin.php      # PIN ändern
```

## Unterschiede der Spielarten auf einen Blick

| Aspekt              | Canasta                                         | Rommé                                       |
|---------------------|-------------------------------------------------|---------------------------------------------|
| Sieger              | Höchste Gesamtpunktzahl                         | Niedrigste Gesamtpunktzahl                  |
| Runde endet wenn    | Ein Spieler das Ziel erreicht (`≥ Ziel`)        | Ein Spieler die Obergrenze überschreitet (`> Ziel`) |
| Meld-Mindestwerte   | Schwellwerte pro Punktestand                    | Ein fester Wert pro Session                 |
| Statistik-Sortierung| Gesamtpunktzahl absteigend                      | Gesamtpunktzahl aufsteigend                 |

## Lizenz

[MIT](LICENSE)
