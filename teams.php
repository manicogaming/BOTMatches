<?php
require_once("config.php");
require_once("functions.php");

renderPageStart($page_title . " - Team Rankings");

$cache_file = __DIR__ . '/cache_teams.json';
$teams = null;

// Toggle: ?all=1 shows all teams, default shows active roster only
$showAll = isset($_GET['all']) && $_GET['all'] === '1';

// Parse active rosters from game server (full: team → {players, logo})
$allRosters = parseActiveRostersFull($bot_rosters_path);
$activeTeams = array_keys($allRosters);
$hasRoster = !empty($activeTeams);

// Check if cache is still valid (matches latest match_id)
$latestMatchId = getLatestMatchId($conn);
if (file_exists($cache_file)) {
    $cache = json_decode(file_get_contents($cache_file), true);
    if ($cache && isset($cache['match_id']) && $cache['match_id'] === $latestMatchId) {
        $teams = $cache['data'];
    }
}

// Cache miss or new match — recompute (always unfiltered)
if ($teams === null) {
    $teams = computeTeamRankings($conn, $teams_min_matches);
    file_put_contents($cache_file, json_encode(['match_id' => $latestMatchId, 'data' => $teams]));
}

// Bulk-resolve player IDs for roster links
$playerIds = [];
if ($hasRoster) {
    $allPlayerNames = [];
    foreach ($allRosters as $r) {
        foreach ($r['players'] as $pn) {
            $allPlayerNames[$pn] = true;
        }
    }
    $allPlayerNames = array_keys($allPlayerNames);
    if (!empty($allPlayerNames)) {
        $ph = implode(',', array_fill(0, count($allPlayerNames), '?'));
        $stmt = $conn->prepare("SELECT id, name FROM sql_players WHERE name IN ({$ph})");
        $stmt->bind_param(str_repeat('s', count($allPlayerNames)), ...$allPlayerNames);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $playerIds[$row['name']] = (int)$row['id'];
        }
    }
}

$conn->close();

// Apply roster filter at display time (unless ?all=1 or no roster file)
$displayTeams = $teams;
if (!$showAll && $hasRoster) {
    $displayTeams = array_values(array_filter($teams, function($t) use ($activeTeams) {
        return in_array($t['name'], $activeTeams);
    }));
}

