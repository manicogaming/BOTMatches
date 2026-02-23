<?php
require_once("config.php");
require_once("functions.php");

renderPageStart($page_title . " - Team Rankings");

$cache_file = __DIR__ . '/cache_teams.json';
$teams = null;

// Serve from cache if fresh enough
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $teams_cache_seconds) {
    $teams = json_decode(file_get_contents($cache_file), true);
}

// Cache miss or expired — recompute
if ($teams === null) {
    $teams = computeTeamRankings($conn, $teams_min_matches);
    file_put_contents($cache_file, json_encode($teams));
}

$conn->close();

if (count($teams) > 0) {
    $cache_age = file_exists($cache_file) ? time() - filemtime($cache_file) : 0;
    $cache_remaining = max(0, $teams_cache_seconds - $cache_age);
    $cache_minutes = ceil($cache_remaining / 60);
    $today = date('j F Y');

    echo '
    <div style="margin-top:20px;">
        <div class="card rounded-borders" style="border: none !important;">
            <div class="card-body" style="padding:0;">
                <div style="background-color:#375a7f; padding: 12px 20px;">
                    <h3 class="text-uppercase text-center text-white" style="font-size:22px; margin:0;">Team Ranking</h3>
                    <p class="text-center text-white-50" style="margin:0; font-size:13px;">'.$today.'</p>
                </div>';

    foreach ($teams as $team) {
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
        $h2h_color = $team['h2h'] >= 0 ? '#5cb85c' : '#d9534f';

        $teamId = preg_replace('/[^a-zA-Z0-9]/', '_', $team['name']);

        echo '
                <div class="team-row">
                    <div class="d-flex align-items-center flex-wrap">
                        <div class="team-rank text-white" style="min-width:45px;">#'.$team['rank'].'</div>
                        <div style="min-width:30px; text-align:center;">'.$change_html.'</div>
                        <div class="d-flex align-items-center" style="min-width:200px; flex:1;">
                            <img class="team-logo" src="assets/img/icons/'.h($team['name']).'.png" onerror="this.style.display=\'none\'">
                            <span class="team-name text-white">'.h($team['name']).'</span>
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
                        <div class="text-center" style="min-width:40px;">
                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleDetails(\''.$teamId.'\')" style="padding:2px 8px; font-size:11px;">
                                <i class="fa fa-bar-chart"></i>
                            </button>
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
                                    <span style="color:'.$h2h_color.'; font-weight:bold;">'.$h2h_display.'</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
    }

    echo '
            </div>
        </div>
    </div>
    <p class="text-center text-muted" style="margin-top:10px;">
        <small>Minimum '.$teams_min_matches.' matches required. Rank changes vs 7 active days ago. Updates every '.(int)($teams_cache_seconds / 60).' min (next in ~'.$cache_minutes.' min).</small>
    </p>
    <p class="text-center text-muted" style="margin-top:2px;">
        <small>Decay based on <strong>'.$teams[0]['active_days'].' active days</strong> (days with matches played). Full value for '.VRS_FULL_VALUE_ACTIVE_DAYS.' active days, fully decayed after '.VRS_DECAY_ACTIVE_DAYS.'.</small>
    </p>
    <p class="text-center text-muted" style="margin-top:2px;">
        <small>Model based on <a href="https://github.com/ValveSoftware/counter-strike_regional_standings" class="text-info" target="_blank">Valve Regional Standings</a> — adapted for bot matches (no prize money/LAN factors).</small>
    </p>';
} else {
    echo '<h4 style="margin-top:40px;text-align:center;">No teams with enough matches yet!</h4>';
}
?>
    <script>
    function toggleDetails(teamId) {
        var el = document.getElementById('details_' + teamId);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
    </script>
<?php renderPageEnd(); ?>
