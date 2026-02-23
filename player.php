<?php
require_once("config.php");
require_once("functions.php");

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

// ── Query 1: Career aggregate stats ──
$stmt = $conn->prepare("SELECT p.id, m.name,
        SUM(m.kills) AS totalkills, SUM(m.assists) AS totalassists,
        SUM(m.deaths) AS totaldeaths, SUM(m.`5k`) AS total5k,
        SUM(m.`4k`) AS total4k, SUM(m.`3k`) AS total3k,
        SUM(m.damage) AS totaldamage, SUM(m.kastrounds) AS totalkastrounds,
        COUNT(DISTINCT m.match_id) AS matches
    FROM sql_matches m
    INNER JOIN sql_players p ON p.name = m.name
    WHERE p.id = ?
    GROUP BY p.id, m.name");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    renderPageStart($page_title . " - Player");
    echo '<h4 class="empty-state">No Player with that ID!</h4>';
    $conn->close();
    renderPageEnd();
    exit;
}

$player = $result->fetch_assoc();
$playerName = $player["name"];

// ── Query 2: Total rounds ──
$stmtRounds = $conn->prepare("SELECT SUM(s.team_2 + s.team_3) AS totalrounds
    FROM sql_matches_scoretotal s
    INNER JOIN sql_matches m ON s.match_id = m.match_id
    INNER JOIN sql_players p ON p.name = m.name
    WHERE p.id = ?");
$stmtRounds->bind_param("i", $id);
$stmtRounds->execute();
$roundRow = $stmtRounds->get_result()->fetch_assoc();
$totalRounds = max(1, (int)$roundRow["totalrounds"]);

// ── Query 3: Per-match data (all matches for this player) ──
$stmtMatches = $conn->prepare("SELECT m.match_id, m.kills, m.assists, m.deaths, m.damage,
        m.kastrounds, m.`5k`, m.`4k`, m.`3k`, m.team,
        s.team_2, s.team_2_name, s.team_3, s.team_3_name, s.map, s.timestamp
    FROM sql_matches m
    INNER JOIN sql_matches_scoretotal s ON s.match_id = m.match_id
    WHERE m.name = ?
    ORDER BY m.match_id ASC");
$stmtMatches->bind_param("s", $playerName);
$stmtMatches->execute();
$matchResult = $stmtMatches->get_result();

$allMatches = [];
while ($mrow = $matchResult->fetch_assoc()) {
    $rounds = max(1, (int)$mrow["team_2"] + (int)$mrow["team_3"]);
    $rating = calculateHLTV2($mrow["kills"], $mrow["deaths"], $mrow["assists"], $mrow["damage"], $mrow["kastrounds"], $rounds);

    // Determine player's team name and opponent, and W/L
    if ((int)$mrow["team"] == 2) {
        $myTeamName = $mrow["team_2_name"];
        $myScore = (int)$mrow["team_2"];
        $oppTeamName = $mrow["team_3_name"];
        $oppScore = (int)$mrow["team_3"];
    } else {
        $myTeamName = $mrow["team_3_name"];
        $myScore = (int)$mrow["team_3"];
        $oppTeamName = $mrow["team_2_name"];
        $oppScore = (int)$mrow["team_2"];
    }

    if ($myScore > $oppScore) $wl = 'W';
    elseif ($myScore < $oppScore) $wl = 'L';
    else $wl = 'D';

    $allMatches[] = [
        'match_id'  => (int)$mrow["match_id"],
        'kills'     => (int)$mrow["kills"],
        'assists'   => (int)$mrow["assists"],
        'deaths'    => (int)$mrow["deaths"],
        'damage'    => (int)$mrow["damage"],
        'kastrounds'=> (int)$mrow["kastrounds"],
        '5k'        => (int)$mrow["5k"],
        '4k'        => (int)$mrow["4k"],
        '3k'        => (int)$mrow["3k"],
        'rounds'    => $rounds,
        'rating'    => $rating,
        'map'       => $mrow["map"],
        'timestamp' => $mrow["timestamp"],
        'team'      => (int)$mrow["team"],
        'my_team'   => $myTeamName,
        'my_score'  => $myScore,
        'opp_team'  => $oppTeamName,
        'opp_score' => $oppScore,
        'wl'        => $wl,
    ];
}

$conn->close();

// ── Compute derived stats ──
$totalKills = (int)$player["totalkills"];
$totalDeaths = (int)$player["totaldeaths"];
$totalAssists = (int)$player["totalassists"];
$totalDamage = (int)$player["totaldamage"];
$totalKAST = (int)$player["totalkastrounds"];
$matchCount = (int)$player["matches"];

$kdr = calculateKDR($totalKills, $totalDeaths);
$adr = calculateADR($totalDamage, $totalRounds);
$rating = calculateHLTV2($totalKills, $totalDeaths, $totalAssists, $totalDamage, $totalKAST, $totalRounds);
$kpr = round($totalKills / $totalRounds, 2);
$dpr = round($totalDeaths / $totalRounds, 2);
$apr = round($totalAssists / $totalRounds, 2);
$kastPct = round(($totalKAST / $totalRounds) * 100, 1);

// Impact rating (sub-component of HLTV2)
$impact = round(HLTV2_IMPACT_MOD * ((HLTV2_IMPACT_KPR_MOD * $kpr) + (HLTV2_IMPACT_APR_MOD * $apr) + HLTV2_IMPACT_OFFSET_MOD), 2);

// W/L record
$wins = 0; $losses = 0; $draws = 0;
foreach ($allMatches as $m) {
    if ($m['wl'] === 'W') $wins++;
    elseif ($m['wl'] === 'L') $losses++;
    else $draws++;
}
$winrate = $matchCount > 0 ? round(($wins / $matchCount) * 100, 1) : 0;

// Form (last 5 matches)
$recentMatches = array_slice($allMatches, -5);
$form = array_map(function($m) { return $m['wl']; }, $recentMatches);

// Career highs
$bestRating = 0; $bestRatingId = 0;
$mostKills = 0; $mostKillsId = 0;
$highestADR = 0; $highestADRId = 0;
foreach ($allMatches as $m) {
    if ($m['rating'] > $bestRating) { $bestRating = $m['rating']; $bestRatingId = $m['match_id']; }
    if ($m['kills'] > $mostKills) { $mostKills = $m['kills']; $mostKillsId = $m['match_id']; }
    $mADR = $m['rounds'] > 0 ? round($m['damage'] / $m['rounds'], 1) : 0;
    if ($mADR > $highestADR) { $highestADR = $mADR; $highestADRId = $m['match_id']; }
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

// Per-team breakdown (which teams this player has played for)
$teamStats = [];
foreach ($allMatches as $m) {
    $t = $m['my_team'];
    if (empty($t)) continue;
    if (!isset($teamStats[$t])) {
        $teamStats[$t] = ['kills' => 0, 'deaths' => 0, 'assists' => 0, 'damage' => 0, 'kastrounds' => 0, 'rounds' => 0, 'matches' => 0, 'wins' => 0];
    }
    $teamStats[$t]['kills'] += $m['kills'];
    $teamStats[$t]['deaths'] += $m['deaths'];
    $teamStats[$t]['assists'] += $m['assists'];
    $teamStats[$t]['damage'] += $m['damage'];
    $teamStats[$t]['kastrounds'] += $m['kastrounds'];
    $teamStats[$t]['rounds'] += $m['rounds'];
    $teamStats[$t]['matches']++;
    if ($m['wl'] === 'W') $teamStats[$t]['wins']++;
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

// Chart data (all matches chronologically)
$chartLabels = [];
$chartRatings = [];
foreach ($allMatches as $m) {
    $chartLabels[] = date('d M', strtotime($m['timestamp']));
    $chartRatings[] = $m['rating'];
}

// ── Render page ──
renderPageStart($page_title . " - " . $playerName);

$ratingCls = ratingClass($rating);

// Form badges HTML
$formHtml = '';
foreach ($form as $f) {
    if ($f === 'W') $formHtml .= '<span class="form-badge form-w">W</span>';
    elseif ($f === 'L') $formHtml .= '<span class="form-badge form-l">L</span>';
    else $formHtml .= '<span class="form-badge form-d">D</span>';
}

// Rating form badges (last 5 ratings)
$ratingFormHtml = '';
foreach ($recentMatches as $rm) {
    $rc = ratingClass($rm['rating']);
    $ratingFormHtml .= '<span class="form-badge '.($rm['rating'] >= 1.0 ? 'form-w' : 'form-l').'" style="min-width:38px;width:auto;padding:0 5px;">'.$rm['rating'].'</span>';
}

echo '
<div class="profile-card">
    <div class="row align-items-center">
        <div class="col-md-4 text-center">
            <div style="font-size:42px;font-weight:bold;color:#fff;">'.h($playerName).'</div>
            <div style="margin:8px 0;">'.$formHtml.'</div>
            <div style="color:#888;font-size:13px;">'.$wins.'W - '.$losses.'L'.($draws > 0 ? ' - '.$draws.'D' : '').' ('.$winrate.'% win rate)</div>
        </div>
        <div class="col-md-8">
            <div class="row">
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value '.$ratingCls.'">'.$rating.'</div>
                    <div class="stat-label">Rating</div>
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
                    <div class="stat-value-sm">'.$kpr.'</div>
                    <div class="stat-label">KPR</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$dpr.'</div>
                    <div class="stat-label">DPR</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$kastPct.'%</div>
                    <div class="stat-label">KAST</div>
                </div>
            </div>
            <div class="row" style="margin-top:5px;">
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$impact.'</div>
                    <div class="stat-label">Impact</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$apr.'</div>
                    <div class="stat-label">APR</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$matchCount.'</div>
                    <div class="stat-label">Matches</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$totalKills.'</div>
                    <div class="stat-label">Kills</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$totalDeaths.'</div>
                    <div class="stat-label">Deaths</div>
                </div>
                <div class="col-4 col-md-2 stat-box">
                    <div class="stat-value-sm">'.$totalAssists.'</div>
                    <div class="stat-label">Assists</div>
                </div>
            </div>
        </div>
    </div>
</div>';

// ═══════════ CAREER HIGHLIGHTS ═══════════
echo '
<div class="profile-card">
    <div class="section-title" style="margin-top:0;">Career Highlights</div>
    <div class="row">
        <div class="col-md-2 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.$bestRating.'</div>
                <div class="highlight-label">Best Rating</div>
                <a href="scoreboard.php?id='.$bestRatingId.'" class="text-info" style="font-size:11px;">View Match</a>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.$mostKills.'</div>
                <div class="highlight-label">Most Kills</div>
                <a href="scoreboard.php?id='.$mostKillsId.'" class="text-info" style="font-size:11px;">View Match</a>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.$highestADR.'</div>
                <div class="highlight-label">Highest ADR</div>
                <a href="scoreboard.php?id='.$highestADRId.'" class="text-info" style="font-size:11px;">View Match</a>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.(int)$player["total5k"].'</div>
                <div class="highlight-label">Total Aces</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.(int)$player["total4k"].'</div>
                <div class="highlight-label">Total 4Ks</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="highlight-card">
                <div class="highlight-value">'.(int)$player["total3k"].'</div>
                <div class="highlight-label">Total 3Ks</div>
            </div>
        </div>
    </div>
    <div style="margin-top:8px;">
        <span class="stat-label">Last 5 Ratings: </span> '.$ratingFormHtml.'
    </div>
</div>';

// ═══════════ RATING CHART ═══════════
echo '
<div class="profile-card">
    <div class="section-title" style="margin-top:0;">Rating Over Time</div>
    <canvas id="ratingChart" height="80"></canvas>
</div>';

// ═══════════ TABS: Matches / Maps / Teams / Opponents ═══════════
echo '
<div class="profile-card" style="padding-bottom:5px;">
    <div style="border-bottom:1px solid #444; margin-bottom:15px;">
        <button class="tab-btn active" onclick="showTab(\'matches\')">Recent Matches</button>
        <button class="tab-btn" onclick="showTab(\'maps\')">Maps</button>
        <button class="tab-btn" onclick="showTab(\'teams\')">Teams</button>
        <button class="tab-btn" onclick="showTab(\'opponents\')">Opponents</button>
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
                    <th data-sort="string">Team</th>
                    <th data-sort="string">vs</th>
                    <th data-sort="string">Score</th>
                    <th data-sort="string">W/L</th>
                    <th data-sort="number">K</th>
                    <th data-sort="number">D</th>
                    <th data-sort="number">A</th>
                    <th data-sort="number">ADR</th>
                    <th data-sort="number">Rating</th>
                    <th></th>
                </tr></thead>
                <tbody>';

foreach ($last20 as $m) {
    $wlCls = wlClass($m['wl']);
    $mADR = $m['rounds'] > 0 ? round($m['damage'] / $m['rounds'], 0) : 0;
    $mRatingCls = ratingClass($m['rating']);
    $dateStr = date('d M Y', strtotime($m['timestamp']));
    echo '
                <tr>
                    <td>'.$dateStr.'</td>
                    <td>'.h($m['map']).'</td>
                    <td><a href="team.php?name='.urlencode($m['my_team']).'" class="text-white">'.h($m['my_team']).'</a></td>
                    <td><a href="team.php?name='.urlencode($m['opp_team']).'" class="text-white">'.h($m['opp_team']).'</a></td>
                    <td>'.$m['my_score'].' - '.$m['opp_score'].'</td>
                    <td class="'.$wlCls.'" style="font-weight:bold;">'.$m['wl'].'</td>
                    <td>'.$m['kills'].'</td>
                    <td>'.$m['deaths'].'</td>
                    <td>'.$m['assists'].'</td>
                    <td>'.$mADR.'</td>
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
// Sort maps by matches descending
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
                    <th data-sort="number">KAST %</th>
                    <th data-sort="number">Rating</th>
                </tr></thead>
                <tbody>';

foreach ($mapStats as $mapName => $ms) {
    $mRounds = max(1, $ms['rounds']);
    $mRating = calculateHLTV2($ms['kills'], $ms['deaths'], $ms['assists'], $ms['damage'], $ms['kastrounds'], $mRounds);
    $mKDR = calculateKDR($ms['kills'], $ms['deaths']);
    $mADR = calculateADR($ms['damage'], $mRounds);
    $mKAST = round(($ms['kastrounds'] / $mRounds) * 100, 1);
    $mWinPct = $ms['matches'] > 0 ? round(($ms['wins'] / $ms['matches']) * 100, 1) : 0;
    $mRatingCls = ratingClass($mRating);
    echo '
                <tr>
                    <td>'.h($mapName).'</td>
                    <td>'.$ms['matches'].'</td>
                    <td>'.$mWinPct.'%</td>
                    <td>'.$mKDR.'</td>
                    <td>'.$mADR.'</td>
                    <td>'.$mKAST.'%</td>
                    <td class="'.$mRatingCls.'" style="font-weight:bold;">'.$mRating.'</td>
                </tr>';
}

echo '
                </tbody>
            </table>
        </div>
    </div>';

// ── TAB: Teams ──
uasort($teamStats, function($a, $b) { return $b['matches'] <=> $a['matches']; });

echo '
    <div id="tab-teams" class="tab-content">
        <div class="table-responsive">
            <table class="table sortable" id="teams-table">
                <thead><tr>
                    <th data-sort="string">Team</th>
                    <th data-sort="number">Matches</th>
                    <th data-sort="number">Win %</th>
                    <th data-sort="number">KDR</th>
                    <th data-sort="number">ADR</th>
                    <th data-sort="number">Rating</th>
                </tr></thead>
                <tbody>';

foreach ($teamStats as $tName => $ts) {
    $tRounds = max(1, $ts['rounds']);
    $tRating = calculateHLTV2($ts['kills'], $ts['deaths'], $ts['assists'], $ts['damage'], $ts['kastrounds'], $tRounds);
    $tKDR = calculateKDR($ts['kills'], $ts['deaths']);
    $tADR = calculateADR($ts['damage'], $tRounds);
    $tWinPct = $ts['matches'] > 0 ? round(($ts['wins'] / $ts['matches']) * 100, 1) : 0;
    $tRatingCls = ratingClass($tRating);
    echo '
                <tr>
                    <td><a href="team.php?name='.urlencode($tName).'" class="text-white">'.h($tName).'</a></td>
                    <td>'.$ts['matches'].'</td>
                    <td>'.$tWinPct.'%</td>
                    <td>'.$tKDR.'</td>
                    <td>'.$tADR.'</td>
                    <td class="'.$tRatingCls.'" style="font-weight:bold;">'.$tRating.'</td>
                </tr>';
}

echo '
                </tbody>
            </table>
        </div>
    </div>';

// ── TAB: Opponents (H2H) ──
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
    </div>
</div>';

// ═══════════ JAVASCRIPT ═══════════
$chartLabelsJson = json_encode($chartLabels);
$chartRatingsJson = json_encode($chartRatings);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Tab switching
function showTab(tab) {
    document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.remove('active'); });
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
}

// Rating chart
var ctx = document.getElementById('ratingChart').getContext('2d');
var labels = <?php echo $chartLabelsJson; ?>;
var ratings = <?php echo $chartRatingsJson; ?>;

// Downsample if too many points (keep last 200 for readability)
if (labels.length > 200) {
    labels = labels.slice(-200);
    ratings = ratings.slice(-200);
}

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Rating',
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
        plugins: {
            legend: { display: false },
            annotation: {}
        },
        scales: {
            x: {
                ticks: { color: '#888', maxTicksLimit: 15 },
                grid: { color: '#333' }
            },
            y: {
                ticks: { color: '#888' },
                grid: { color: '#333' }
            }
        }
    },
    plugins: [{
        // Draw 1.00 reference line
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

// Sortable table columns (shared across all tabs)
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
