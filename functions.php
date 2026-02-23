<?php
// HLTV 2.0 Rating Constants
define('HLTV2_KAST_MOD', 0.0073);
define('HLTV2_KPR_MOD', 0.3591);
define('HLTV2_DPR_MOD', -0.5329);
define('HLTV2_IMPACT_MOD', 0.2372);
define('HLTV2_IMPACT_KPR_MOD', 2.13);
define('HLTV2_IMPACT_APR_MOD', 0.42);
define('HLTV2_IMPACT_OFFSET_MOD', -0.41);
define('HLTV2_ADR_MOD', 0.0032);
define('HLTV2_OFFSET_MOD', 0.1587);

/**
 * Calculate HLTV 2.0 rating for a player.
 * Returns the rounded rating value, or 0.0 if rounds is 0.
 */
function calculateHLTV2($kills, $deaths, $assists, $damage, $kastrounds, $rounds) {
    if ($rounds <= 0) {
        return 0.0;
    }

    $KAST    = HLTV2_KAST_MOD * ($kastrounds / $rounds) * 100.0;
    $KPR     = HLTV2_KPR_MOD * $kills / $rounds;
    $DPR     = HLTV2_DPR_MOD * $deaths / $rounds;
    $ADR     = HLTV2_ADR_MOD * $damage / $rounds;
    $Impact  = HLTV2_IMPACT_MOD * ((HLTV2_IMPACT_KPR_MOD * ($kills / $rounds)) + (HLTV2_IMPACT_APR_MOD * ($assists / $rounds)) + HLTV2_IMPACT_OFFSET_MOD);

    $HLTV2 = $KAST + $KPR + $DPR + $Impact + $ADR + HLTV2_OFFSET_MOD;
    return round($HLTV2, 2);
}

/**
 * Calculate KDR (Kill/Death Ratio).
 */
function calculateKDR($kills, $deaths) {
    if ($deaths <= 0) {
        return (float)$kills;
    }
    return round($kills / $deaths, 2);
}

/**
 * Calculate ADR (Average Damage per Round).
 */
function calculateADR($damage, $rounds) {
    if ($rounds <= 0) {
        return 0;
    }
    return round($damage / $rounds, 0);
}

/**
 * Render the common HTML head and opening body/container.
 */