if (count($displayTeams) > 0) {
    $today = date('j F Y');

    // Toggle link
    if ($hasRoster) {
        $toggleUrl = $showAll ? 'teams.php' : 'teams.php?all=1';
        $toggleLabel = $showAll ? 'Show Active Rosters Only' : 'Show All Teams';
        echo '<div class="text-center" style="margin-top:15px;"><a href="'.$toggleUrl.'" class="btn btn-sm btn-outline-info">'.$toggleLabel.'</a></div>';
    }

    echo '
    <div style="margin-top:10px;">
        <div class="card rounded-borders" style="border: none !important;">
            <div class="card-body" style="padding:0;">
                <div class="card-header-bar bg-primary" style="padding:12px 20px;">
                    <h3>Team Ranking</h3>
                    <p class="text-center text-white-50" style="margin:0; font-size:13px;">'.$today.'</p>
                </div>';

    foreach ($displayTeams as $team) {
        // Rank change indicator
        if ($team['rank_change'] === null) {
            $change_html = '<span class="team-change-new">NEW</span>';
        } elseif ($team['rank_change'] > 0) {
            $change_html = '<span class="team-change-up"><i class="fa fa-caret-up"></i> '.$team['rank_change'].'</span>';
        } elseif ($team['rank_change'] < 0) {
            $change_html = '<span class="team-change-down"><i class="fa fa-caret-down"></i> '.abs($team['rank_change']).'</span>';
        } else {
            $change_html = '<span class="team-change-same">—</span>';
        }

        // Form badges (last 5 matches)
        $form_html = '';
        foreach ($team['form'] as $f) {
            if ($f === 'W') {
                $form_html .= '<span class="form-badge form-w">W</span>';
            } elseif ($f === 'L') {
                $form_html .= '<span class="form-badge form-l">L</span>';
            } else {
                $form_html .= '<span class="form-badge form-d">D</span>';
            }
        }

        // Record string
        $record = $team['wins'].'W - '.$team['losses'].'L';
        if ($team['draws'] > 0) {
            $record .= ' - '.$team['draws'].'D';
        }

        // Factor breakdown bars
        $sos_pct = $team['sos'];
        $net_pct = $team['network'];
        $con_pct = $team['consistency'];
        $h2h_display = ($team['h2h'] >= 0 ? '+' : '').$team['h2h'];
        $h2h_class = $team['h2h'] >= 0 ? 'rating-good' : 'rating-bad';

        $teamId = preg_replace('/[^a-zA-Z0-9]/', '_', $team['name']);

        echo '
                <div class="team-row">
                    <div class="d-flex align-items-center flex-wrap">
                        <div class="team-rank text-white" style="min-width:45px;">#'.$team['rank'].'</div>
                        <div style="min-width:30px; text-align:center;">'.$change_html.'</div>
                        <div class="d-flex align-items-center" style="min-width:200px; flex:1;">
                            <img class="team-logo" src="assets/img/icons/'.h($team['name']).'.png" onerror="this.style.display=\'none\'">
                            <span class="team-name text-white"><a href="team.php?name='.urlencode($team['name']).'" class="text-white">'.h($team['name']).'</a></span>
                        </div>
                        <div class="text-center" style="min-width:80px;">
                            <span class="team-points text-white">'.$team['points'].'</span><br>
                            <span class="team-stat-label">points</span>
                        </div>
                        <div class="text-center d-none d-md-block" style="min-width:100px;">
                            <span class="team-stat-value text-white">'.$record.'</span><br>
                            <span class="team-stat-label">'.$team['matches'].' matches</span>
                        </div>
                        <div class="text-center d-none d-md-block" style="min-width:70px;">
                            <span class="team-stat-value text-white">'.$team['winrate'].'%</span><br>
                            <span class="team-stat-label">win rate</span>
                        </div>
                        <div class="text-center d-none d-sm-block" style="min-width:130px;">
                            '.$form_html.'<br>
                            <span class="team-stat-label">form</span>
                        </div>
                        <div class="text-center" style="min-width:70px;">
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleDetails(\''.$teamId.'\')" style="padding:2px 8px; font-size:11px;" title="VRS Breakdown">
                                <i class="fa fa-bar-chart"></i>
                            </button>';

        // Roster toggle button (only if roster data exists for this team)
        if (isset($allRosters[$team['name']])) {
            echo '
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleRoster(\''.$teamId.'\')" style="padding:2px 8px; font-size:11px; margin-left:2px;" title="Active Roster">
                                <i class="fa fa-users"></i>
                            </button>';
        }

        echo '
                        </div>
                    </div>
                    <div id="details_'.$teamId.'" style="display:none; margin-top:10px; padding:8px 45px;">
                        <div class="row text-white" style="font-size:13px;">
                            <div class="col-md-3 col-6 mb-2">
                                <span class="team-stat-label">Strength of Schedule</span>
                                <div style="background:#444; border-radius:3px; height:8px; margin-top:3px;">
                                    <div style="background:#5bc0de; height:8px; border-radius:3px; width:'.$sos_pct.'%;"></div>
                                </div>
                                <small>'.$sos_pct.'</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <span class="team-stat-label">Opponent Network</span>
                                <div style="background:#444; border-radius:3px; height:8px; margin-top:3px;">
                                    <div style="background:#f0ad4e; height:8px; border-radius:3px; width:'.$net_pct.'%;"></div>
                                </div>
                                <small>'.$net_pct.'</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <span class="team-stat-label">Consistency</span>
                                <div style="background:#444; border-radius:3px; height:8px; margin-top:3px;">
                                    <div style="background:#5cb85c; height:8px; border-radius:3px; width:'.$con_pct.'%;"></div>
                                </div>
                                <small>'.$con_pct.'</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <span class="team-stat-label">H2H Adjustment</span>
                                <div style="margin-top:5px;">
                                    <span class="'.$h2h_class.'" style="font-weight:bold;">'.$h2h_display.'</span>
                                </div>
                            </div>
                        </div>
                    </div>';

        // Collapsible roster section
        if (isset($allRosters[$team['name']])) {
            $rosterPlayers = $allRosters[$team['name']]['players'];
            echo '
                    <div id="roster_'.$teamId.'" style="display:none; margin-top:10px; padding:8px 45px;">
                        <div style="display:flex; flex-wrap:wrap; gap:6px;">';

            foreach ($rosterPlayers as $pName) {
                $pid = isset($playerIds[$pName]) ? $playerIds[$pName] : null;
                $nameLink = $pid
                    ? '<a href="player.php?id='.$pid.'" class="text-white">'.h($pName).'</a>'
                    : h($pName);

                echo '
                            <div class="highlight-card" style="padding:6px 12px; margin-bottom:0;">
                                <div style="font-size:13px; font-weight:bold;">'.$nameLink.'</div>
                            </div>';
            }

            echo '
                        </div>
                    </div>';
        }

        echo '
                </div>';
    }

    if ($hasRoster && !$showAll) {
        $rosterNote = 'Showing <strong>'.count($displayTeams).'</strong> active rosters from bot_rosters.txt.';
    } elseif ($hasRoster && $showAll) {
        $rosterNote = 'Showing all <strong>'.count($displayTeams).'</strong> teams.';
    } else {
        $rosterNote = '<span style="color:#f0ad4e;">bot_rosters.txt not found — showing all teams.</span>';
    }

    $activeDays = !empty($teams) ? $teams[0]['active_days'] : 0;

    echo '
            </div>
        </div>
    </div>
    <p class="text-center text-muted" style="margin-top:10px;">
        <small>Minimum '.$teams_min_matches.' matches required. Rank changes vs 7 active days ago. Updates automatically after each match.</small>
    </p>
    <p class="text-center text-muted" style="margin-top:2px;">
        <small>'.$rosterNote.'</small>
    </p>
    <p class="text-center text-muted" style="margin-top:2px;">
        <small>Decay based on <strong>'.$activeDays.' active days</strong> (days with matches played). Full value for '.VRS_FULL_VALUE_ACTIVE_DAYS.' active days, fully decayed after '.VRS_DECAY_ACTIVE_DAYS.'.</small>
    </p>
    <p class="text-center text-muted" style="margin-top:2px;">
        <small>Model based on <a href="https://github.com/ValveSoftware/counter-strike_regional_standings" class="text-info" target="_blank">Valve Regional Standings</a> — adapted for bot matches (no prize money/LAN factors).</small>
    </p>';
} else {
    echo '<h4 class="empty-state">No teams with enough matches yet!</h4>';
}
?>
    <script>
    function toggleDetails(teamId) {
        var el = document.getElementById('details_' + teamId);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
    function toggleRoster(teamId) {
        var el = document.getElementById('roster_' + teamId);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
    </script>
<?php renderPageEnd(); ?>
