<?php
require_once("config.php");
require_once("functions.php");

$teamName = isset($_GET["name"]) ? trim($_GET["name"]) : '';

if ($teamName === '') {
    renderPageStart($page_title . " - Team");
    echo '<h4 class="empty-state">No team specified!</h4>';
    $conn->close();
    renderPageEnd();
    exit;
}

// ── Query: All matches involving this team ──
$stmt = $conn->prepare("SELECT m.match_id, m.name, m.kills, m.assists, m.deaths, m.damage,
        m.kastrounds, m.`5k`, m.`4k`, m.`3k`, m.team,
        s.team_2, s.team_2_name, s.team_3, s.team_3_name, s.map, s.timestamp
    FROM sql_matches m
    INNER JOIN sql_matches_scoretotal s ON s.match_id = m.match_id
    WHERE s.team_2_name = ? OR s.team_3_name = ?
    ORDER BY m.match_id ASC");
$stmt->bind_param("ss", $teamName, $teamName);
$stmt->execute();
$result = $stmt->get_result();

// Organize by match_id
$matchPlayers = []; // match_id => [player rows for this team]
$matchMeta = [];    // match_id => meta info

while ($row = $result->fetch_assoc()) {
    $mid = (int)$row['match_id'];

    // Determine which side this team is on
    if ($row['team_2_name'] === $teamName) {
        $isTeam2 = true;
    } elseif ($row['team_3_name'] === $teamName) {
        $isTeam2 = false;
    } else {
        continue;
    }

    // Store match meta once per match_id
    if (!isset($matchMeta[$mid])) {
        if ($isTeam2) {
            $myScore = (int)$row['team_2'];
            $oppScore = (int)$row['team_3'];
            $oppName = $row['team_3_name'];
            $myTeamCode = 2;
        } else {
            $myScore = (int)$row['team_3'];
            $oppScore = (int)$row['team_2'];
            $oppName = $row['team_2_name'];
            $myTeamCode = 3;
        }

        if ($myScore > $oppScore) $wl = 'W';
        elseif ($myScore < $oppScore) $wl = 'L';
        else $wl = 'D';

        $matchMeta[$mid] = [
            'match_id'  => $mid,
            'map'       => $row['map'],
            'timestamp' => $row['timestamp'],
            'my_score'  => $myScore,
            'opp_score' => $oppScore,
            'opp_team'  => $oppName,
            'wl'        => $wl,
            'rounds'    => max(1, (int)$row['team_2'] + (int)$row['team_3']),
            'team_code' => $myTeamCode,
        ];
    }

    // Only collect players that belong to this team
    if (($isTeam2 && (int)$row['team'] == 2) || (!$isTeam2 && (int)$row['team'] == 3)) {
        $matchPlayers[$mid][] = [
            'name'   => $row['name'],
            'kills'  => (int)$row['kills'],
            'deaths' => (int)$row['deaths'],
            'assists'=> (int)$row['assists'],
            'damage' => (int)$row['damage'],
            'kastrounds' => (int)$row['kastrounds'],
        ];
    }
}

if (empty($matchMeta)) {
    renderPageStart($page_title . " - " . $teamName);
    echo '<h4 class="empty-state">No matches found for '.h($teamName).'!</h4>';
    $conn->close();
    renderPageEnd();
    exit;
}

// ── Build per-match aggregates (team-level stats per match) ──
$allMatches = [];
foreach ($matchMeta as $mid => $meta) {
    $players = isset($matchPlayers[$mid]) ? $matchPlayers[$mid] : [];
    $teamKills = 0; $teamDeaths = 0; $teamAssists = 0; $teamDamage = 0; $teamKAST = 0;
    $rounds = $meta['rounds'];

    // Calculate each player's individual rating and average them
    $ratingSum = 0;
    foreach ($players as $p) {
        $teamKills += $p['kills'];
        $teamDeaths += $p['deaths'];
        $teamAssists += $p['assists'];
        $teamDamage += $p['damage'];
        $teamKAST += $p['kastrounds'];
        $ratingSum += (float)calculateHLTV2($p['kills'], $p['deaths'], $p['assists'], $p['damage'], $p['kastrounds'], $rounds);
    }
    $playerCount = count($players);
    $rating = $playerCount > 0 ? round($ratingSum / $playerCount, 2) : 0;

    $allMatches[] = array_merge($meta, [
        'kills'   => $teamKills,
        'deaths'  => $teamDeaths,
        'assists' => $teamAssists,
        'damage'  => $teamDamage,
        'kastrounds' => $teamKAST,
        'rating'  => $rating,
        'players' => $players,
    ]);
}

// ── Resolve player IDs for linking ──
$allPlayerNames = [];
foreach ($matchPlayers as $players) {
    foreach ($players as $p) {
        $allPlayerNames[$p['name']] = true;
    }
}
$playerIds = [];
if (!empty($allPlayerNames)) {
    $names = array_keys($allPlayerNames);
    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $types = str_repeat('s', count($names));
    $idStmt = $conn->prepare("SELECT id, name FROM sql_players WHERE name IN ({$placeholders})");
    $idStmt->bind_param($types, ...$names);
    $idStmt->execute();
    $idResult = $idStmt->get_result();
    while ($r = $idResult->fetch_assoc()) {
        $playerIds[$r['name']] = (int)$r['id'];
    }
}

// ── Get VRS ranking data (from cache if available) ──
$activeTeams = parseActiveRosters($bot_rosters_path);
$rosterHash = !empty($activeTeams) ? md5(implode('|', $activeTeams)) : '';
$latestMatchId = getLatestMatchId($conn);

$cache_file = __DIR__ . '/cache_teams.json';
$rankedTeams = null;
if (file_exists($cache_file)) {
    $cache = json_decode(file_get_contents($cache_file), true);
    if ($cache && isset($cache['match_id']) && $cache['match_id'] === $latestMatchId
        && isset($cache['roster_hash']) && $cache['roster_hash'] === $rosterHash) {
        $rankedTeams = $cache['data'];
    }
}
if ($rankedTeams === null) {
    $rankedTeams = computeTeamRankings($conn, $teams_min_matches, $activeTeams);
    file_put_contents($cache_file, json_encode(['match_id' => $latestMatchId, 'roster_hash' => $rosterHash, 'data' => $rankedTeams]));
}

// Find this team in rankings
$vrsData = null;
$vrsRank = null;
foreach ($rankedTeams as $idx => $t) {
    if ($t['name'] === $teamName) {
        $vrsData = $t;
        $vrsRank = $idx + 1;
        break;
    }
}

// ── Parse current roster from bot_rosters.txt ──
$rosters = parseActiveRostersFull($bot_rosters_path);
$currentRoster = isset($rosters[$teamName]) ? $rosters[$teamName] : null;

$conn->close();

// ── Compute career totals ──
$totalKills = 0; $totalDeaths = 0; $totalAssists = 0; $totalDamage = 0; $totalKAST = 0; $totalRounds = 0;
$wins = 0; $losses = 0; $draws = 0;
foreach ($allMatches as $m) {
    $totalKills += $m['kills'];
    $totalDeaths += $m['deaths'];
    $totalAssists += $m['assists'];
    $totalDamage += $m['damage'];
    $totalKAST += $m['kastrounds'];
    $totalRounds += $m['rounds'];
    if ($m['wl'] === 'W') $wins++;
    elseif ($m['wl'] === 'L') $losses++;
    else $draws++;
}
$matchCount = count($allMatches);
$totalRounds = max(1, $totalRounds);

$kdr = calculateKDR($totalKills, $totalDeaths);
$adr = calculateADR($totalDamage, $totalRounds);

// Career rating = average of per-match team ratings
$ratingTotal = 0;
foreach ($allMatches as $m) {
    $ratingTotal += $m['rating'];
}
$rating = $matchCount > 0 ? round($ratingTotal / $matchCount, 2) : 0;

$winrate = $matchCount > 0 ? round(($wins / $matchCount) * 100, 1) : 0;

// Form (last 5)
$recentMatches = array_slice($allMatches, -5);
$form = array_map(function($m) { return $m['wl']; }, $recentMatches);

// Career highs
$longestStreak = 0; $currentStreak = 0;
foreach ($allMatches as $m) {
    if ($m['wl'] === 'W') {
        $currentStreak++;
        if ($currentStreak > $longestStreak) $longestStreak = $currentStreak;
    } else {
        $currentStreak = 0;
    }
}

// Per-map breakdown
$mapStats = [];
foreach ($allMatches as $m) {
    $map = $m['map'];
    if (!isset($mapStats[$map])) {
        $mapStats[$map] = ['kills' => 0, 'deaths' => 0, 'assists' => 0, 'damage' => 0, 'kastrounds' => 0, 'rounds' => 0, 'matches' => 0, 'wins' => 0];
    }
    $mapStats[$map]['kills'] += $m['kills'];
    $mapStats[$map]['deaths'] += $m['deaths'];
    $mapStats[$map]['assists'] += $m['assists'];
    $mapStats[$map]['damage'] += $m['damage'];
    $mapStats[$map]['kastrounds'] += $m['kastrounds'];
    $mapStats[$map]['rounds'] += $m['rounds'];
    $mapStats[$map]['matches']++;
    if ($m['wl'] === 'W') $mapStats[$map]['wins']++;
}

// Head-to-head vs opponents
$h2hStats = [];
foreach ($allMatches as $m) {
    $opp = $m['opp_team'];
    if (empty($opp)) continue;
    if (!isset($h2hStats[$opp])) {
        $h2hStats[$opp] = ['kills' => 0, 'deaths' => 0, 'assists' => 0, 'damage' => 0, 'kastrounds' => 0, 'rounds' => 0, 'matches' => 0, 'wins' => 0];
    }
    $h2hStats[$opp]['kills'] += $m['kills'];
    $h2hStats[$opp]['deaths'] += $m['deaths'];
    $h2hStats[$opp]['assists'] += $m['assists'];
    $h2hStats[$opp]['damage'] += $m['damage'];
    $h2hStats[$opp]['kastrounds'] += $m['kastrounds'];
    $h2hStats[$opp]['rounds'] += $m['rounds'];
    $h2hStats[$opp]['matches']++;
    if ($m['wl'] === 'W') $h2hStats[$opp]['wins']++;
}

// Per-player career stats with this team
$playerCareer = [];
foreach ($allMatches as $m) {
    $seen = [];
    foreach ($m['players'] as $p) {
        $name = $p['name'];
        if (!isset($playerCareer[$name])) {
            $playerCareer[$name] = ['kills' => 0, 'deaths' => 0, 'assists' => 0, 'damage' => 0, 'kastrounds' => 0, 'rounds' => 0, 'matches' => 0];
        }
        $playerCareer[$name]['kills'] += $p['kills'];
        $playerCareer[$name]['deaths'] += $p['deaths'];
        $playerCareer[$name]['assists'] += $p['assists'];
        $playerCareer[$name]['damage'] += $p['damage'];
        $playerCareer[$name]['kastrounds'] += $p['kastrounds'];
        $playerCareer[$name]['rounds'] += $m['rounds'];
        if (!isset($seen[$name])) {
            $playerCareer[$name]['matches']++;
            $seen[$name] = true;
        }
    }
}

// Chart data
$chartLabels = [];
$chartRatings = [];
foreach ($allMatches as $m) {
    $chartLabels[] = date('d M', strtotime($m['timestamp']));
    $chartRatings[] = $m['rating'];
}

// ── Render page ──
renderPageStart($page_title . " - " . $teamName);

$ratingCls = ratingClass($rating);

// Form badges
$formHtml = '';
foreach ($form as $f) {
    if ($f === 'W') $formHtml .= '<span class="form-badge form-w">W</span>';
    elseif ($f === 'L') $formHtml .= '<span class="form-badge form-l">L</span>';
    else $formHtml .= '<span class="form-badge form-d">D</span>';
}

// ═══════════ OVERVIEW CARD ═══════════
$logoHtml = '<img src="assets/img/icons/'.h($teamName).'.png" height="48" width="48" style="margin-bottom:8px;" onerror="this.style.display=\'none\'">';

echo '
<div class="profile-card">
    <div class="row align-items-center">
        <div class="col-md-4 text-center">
            '.$logoHtml.'
            <div style="font-size:36px;font-weight:bold;color:#fff;">'.h($teamName).'</div>
            <div style="margin:8px 0;">'.$formHtml.'</div>
            <div style="color:#888;font-size:13px;">'.$wins.'W - '.$losses.'L'.($draws > 0 ? ' - '.$draws.'D' : '').' ('.$winrate.'% win rate)</div>
        </div>
        <div class="col-md-8">
            <div class="row">
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value '.$ratingCls.'">'.$rating.'</div>
                    <div class="stat-label">Avg Rating</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$kdr.'</div>
                    <div class="stat-label">KDR</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$adr.'</div>
                    <div class="stat-label">ADR</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$matchCount.'</div>
                    <div class="stat-label">Matches</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$totalRounds.'</div>
                    <div class="stat-label">Rounds</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$totalKills.'</div>
                    <div class="stat-label">Kills</div>
                </div>
            </div>
        </div>
    </div>
</div>';

// ═══════════ VRS RANKING CONTEXT ═══════════
if ($vrsData !== null) {
    $sosPct = $vrsData['sos'];
    $netPct = $vrsData['network'];
    $conPct = $vrsData['consistency'];
    $h2hVal = $vrsData['h2h'];
    $h2hCls = $h2hVal >= 0 ? 'rating-good' : 'rating-bad';
    $h2hDisplay = ($h2hVal >= 0 ? '+' : '') . $h2hVal;

    echo '
<div class="profile-card">
    <div class="section-title" style="margin-top:0;">VRS Ranking</div>
    <div class="row align-items-center">
        <div class="col-md-2 col-4 stat-box">
            <div class="stat-value" style="color:#5bc0de;">#'.$vrsRank.'</div>
            <div class="stat-label">Rank</div>
        </div>
        <div class="col-md-2 col-4 stat-box">
            <div class="stat-value-sm">'.$vrsData['points'].'</div>
            <div class="stat-label">Points</div>
        </div>
        <div class="col-md-2 col-4 stat-box">
            <div class="stat-value-sm">'.$vrsData['current_elo'].'</div>
            <div class="stat-label">Elo</div>
        </div>
        <div class="col-md-6 col-12">
            <div class="row" style="margin-top:8px;">
                <div class="col-4">
                    <span class="team-stat-label">Strength of Schedule</span>
                    <div style="background:#444; border-radius:3px; height:8px; margin-top:3px;">
                        <div style="background:#5bc0de; height:8px; border-radius:3px; width:'.$sosPct.'%;"></div>
                    </div>
                    <span class="team-stat-value">'.$sosPct.'%</span>
                </div>
                <div class="col-4">
                    <span class="team-stat-label">Opponent Network</span>
                    <div style="background:#444; border-radius:3px; height:8px; margin-top:3px;">
                        <div style="background:#f0ad4e; height:8px; border-radius:3px; width:'.$netPct.'%;"></div>
                    </div>
                    <span class="team-stat-value">'.$netPct.'%</span>
                </div>
                <div class="col-4">
                    <span class="team-stat-label">Consistency</span>
                    <div style="background:#444; border-radius:3px; height:8px; margin-top:3px;">
                        <div style="background:#5cb85c; height:8px; border-radius:3px; width:'.$conPct.'%;"></div>
                    </div>
                    <span class="team-stat-value">'.$conPct.'%</span>
                </div>
            </div>
            <div style="margin-top:8px;">
                <span class="team-stat-label">H2H Elo Adjustment: </span>
                <span class="'.$h2hCls.'" style="font-weight:bold;">'.$h2hDisplay.'</span>
            </div>
        </div>
    </div>
</div>';
}

// ═══════════ CURRENT ROSTER ═══════════
if ($currentRoster !== null && !empty($currentRoster['players'])) {
    echo '
<div class="profile-card">
    <div class="section-title" style="margin-top:0;">Current Roster</div>
    <div class="row">';

    foreach ($currentRoster['players'] as $pName) {
        $pc = isset($playerCareer[$pName]) ? $playerCareer[$pName] : null;
        $pid = isset($playerIds[$pName]) ? $playerIds[$pName] : null;

        if ($pc && $pc['rounds'] > 0) {
            $pRating = calculateHLTV2($pc['kills'], $pc['deaths'], $pc['assists'], $pc['damage'], $pc['kastrounds'], $pc['rounds']);
            $pKDR = calculateKDR($pc['kills'], $pc['deaths']);
            $pADR = calculateADR($pc['damage'], $pc['rounds']);
            $pRatingCls = ratingClass($pRating);
        } else {
            $pRating = '-'; $pKDR = '-'; $pADR = '-'; $pRatingCls = '';
        }

        $nameLink = $pid ? '<a href="player.php?id='.$pid.'" class="text-white">'.h($pName).'</a>' : h($pName);

        echo '
        <div class="col-md-4 col-6" style="margin-bottom:10px;">
            <div class="highlight-card">
                <div style="font-size:16px;font-weight:bold;">'.$nameLink.'</div>';

        if ($pc && $pc['rounds'] > 0) {
            echo '
                <div style="margin-top:6px;">
                    <span class="'.$pRatingCls.'" style="font-weight:bold;font-size:18px;">'.$pRating.'</span>
                    <span class="stat-label" style="margin-left:4px;">rating</span>
                </div>
                <div style="color:#888;font-size:12px;margin-top:2px;">'.$pKDR.' KDR &middot; '.$pADR.' ADR &middot; '.$pc['matches'].' matches</div>';
        } else {
            echo '
                <div style="color:#888;font-size:12px;margin-top:6px;">No matches with this team</div>';
        }

        echo '
            </div>
        </div>';
    }

    echo '
    </div>
</div>';
}

// ═══════════ CAREER HIGHLIGHTS ═══════════
echo '
<div class="profile-card">
    <div class="section-title" style="margin-top:0;">Career Highlights</div>
    <div class="row">
        <div class="col-md-3 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.$currentStreak.'</div>
                <div class="highlight-label">Current Win Streak</div>
            </div>
        </div>
        <div class="col-md-3 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.$longestStreak.'</div>
                <div class="highlight-label">Longest Win Streak</div>
            </div>
        </div>
        <div class="col-md-3 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.$wins.'</div>
                <div class="highlight-label">Total Wins</div>
            </div>
        </div>
    </div>
</div>';

// ═══════════ RATING CHART ═══════════
echo '
<div class="profile-card">
    <div class="section-title" style="margin-top:0;">Team Rating Over Time</div>
    <canvas id="ratingChart" height="80"></canvas>
</div>';

// ═══════════ TABS ═══════════
echo '
<div class="profile-card" style="padding-bottom:5px;">
    <div style="border-bottom:1px solid #444; margin-bottom:15px;">
        <button class="tab-btn active" onclick="showTab(\'matches\')">Recent Matches</button>
        <button class="tab-btn" onclick="showTab(\'maps\')">Maps</button>
        <button class="tab-btn" onclick="showTab(\'opponents\')">Opponents</button>
        <button class="tab-btn" onclick="showTab(\'players\')">All Players</button>
    </div>';

// ── TAB: Recent Matches ──
$last20 = array_slice(array_reverse($allMatches), 0, 20);
echo '
    <div id="tab-matches" class="tab-content active">
        <div class="table-responsive">
            <table class="table sortable" id="matches-table">
                <thead><tr>
                    <th data-sort="string">Date</th>
                    <th data-sort="string">Map</th>
                    <th data-sort="string">Opponent</th>
                    <th data-sort="string">Score</th>
                    <th data-sort="string">W/L</th>
                    <th data-sort="number">Team Rating</th>
                    <th></th>
                </tr></thead>
                <tbody>';

foreach ($last20 as $m) {
    $wlCls = wlClass($m['wl']);
    $mRatingCls = ratingClass($m['rating']);
    $dateStr = date('d M Y', strtotime($m['timestamp']));
    echo '
                <tr>
                    <td>'.$dateStr.'</td>
                    <td>'.h($m['map']).'</td>
                    <td><a href="team.php?name='.urlencode($m['opp_team']).'" class="text-white">'.h($m['opp_team']).'</a></td>
                    <td>'.$m['my_score'].' - '.$m['opp_score'].'</td>
                    <td class="'.$wlCls.'" style="font-weight:bold;">'.$m['wl'].'</td>
                    <td class="'.$mRatingCls.'" style="font-weight:bold;">'.$m['rating'].'</td>
                    <td><a href="scoreboard.php?id='.$m['match_id'].'" class="text-info"><i class="fa fa-external-link"></i></a></td>
                </tr>';
}

echo '
                </tbody>
            </table>
        </div>
    </div>';

// ── TAB: Maps ──
uasort($mapStats, function($a, $b) { return $b['matches'] <=> $a['matches']; });

echo '
    <div id="tab-maps" class="tab-content">
        <div class="table-responsive">
            <table class="table sortable" id="maps-table">
                <thead><tr>
                    <th data-sort="string">Map</th>
                    <th data-sort="number">Matches</th>
                    <th data-sort="number">Win %</th>
                    <th data-sort="number">KDR</th>
                    <th data-sort="number">ADR</th>
                    <th data-sort="number">Rating</th>
                </tr></thead>
                <tbody>';

foreach ($mapStats as $mapName => $ms) {
    $mRounds = max(1, $ms['rounds']);
    $mRating = calculateHLTV2($ms['kills'], $ms['deaths'], $ms['assists'], $ms['damage'], $ms['kastrounds'], $mRounds);
    $mKDR = calculateKDR($ms['kills'], $ms['deaths']);
    $mADR = calculateADR($ms['damage'], $mRounds);
    $mWinPct = $ms['matches'] > 0 ? round(($ms['wins'] / $ms['matches']) * 100, 1) : 0;
    $mRatingCls = ratingClass($mRating);
    echo '
                <tr>
                    <td>'.h($mapName).'</td>
                    <td>'.$ms['matches'].'</td>
                    <td>'.$mWinPct.'%</td>
                    <td>'.$mKDR.'</td>
                    <td>'.$mADR.'</td>
                    <td class="'.$mRatingCls.'" style="font-weight:bold;">'.$mRating.'</td>
                </tr>';
}

echo '
                </tbody>
            </table>
        </div>
    </div>';

// ── TAB: Opponents ──
uasort($h2hStats, function($a, $b) { return $b['matches'] <=> $a['matches']; });

echo '
    <div id="tab-opponents" class="tab-content">
        <div class="table-responsive">
            <table class="table sortable" id="opponents-table">
                <thead><tr>
                    <th data-sort="string">Opponent</th>
                    <th data-sort="number">Matches</th>
                    <th data-sort="number">Win %</th>
                    <th data-sort="number">KDR</th>
                    <th data-sort="number">ADR</th>
                    <th data-sort="number">Rating</th>
                </tr></thead>
                <tbody>';

foreach ($h2hStats as $oppName => $os) {
    $oRounds = max(1, $os['rounds']);
    $oRating = calculateHLTV2($os['kills'], $os['deaths'], $os['assists'], $os['damage'], $os['kastrounds'], $oRounds);
    $oKDR = calculateKDR($os['kills'], $os['deaths']);
    $oADR = calculateADR($os['damage'], $oRounds);
    $oWinPct = $os['matches'] > 0 ? round(($os['wins'] / $os['matches']) * 100, 1) : 0;
    $oRatingCls = ratingClass($oRating);
    echo '
                <tr>
                    <td><a href="team.php?name='.urlencode($oppName).'" class="text-white">'.h($oppName).'</a></td>
                    <td>'.$os['matches'].'</td>
                    <td>'.$oWinPct.'%</td>
                    <td>'.$oKDR.'</td>
                    <td>'.$oADR.'</td>
                    <td class="'.$oRatingCls.'" style="font-weight:bold;">'.$oRating.'</td>
                </tr>';
}

echo '
                </tbody>
            </table>
        </div>
    </div>';

// ── TAB: All Players (historical) ──
uasort($playerCareer, function($a, $b) { return $b['matches'] <=> $a['matches']; });

$currentRosterNames = ($currentRoster !== null) ? $currentRoster['players'] : [];

echo '
    <div id="tab-players" class="tab-content">
        <div class="table-responsive">
            <table class="table sortable" id="players-table">
                <thead><tr>
                    <th data-sort="string">Player</th>
                    <th data-sort="number">Matches</th>
                    <th data-sort="number">Kills</th>
                    <th data-sort="number">Deaths</th>
                    <th data-sort="number">KDR</th>
                    <th data-sort="number">ADR</th>
                    <th data-sort="number">Rating</th>
                </tr></thead>
                <tbody>';

foreach ($playerCareer as $pName => $pc) {
    $pRounds = max(1, $pc['rounds']);
    $pRating = calculateHLTV2($pc['kills'], $pc['deaths'], $pc['assists'], $pc['damage'], $pc['kastrounds'], $pRounds);
    $pKDR = calculateKDR($pc['kills'], $pc['deaths']);
    $pADR = calculateADR($pc['damage'], $pRounds);
    $pRatingCls = ratingClass($pRating);
    $pid = isset($playerIds[$pName]) ? $playerIds[$pName] : null;
    $nameLink = $pid ? '<a href="player.php?id='.$pid.'" class="text-white">'.h($pName).'</a>' : h($pName);

    // Mark current roster players
    $badge = in_array($pName, $currentRosterNames) ? ' <span style="color:#5bc0de;font-size:10px;">ACTIVE</span>' : '';

    echo '
                <tr>
                    <td>'.$nameLink.$badge.'</td>
                    <td>'.$pc['matches'].'</td>
                    <td>'.$pc['kills'].'</td>
                    <td>'.$pc['deaths'].'</td>
                    <td>'.$pKDR.'</td>
                    <td>'.$pADR.'</td>
                    <td class="'.$pRatingCls.'" style="font-weight:bold;">'.$pRating.'</td>
                </tr>';
}

echo '
                </tbody>
            </table>
        </div>
    </div>
</div>';

// ═══════════ JAVASCRIPT ═══════════
$chartLabelsJson = json_encode($chartLabels);
$chartRatingsJson = json_encode($chartRatings);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
function showTab(tab) {
    document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.remove('active'); });
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
}

