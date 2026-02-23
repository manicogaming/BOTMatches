# BOTMatches

A CS:GO bot match statistics website and SourceMod plugin, adapted from [SQL Matches](https://github.com/NexusHub/Sourcemod-SQLMatches/tree/master/web%20server) by DistrictNine.Host.

This version is designed for bot matches and may not work properly with real players.

## Pages

- **index.php** — Match list with search, pagination, and AJAX auto-update notifications
- **scoreboard.php** — Match scoreboard with HLTV 2.0 ratings, ADR, KDR, and sortable columns
- **player.php** — Individual player career stats aggregated across all matches
- **leaderboard.php** — Player rankings by HLTV 2.0 rating (minimum match threshold, file-cached)
- **teams.php** — Valve-inspired team rankings with factor breakdown, form, win rate, and rank changes

## Team Ranking Model

The team ranking system is based on [Valve's Regional Standings (VRS)](https://github.com/ValveSoftware/counter-strike_regional_standings), adapted for bot matches (no prize money or LAN factors).

### Factors

| Factor | Description | VRS Equivalent |
|---|---|---|
| Strength of Schedule | Top 10 wins weighted by opponent Elo at time of defeat | Bounty Collected |
| Opponent Network | Distinct opponents defeated, weighted by recency | Opponent Network |
| Consistency | Top 10 recent wins, weighted by recency | LAN Wins |
| Head-to-Head | Elo adjustments from direct match results (uncapped) | H2H Adjustments |

### Scoring

- **Starting Rank Value** = 400 + (normalized average of 3 factors × 1600)
- **Final Rank Value** = Starting Rank Value + Head-to-Head Adjustments
- Base range is 400–2000, with H2H pushing beyond in either direction

### Active-Day Decay

Instead of real calendar time, decay is based on **active days** — calendar days where at least one match was played on the server.

- Full value for 5 active days
- Exponential decay from active day 5 to 60
- Fully decayed after 60 active days
- **Server off = zero decay** (the clock only ticks when matches are played)

This prevents rankings from changing while the server is inactive (e.g. WAMP turned off between tournament sessions).

## Configuration

### config.php

| Setting | Default | Description |
|---|---|---|
| `$servername` | `localhost` | Database host |
| `$username` | `root` | Database user |
| `$password` | *(empty)* | Database password |
| `$database` | `sql_matches` | Database name |
| `$page_title` / `$site_name` | `BOTMatches` | Site title shown in header and browser tab |
| `$max_matches` | `25` | Matches per page on index |
| `$leaderboard_min_matches` | `100` | Minimum matches for a player to appear on the leaderboard |
| `$leaderboard_cache_seconds` | `300` | Leaderboard cache TTL (seconds) |
| `$teams_cache_seconds` | `300` | Team rankings cache TTL (seconds) |
| `$teams_min_matches` | `10` | Minimum matches for a team to appear in rankings |

### Other Files

- **maps.php** — Map name to image path mapping array (add new maps here)
- **functions.php** — Shared helpers: HLTV 2.0 calculation, page rendering, team ranking model
- **head.php** — Navigation header with active page highlighting
- **api_latest.php** — JSON endpoint returning latest match ID (used by AJAX polling)

### Cache Files (auto-generated)

- `cache_leaderboard.json` — Player leaderboard cache. Delete to force recalculation.
- `cache_teams.json` — Team rankings cache. Delete to force recalculation.

## Plugin (sqlmatch.sp)

SourceMod plugin that records match data to MySQL at match end.

### Features

- Records kills, assists, deaths, damage, 3k/4k/5k multi-kills per player
- KAST round tracking with trade kill detection (5-second window)
- SQL injection prevention via `SQL_EscapeString` on all user-controlled strings
- Transaction-based inserts with success/error logging
- Entity guards for `cs_team_manager` and `cs_player_manager`
- Game mode validation (competitive only: `game_mode=1, game_type=0`)
- Skips matches with more than 5 players per team (with logging)
- All client arrays reset on connect to prevent stale data
- Game mode re-checked on `OnMapStart`
- Database indexes on `match_id` and `(match_id, name)` for query performance

### Database Tables

**sql_matches_scoretotal** — One row per match

| Column | Type | Description |
|---|---|---|
| match_id | bigint (PK, auto) | Unique match identifier |
| timestamp | timestamp | When the match was recorded |
| team_2 | int | T-side score |
| team_2_name | varchar(128) | T-side team name |
| team_3 | int | CT-side score |
| team_3_name | varchar(128) | CT-side team name |
| map | varchar(128) | Map played |

**sql_matches** — One row per player per match

| Column | Type | Description |
|---|---|---|
| match_id | bigint | References scoretotal match_id |
| name | varchar(65) | Player name |
| team | int | Team number (2=T, 3=CT) |
| kills | int | Total kills |
| assists | int | Total assists |
| deaths | int | Total deaths |
| 5k / 4k / 3k | int | Multi-kill round counts |
| damage | int | Total damage dealt |
| kastrounds | int | Rounds with Kill, Assist, Survived, or Traded |

### Installation

1. Compile `sqlmatch.sp` and place the `.smx` in `addons/sourcemod/plugins/`
2. Add a `sql_matches` entry to `addons/sourcemod/configs/databases.cfg`
3. Tables are created automatically on plugin load

## Requirements

- PHP 7+ with MySQLi extension
- MySQL / MariaDB
- SourceMod 1.10+ (for plugin)
- CS:GO dedicated server (for plugin)