function renderPageStart($page_title) {
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.htmlspecialchars($page_title).'</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootswatch/4.1.2/darkly/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lato:400,700,400italic">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.5.2/animate.min.css">
    <link rel="stylesheet" href="assets/css/Search-Field-With-Icon.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* ── Navigation ── */
        .nav-links { text-align: center; margin-bottom: 10px; }
        .nav-links a { color: #aaa; margin: 0 12px; text-decoration: none; }
        .nav-links a:hover { color: #fff; text-decoration: none; }
        .nav-links a.active { color: #fff; font-weight: bold; }

        /* ── AJAX banner ── */
        .new-match-banner {
            display: none;
            text-align: center;
            padding: 8px;
            background-color: #375a7f;
            border-radius: 5px;
            margin-bottom: 10px;
            cursor: pointer;
        }
        .new-match-banner:hover { background-color: #4a6f94; }

        /* ── Sortable tables ── */
        .sortable th { cursor: pointer; user-select: none; }
        .sortable th:hover { color: #fff; }
        .sort-arrow { font-size: 10px; margin-left: 4px; }

        /* ── Missing map fallback ── */
        .missing-map-card {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 150px;
            background-color: #3e3e3f;
            border-radius: 25px;
            font-size: 16px;
            color: #ff6b6b;
        }

        /* ── Rating colors (shared across all pages) ── */
        .rating-good { color: #5cb85c; }
        .rating-bad { color: #d9534f; }

        /* ── Card header bars ── */
        .card-header-bar {
            padding: 4px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .card-header-bar h3 {
            font-size: 22px;
            margin: 0;
            text-transform: uppercase;
            color: #fff;
            text-align: center;
        }
        .card-header-bar.bg-primary { background-color: #375a7f; }
        .card-header-bar.bg-ct { background-color: #5b768d; }
        .card-header-bar.bg-t { background-color: #ac9b66; }

        /* ── Empty state messages ── */
        .empty-state {
            margin-top: 40px;
            text-align: center;
            font-size: 18px;
            color: #888;
        }

        /* ── Form badges (shared: teams + player) ── */
        .form-badge { display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 3px; font-size: 11px; font-weight: bold; margin: 0 1px; }
        .form-w { background-color: #5cb85c; color: #fff; }
        .form-l { background-color: #d9534f; color: #fff; }
        .form-d { background-color: #f0ad4e; color: #fff; }

        /* ── Team rankings ── */
        .team-row { border-bottom: 1px solid #444; padding: 12px 15px; }
        .team-row:hover { background-color: #333; }
        .team-rank { font-size: 24px; font-weight: bold; min-width: 40px; text-align: center; }
        .team-logo { width: 38px; height: 38px; margin-right: 12px; object-fit: contain; }
        .team-name { font-size: 18px; font-weight: bold; }
        .team-points { font-size: 20px; font-weight: bold; }
        .team-change-up { color: #5cb85c; font-size: 13px; }
        .team-change-down { color: #d9534f; font-size: 13px; }
        .team-change-same { color: #888; font-size: 13px; }
        .team-change-new { color: #5bc0de; font-size: 11px; font-weight: bold; }
        .team-stat-label { color: #888; font-size: 12px; }
        .team-stat-value { font-size: 14px; }

        /* ── Profile / content cards ── */
        .profile-card { background-color: #282828; border-radius: 8px; padding: 20px; margin-top: 15px; }
        .stat-box { text-align: center; padding: 10px; }
        .stat-value { font-size: 24px; font-weight: bold; color: #fff; }
        .stat-value-sm { font-size: 18px; font-weight: bold; color: #fff; }
        .stat-label { color: #888; font-size: 12px; text-transform: uppercase; }
        .section-title { font-size: 18px; font-weight: bold; color: #fff; margin: 25px 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #444; }
        .highlight-card { background-color: #333; border-radius: 5px; padding: 12px; text-align: center; margin-bottom: 10px; }
        .highlight-value { font-size: 22px; font-weight: bold; color: #5bc0de; }
        .highlight-label { font-size: 11px; color: #888; text-transform: uppercase; }

        /* ── Tabs ── */
        .tab-btn { background: none; border: none; color: #aaa; padding: 8px 16px; cursor: pointer; font-size: 14px; border-bottom: 2px solid transparent; }
        .tab-btn:hover { color: #fff; }
        .tab-btn.active { color: #fff; font-weight: bold; border-bottom: 2px solid #375a7f; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container" style="margin-top:20px;">';

    require("head.php");
}

/**
 * Render the common page footer with scripts.
 */
function renderPageEnd() {
    echo '
    </div>
    <a class="text-white" href="https://github.com/DistrictNineHost/Sourcemod-SQLMatches" target="_blank" style="position:fixed;bottom:0px;right:10px;">Developed by DistrictNine.Host</a>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.2/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/bs-animation.js"></script>
</body>
</html>';
}

/**
 * Format a timestamp for display.
 */
function formatTimestamp($timestamp) {
    if (empty($timestamp)) {
        return '';
    }
    $dt = new DateTime($timestamp);
    return $dt->format('d M Y, H:i');
}

/**
 * Sanitize and escape a string for safe HTML output.
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Convert unicode escape sequences to HTML entities.
 */
function unicode2html($str) {
    setlocale(LC_ALL, 'en_US.UTF-8');
    $str = preg_replace("/u([0-9a-fA-F]{4})/", "&#x\\1;", $str);
    return iconv("UTF-8", "ISO-8859-1//TRANSLIT", $str);
}

/**
 * Return CSS class for rating coloring.
 */
function ratingClass($rating) {
    return $rating >= 1.0 ? 'rating-good' : 'rating-bad';
}

/**
 * Return CSS class for W/L/D coloring.
 */
function wlClass($wl) {
    if ($wl === 'W') return 'rating-good';
    if ($wl === 'L') return 'rating-bad';
    return 'text-warning'; // Bootstrap amber for draws
}

/**
 * Get the latest match_id from the database.
 * Used for cache invalidation — one cheap query instead of time-based TTL.
 */
function getLatestMatchId($conn) {
    $result = $conn->query("SELECT MAX(match_id) AS latest FROM sql_matches_scoretotal");
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['latest'];
    }
    return 0;
}

/**
 * Parse a bot_rosters.txt VDF file and return array of active team names.
 * Returns empty array if file not found or unreadable (rankings will be unfiltered).
 *
 * VDF format:
 *   "Teams"
 *   {
 *       "Team Name"
 *       {
 *           "players"    "player1,player2,player3"
 *           "logo"       "ABC"
 *       }
 *   }
 */
function parseActiveRosters($filePath) {
    if (empty($filePath) || !file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        return [];
    }

    $teams = [];
    // Match top-level team name keys inside the "Teams" block
    // Pattern: tab + "TeamName" followed by newline + tab + {
    if (preg_match_all('/^\t"([^"]+)"\s*\n\t\{/m', $content, $matches)) {
        $teams = $matches[1];
    }

    return $teams;
}

/**
 * Parse bot_rosters.txt and return flat array of all active player names.
 * VDF "players" values are comma-separated: "player1,player2,player3"
 */
function parseActiveRosterPlayers($filePath) {
    if (empty($filePath) || !file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        return [];
    }

    $players = [];
    // Match: "players"    "name1,name2,name3"
    if (preg_match_all('/"players"\s+"([^"]+)"/', $content, $matches)) {
        foreach ($matches[1] as $playerList) {
            foreach (explode(',', $playerList) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $players[] = $name;
                }
            }
        }
    }

    return array_unique($players);
}

/**
 * Parse bot_rosters.txt and return full mapping: team name => ['players' => [...], 'logo' => '...']
 */
function parseActiveRostersFull($filePath) {
    if (empty($filePath) || !file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        return [];
    }

    $rosters = [];
    // Match each team block: "TeamName" { "players" "..." "logo" "..." }
    $pattern = '/^\t"([^"]+)"\s*\n\t\{([^}]+)\}/m';
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $teamName = $match[1];
            $block = $match[2];

            $players = [];
            if (preg_match('/"players"\s+"([^"]+)"/', $block, $pm)) {
                foreach (explode(',', $pm[1]) as $name) {
                    $name = trim($name);
                    if ($name !== '') $players[] = $name;
                }
            }

            $logo = '';
            if (preg_match('/"logo"\s+"([^"]+)"/', $block, $lm)) {
                $logo = $lm[1];
            }

            $rosters[$teamName] = ['players' => $players, 'logo' => $logo];
        }
    }

    return $rosters;
}

/**
 * ============================================================================
 * Valve-inspired team ranking model adapted for bot matches.
 * ============================================================================
 *
 * Based on Valve Regional Standings (VRS) structure:
 * - Strength of Schedule (replaces Bounty Collected): top 10 wins weighted by opponent rating
 * - Opponent Network: distinct opponents defeated
 * - Consistency (replaces LAN Wins): recent win count with recency weighting
 * - Head-to-Head: Elo adjustments from direct matches
 *
 * ACTIVE-DAY DECAY: Instead of real calendar time, decay is based on "active days" —
 * days where at least one match was played on the server. This means:
 * - Server off for 3 months = zero decay (no active days passed)
 * - A day with 30 matches = 1 active day (not 30 ticks)
 * - Decay only happens when tournaments are actually being run
 *
 * Score formula: Starting Rank Value (400 + avg*1600) + H2H Adjustments (uncapped)
 */

define('VRS_FULL_VALUE_ACTIVE_DAYS', 5);   // Full value within this many active days
define('VRS_DECAY_ACTIVE_DAYS', 60);       // Results fully decay after this many active days
define('VRS_BASE_ELO', 1500);              // Starting Elo for new teams
define('VRS_K_FACTOR', 32);                // Elo K-factor
define('VRS_TOP_N', 10);                   // Only top N results count per factor
define('VRS_FLOOR', 400);                  // Minimum starting rank value
define('VRS_RANGE', 1600);                 // Starting rank value range (400 + 1600 = 2000 max base)

/**
 * Build active-day timeline from match timestamps.
 * Returns array mapping date string (Y-m-d) => active day number (1-indexed).
 * An "active day" is any calendar date where at least one match was played.
 */
function buildActiveDayTimeline($conn) {
    $result = $conn->query("SELECT DISTINCT DATE(timestamp) AS match_date 
                            FROM sql_matches_scoretotal 
                            ORDER BY match_date ASC");
    $timeline = [];
    $dayNum = 1;
    while ($row = $result->fetch_assoc()) {
        $timeline[$row['match_date']] = $dayNum;
        $dayNum++;
    }
    return $timeline;
}

/**
 * Get active day number for a given timestamp.
 */
function getActiveDay($timestamp, $timeline) {
    if (empty($timestamp)) return 0;
    $date = date('Y-m-d', strtotime($timestamp));
    return isset($timeline[$date]) ? $timeline[$date] : 0;
}

/**
 * Calculate decay weight based on active days elapsed.
 * Full value (1.0) within VRS_FULL_VALUE_ACTIVE_DAYS,
 * exponential decay to 0 over VRS_DECAY_ACTIVE_DAYS.
 */
function vrsAgeWeight($activeDaysSince) {
    if ($activeDaysSince <= VRS_FULL_VALUE_ACTIVE_DAYS) return 1.0;
    if ($activeDaysSince >= VRS_DECAY_ACTIVE_DAYS) return 0.0;

    $daysDecaying = $activeDaysSince - VRS_FULL_VALUE_ACTIVE_DAYS;
    $decayWindow = VRS_DECAY_ACTIVE_DAYS - VRS_FULL_VALUE_ACTIVE_DAYS;
    $lambda = 3.0 / $decayWindow; // ~95% decay at the end
    return exp(-$lambda * $daysDecaying);
}

/**
 * Elo expected score.
 */
function vrsEloExpected($ratingA, $ratingB) {
    return 1.0 / (1.0 + pow(10, ($ratingB - $ratingA) / 400.0));
}

/**
 * Margin of victory multiplier for Elo.
 */
function vrsMovMultiplier($roundsWon, $roundsLost) {
    $diff = abs($roundsWon - $roundsLost);
    return log10(1 + $diff) * 0.6 + 0.8;
}

/**
 * Compute full team rankings from match history using Valve-inspired model
 * with active-day decay. Returns array of teams sorted by points descending.
 * 
 * If $activeTeams is non-empty, only teams in that list appear in the output.
 * All teams still participate in Elo/factor calculations for accuracy —
 * the filter is applied at the end as a display filter.
 */
function computeTeamRankings($conn, $min_matches = 10, $activeTeams = []) {
    // Build the active-day timeline first
    $timeline = buildActiveDayTimeline($conn);
    if (empty($timeline)) return [];

    $currentActiveDay = max(array_values($timeline));
    $totalActiveDays = count($timeline);

    // Snapshot point: 7 active days ago for rank change comparison
    $snapshotActiveDay = max(1, $currentActiveDay - 7);

    $result = $conn->query("SELECT match_id, timestamp, team_2, team_2_name, team_3, team_3_name, map 
                            FROM sql_matches_scoretotal 
                            ORDER BY match_id ASC");

    if (!$result || $result->num_rows == 0) {
        return [];
    }

    // First pass: build Elo history and per-team match records
    $elo = [];           // team => current elo
    $matchRecords = [];  // team => [records with active_day]
    $teamStats = [];     // team => stats
    $snapshotElos = [];  // team => elo at snapshot point

    while ($row = $result->fetch_assoc()) {
        $t_name = $row['team_2_name'];
        $ct_name = $row['team_3_name'];
        $t_score = (int)$row['team_2'];
        $ct_score = (int)$row['team_3'];
        $timestamp = $row['timestamp'];
        $matchActiveDay = getActiveDay($timestamp, $timeline);

        if (empty($t_name) || empty($ct_name)) continue;
        if (($t_score + $ct_score) == 0) continue;

        // Initialize new teams
        foreach ([$t_name, $ct_name] as $name) {
            if (!isset($elo[$name])) {
                $elo[$name] = VRS_BASE_ELO;
                $matchRecords[$name] = [];
                $teamStats[$name] = [
                    'matches' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0,
                    'form' => [], 'last_match' => '', 'peak_elo' => VRS_BASE_ELO
                ];
            }
        }

        // Snapshot elo at/before the snapshot point for rank change tracking
        if ($matchActiveDay <= $snapshotActiveDay) {
            $snapshotElos[$t_name] = $elo[$t_name];
            $snapshotElos[$ct_name] = $elo[$ct_name];
        }

        // Determine result
        if ($ct_score > $t_score) {
            $ct_won = true; $t_won = false; $draw = false;
            $teamStats[$ct_name]['wins']++;
            $teamStats[$t_name]['losses']++;
            $teamStats[$ct_name]['form'][] = 'W';
            $teamStats[$t_name]['form'][] = 'L';
        } elseif ($t_score > $ct_score) {
            $ct_won = false; $t_won = true; $draw = false;
            $teamStats[$t_name]['wins']++;
            $teamStats[$ct_name]['losses']++;
            $teamStats[$t_name]['form'][] = 'W';
            $teamStats[$ct_name]['form'][] = 'L';
        } else {
            $ct_won = false; $t_won = false; $draw = true;
            $teamStats[$ct_name]['draws']++;
            $teamStats[$t_name]['draws']++;
            $teamStats[$ct_name]['form'][] = 'D';
            $teamStats[$t_name]['form'][] = 'D';
        }

        // Elo update
        $ct_elo = $elo[$ct_name];
        $t_elo = $elo[$t_name];
        $ct_actual = $draw ? 0.5 : ($ct_won ? 1.0 : 0.0);
        $t_actual = $draw ? 0.5 : ($t_won ? 1.0 : 0.0);

        $mov_ct = vrsMovMultiplier($ct_score, $t_score);
        $mov_t = vrsMovMultiplier($t_score, $ct_score);

        $ct_expected = vrsEloExpected($ct_elo, $t_elo);
        $t_expected = vrsEloExpected($t_elo, $ct_elo);

        $ct_change = VRS_K_FACTOR * $mov_ct * ($ct_actual - $ct_expected);
        $t_change = VRS_K_FACTOR * $mov_t * ($t_actual - $t_expected);

        $elo[$ct_name] += $ct_change;
        $elo[$t_name] += $t_change;

        // Track peak
        if ($elo[$ct_name] > $teamStats[$ct_name]['peak_elo']) $teamStats[$ct_name]['peak_elo'] = $elo[$ct_name];
        if ($elo[$t_name] > $teamStats[$t_name]['peak_elo']) $teamStats[$t_name]['peak_elo'] = $elo[$t_name];

        // Record match data with active day for factor calculations
        $matchRecords[$ct_name][] = [
            'opponent' => $t_name, 'won' => $ct_won, 'draw' => $draw,
            'opp_elo' => $t_elo, 'active_day' => $matchActiveDay,
            'rounds_won' => $ct_score, 'rounds_lost' => $t_score,
            'elo_change' => $ct_change
        ];
        $matchRecords[$t_name][] = [
            'opponent' => $ct_name, 'won' => $t_won, 'draw' => $draw,
            'opp_elo' => $ct_elo, 'active_day' => $matchActiveDay,
            'rounds_won' => $t_score, 'rounds_lost' => $ct_score,
            'elo_change' => $t_change
        ];

        $teamStats[$ct_name]['matches']++;
        $teamStats[$t_name]['matches']++;
        $teamStats[$ct_name]['last_match'] = $timestamp;
        $teamStats[$t_name]['last_match'] = $timestamp;

        // Keep last 5 form entries
        foreach ([$ct_name, $t_name] as $name) {
            if (count($teamStats[$name]['form']) > 5) {
                $teamStats[$name]['form'] = array_slice($teamStats[$name]['form'], -5);
            }
        }
    }

    // Second pass: compute VRS factors per team using active-day decay
    $teamScores = [];
    $allSoS = [];
    $allNetwork = [];
    $allConsistency = [];
    $allH2H = [];

    foreach ($matchRecords as $teamName => $records) {
        if ($teamStats[$teamName]['matches'] < $min_matches) continue;

        // --- Factor 1: Strength of Schedule (top 10 wins by opponent Elo, age-weighted) ---
        $wins = array_filter($records, function($r) { return $r['won']; });
        $sosScores = [];
        foreach ($wins as $w) {
            $activeDaysSince = $currentActiveDay - $w['active_day'];
            $ageW = vrsAgeWeight($activeDaysSince);
            $sosScores[] = $w['opp_elo'] * $ageW;
        }
        rsort($sosScores);
        $topSoS = array_slice($sosScores, 0, VRS_TOP_N);
        $sos = count($topSoS) > 0 ? array_sum($topSoS) / VRS_TOP_N : 0;

        // --- Factor 2: Opponent Network (distinct opponents defeated, age-weighted) ---
        $distinctOpponents = [];
        foreach ($wins as $w) {
            $activeDaysSince = $currentActiveDay - $w['active_day'];
            $ageW = vrsAgeWeight($activeDaysSince);
            if ($ageW > 0.01) {
                if (!isset($distinctOpponents[$w['opponent']]) || $distinctOpponents[$w['opponent']] < $ageW) {
                    $distinctOpponents[$w['opponent']] = $ageW;
                }
            }
        }
        $netValues = array_values($distinctOpponents);
        rsort($netValues);
        $topNet = array_slice($netValues, 0, VRS_TOP_N);
        $networkScore = array_sum($topNet);

        // --- Factor 3: Consistency (recent wins, age-weighted, top 10) ---
        $conScores = [];
        foreach ($wins as $w) {
            $activeDaysSince = $currentActiveDay - $w['active_day'];
            $ageW = vrsAgeWeight($activeDaysSince);
            $conScores[] = $ageW;
        }
        rsort($conScores);
        $topCon = array_slice($conScores, 0, VRS_TOP_N);
        $consistency = array_sum($topCon);

        // --- Factor 4: Head-to-Head (sum of age-weighted Elo changes) ---
        $h2hSum = 0;
        foreach ($records as $r) {
            $activeDaysSince = $currentActiveDay - $r['active_day'];
            $ageW = vrsAgeWeight($activeDaysSince);
            $h2hSum += $r['elo_change'] * $ageW;
        }

        $allSoS[$teamName] = $sos;
        $allNetwork[$teamName] = $networkScore;
        $allConsistency[$teamName] = $consistency;
        $allH2H[$teamName] = $h2hSum;

        $teamScores[$teamName] = true;
    }

    if (empty($teamScores)) return [];

    // Normalize each factor to 0-1 range
    $maxSoS = max(max(array_values($allSoS)), 0.001);
    $minSoS = min(array_values($allSoS));
    $maxNet = max(max(array_values($allNetwork)), 0.001);
    $minNet = min(array_values($allNetwork));
    $maxCon = max(max(array_values($allConsistency)), 0.001);
    $minCon = min(array_values($allConsistency));

    $ranked = [];
    foreach ($teamScores as $teamName => $_) {
        $stats = $teamStats[$teamName];

        $rangeSoS = $maxSoS - $minSoS;
        $normSoS = $rangeSoS > 0 ? ($allSoS[$teamName] - $minSoS) / $rangeSoS : 0.5;
        $rangeNet = $maxNet - $minNet;
        $normNet = $rangeNet > 0 ? ($allNetwork[$teamName] - $minNet) / $rangeNet : 0.5;
        $rangeCon = $maxCon - $minCon;
        $normCon = $rangeCon > 0 ? ($allConsistency[$teamName] - $minCon) / $rangeCon : 0.5;

        // Starting rank value: VRS formula — 400 + (normalized average * 1600)
        $avgFactor = ($normSoS + $normNet + $normCon) / 3.0;
        $startingValue = VRS_FLOOR + $avgFactor * VRS_RANGE;

        // Add H2H adjustment (uncapped, like Valve's model)
        $h2hAdjustment = $allH2H[$teamName];
        $finalPoints = max(0, round($startingValue + $h2hAdjustment, 1));

        $winrate = $stats['matches'] > 0 ? round(($stats['wins'] / $stats['matches']) * 100, 1) : 0;

        $ranked[] = [
            'name'       => $teamName,
            'points'     => $finalPoints,
            'matches'    => $stats['matches'],
            'wins'       => $stats['wins'],
            'losses'     => $stats['losses'],
            'draws'      => $stats['draws'],
            'winrate'    => $winrate,
            'form'       => $stats['form'],
            'peak_elo'   => round($stats['peak_elo'], 0),
            'current_elo'=> round($elo[$teamName], 0),
            'last_match' => $stats['last_match'],
            'snapshot_elo' => isset($snapshotElos[$teamName]) ? $snapshotElos[$teamName] : null,
            'sos'        => round($normSoS * 100, 1),
            'network'    => round($normNet * 100, 1),
            'consistency'=> round($normCon * 100, 1),
            'h2h'        => round($h2hAdjustment, 1),
            'active_days'=> $totalActiveDays,
        ];
    }

    // Sort by points descending
    usort($ranked, function($a, $b) {
        return $b['points'] <=> $a['points'];
    });

    // Filter to active rosters only (if provided)
    if (!empty($activeTeams)) {
        $ranked = array_values(array_filter($ranked, function($team) use ($activeTeams) {
            return in_array($team['name'], $activeTeams);
        }));
    }

    // Compute rank changes vs snapshot (using old Elo as proxy for old rank)
    $snapshotSorted = $ranked;
    usort($snapshotSorted, function($a, $b) {
        $a_snap = $a['snapshot_elo'] ?? 0;
        $b_snap = $b['snapshot_elo'] ?? 0;
        return $b_snap <=> $a_snap;
    });
    $snapshotRanks = [];
    $srank = 1;
    foreach ($snapshotSorted as $t) {
        if ($t['snapshot_elo'] !== null) {
            $snapshotRanks[$t['name']] = $srank;
        }
        $srank++;
    }

    $currentRank = 1;
    foreach ($ranked as &$team) {
        $team['rank'] = $currentRank;
        if (isset($snapshotRanks[$team['name']])) {
            $team['rank_change'] = $snapshotRanks[$team['name']] - $currentRank;
        } else {
            $team['rank_change'] = null;
        }
        $currentRank++;
    }
    unset($team);

    return $ranked;
}
?>