var ctx = document.getElementById('ratingChart').getContext('2d');
var labels = <?php echo $chartLabelsJson; ?>;
var ratings = <?php echo $chartRatingsJson; ?>;

if (labels.length > 200) {
    labels = labels.slice(-200);
    ratings = ratings.slice(-200);
}

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Team Avg Rating',
            data: ratings,
            borderColor: '#375a7f',
            backgroundColor: 'rgba(55, 90, 127, 0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: labels.length > 50 ? 0 : 3,
            pointHoverRadius: 5,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#888', maxTicksLimit: 15 }, grid: { color: '#333' } },
            y: { ticks: { color: '#888' }, grid: { color: '#333' } }
        }
    },
    plugins: [{
        afterDraw: function(chart) {
            var yScale = chart.scales.y;
            var ctx = chart.ctx;
            var yPos = yScale.getPixelForValue(1.0);
            if (yPos >= yScale.top && yPos <= yScale.bottom) {
                ctx.save();
                ctx.beginPath();
                ctx.setLineDash([5, 5]);
                ctx.strokeStyle = 'rgba(255,255,255,0.3)';
                ctx.lineWidth = 1;
                ctx.moveTo(chart.chartArea.left, yPos);
                ctx.lineTo(chart.chartArea.right, yPos);
                ctx.stroke();
                ctx.restore();
            }
        }
    }]
});

document.querySelectorAll('.sortable th[data-sort]').forEach(function(th) {
    th.addEventListener('click', function() {
        var table = th.closest('table');
        var tbody = table.querySelector('tbody');
        var rows = Array.from(tbody.querySelectorAll('tr'));
        var colIndex = Array.from(th.parentNode.children).indexOf(th);
        var sortType = th.getAttribute('data-sort');
        var isAsc = th.getAttribute('data-order') !== 'asc';

        table.querySelectorAll('.sort-arrow').forEach(function(s) { s.textContent = ''; });
        var arrow = th.querySelector('.sort-arrow');
        if (arrow) arrow.textContent = isAsc ? ' \u25B2' : ' \u25BC';
        th.setAttribute('data-order', isAsc ? 'asc' : 'desc');

        rows.sort(function(a, b) {
            var aVal = a.children[colIndex].textContent.trim();
            var bVal = b.children[colIndex].textContent.trim();
            if (sortType === 'number') {
                aVal = parseFloat(aVal) || 0;
                bVal = parseFloat(bVal) || 0;
                return isAsc ? aVal - bVal : bVal - aVal;
            }
            return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });

        rows.forEach(function(row) { tbody.appendChild(row); });
    });
});
</script>
<?php renderPageEnd(); ?>
