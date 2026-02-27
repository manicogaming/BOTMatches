<?php
require_once("config.php");
require_once("functions.php");

renderPageStart($page_title . " - Leaderboard");

$cache_file = __DIR__ . '/cache_leaderboard.json';

// Toggle: ?all=1 shows all players, default shows active roster only
$showAll = isset($_GET['all']) && $_GET['all'] === '1';

// Parse active roster players for filtering
$activePlayers = parseActiveRosterPlayers($bot_rosters_path);
$hasRoster = !empty($activePlayers);

// ── Cache logic: full rebuild or incremental merge ──
// Cache format: {match_id: int, players: {name: {id, kills, deaths, assists, damage, kastrounds, rounds, matches}, ...}}
$latestMatchId = getLatestMatchId($conn);
$cache = null;
$playerMap = null;

if (file_exists($cache_file)) {
    $cache = json_decode(file_get_contents($cache_file), true);
}

if ($cache && isset($cache['match_id']) && $cache['match_id'] === $latestMatchId && isset($cache['players'])) {
    // Cache hit — no work needed
    $playerMap = $cache['players'];

} elseif ($cache && isset($cache['match_id']) && isset($cache['players']) && !empty($cache['players'])) {
    // Incremental update — query only matches newer than cached match_id
    $playerMap = $cache['players'];
    $cachedMatchId = $cache['match_id'];

    $stmt = $conn->prepare("SELECT p.id, m.name, m.kills, m.assists, m.deaths, m.damage, m.kastrounds,
                            (s.team_2 + s.team_3) AS rounds
                            FROM sql_matches m
                            INNER JOIN sql_players p ON p.name = m.name
                            INNER JOIN sql_matches_scoretotal s ON s.match_id = m.match_id
                            WHERE m.match_id > ?");
    $stmt->bind_param("i", $cachedMatchId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $name = $row['name'];
        if (!isset($playerMap[$name])) {
            $playerMap[$name] = ['id' => (int)$row['id'], 'kills' => 0, 'deaths' => 0, 'assists' => 0,
                                 'damage' => 0, 'kastrounds' => 0, 'rounds' => 0, 'matches' => 0];
        }
        $playerMap[$name]['kills']      += (int)$row['kills'];
        $playerMap[$name]['deaths']     += (int)$row['deaths'];
        $playerMap[$name]['assists']    += (int)$row['assists'];
        $playerMap[$name]['damage']     += (int)$row['damage'];
        $playerMap[$name]['kastrounds'] += (int)$row['kastrounds'];
        $playerMap[$name]['rounds']     += (int)$row['rounds'];
        $playerMap[$name]['matches']++;
    }

    file_put_contents($cache_file, json_encode(['match_id' => $latestMatchId, 'players' => $playerMap]));

} else {
    // Full rebuild — no usable cache
    $stmt = $conn->prepare("SELECT 
            p.id,
            m.name,
            SUM(m.kills) AS totalkills,
            SUM(m.assists) AS totalassists,
            SUM(m.deaths) AS totaldeaths,
            SUM(m.damage) AS totaldamage,
            SUM(m.kastrounds) AS totalkastrounds,
            COUNT(DISTINCT m.match_id) AS matches,
            SUM(s.team_2 + s.team_3) AS totalrounds
        FROM sql_matches m
        INNER JOIN sql_players p ON p.name = m.name
        INNER JOIN sql_matches_scoretotal s ON s.match_id = m.match_id
        GROUP BY p.id, m.name
        ORDER BY m.name");
    $stmt->execute();
    $result = $stmt->get_result();

    $playerMap = [];
    while ($row = $result->fetch_assoc()) {
        $playerMap[$row['name']] = [
            'id'         => (int)$row['id'],
            'kills'      => (int)$row['totalkills'],
            'deaths'     => (int)$row['totaldeaths'],
            'assists'    => (int)$row['totalassists'],
            'damage'     => (int)$row['totaldamage'],
            'kastrounds' => (int)$row['totalkastrounds'],
            'rounds'     => (int)$row['totalrounds'],
            'matches'    => (int)$row['matches'],
        ];
    }

    file_put_contents($cache_file, json_encode(['match_id' => $latestMatchId, 'players' => $playerMap]));
}

$conn->close();

// ── Build display array from raw totals ──
$players = [];
foreach ($playerMap as $name => $raw) {
    if ($raw['matches'] < $leaderboard_min_matches) continue;
    if ($raw['rounds'] <= 0) continue;

    $players[] = [
        'id'       => $raw['id'],
        'name'     => $name,
        'matches'  => $raw['matches'],
        'kills'    => $raw['kills'],
        'deaths'   => $raw['deaths'],
        'kdr'      => calculateKDR($raw['kills'], $raw['deaths']),
        'adr'      => calculateADR($raw['damage'], $raw['rounds']),
        'rating'   => calculateHLTV2($raw['kills'], $raw['deaths'], $raw['assists'], $raw['damage'], $raw['kastrounds'], $raw['rounds']),
    ];
}

usort($players, function($a, $b) {
    return $b['rating'] <=> $a['rating'];
});

// Apply roster filter at display time (unless ?all=1 or no roster file)
$displayPlayers = $players;
if (!$showAll && $hasRoster) {
    $displayPlayers = array_values(array_filter($players, function($p) use ($activePlayers) {
        return in_array($p['name'], $activePlayers);
    }));
}

if (count($displayPlayers) > 0) {
    // Toggle link
    if ($hasRoster) {
        $toggleUrl = $showAll ? 'leaderboard.php' : 'leaderboard.php?all=1';
        $toggleLabel = $showAll ? 'Show Active Roster Only' : 'Show All Players';
        $toggleHtml = '<div class="text-center" style="margin-top:15px;"><a href="'.$toggleUrl.'" class="btn btn-sm btn-outline-info">'.$toggleLabel.'</a></div>';
    } else {
        $toggleHtml = '';
    }

    echo $toggleHtml.'
    <div class="row" style="margin-top:10px;">
        <div class="col-md-12">
            <div class="card rounded-borders" style="border: none !important;">
                <div class="card-body" style="padding:0;">
                    <div class="card-header-bar bg-primary">
                        <h3>Player Leaderboard</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table sortable" id="leaderboard-table">
                            <thead class="table-borderless" style="border: none !important;">
                                <tr>
                                    <th data-sort="number"># <span class="sort-arrow"></span></th>
                                    <th data-sort="string">Player <span class="sort-arrow"></span></th>
                                    <th data-sort="number">Matches <span class="sort-arrow"></span></th>
                                    <th data-sort="number">Kills <span class="sort-arrow"></span></th>
                                    <th data-sort="number">Deaths <span class="sort-arrow"></span></th>
                                    <th data-sort="number">KDR <span class="sort-arrow"></span></th>
                                    <th data-sort="number">ADR <span class="sort-arrow"></span></th>
                                    <th data-sort="number">Rating <span class="sort-arrow"></span></th>
                                </tr>
                            </thead>
                            <tbody>';

    $rank = 1;
    foreach ($displayPlayers as $p) {
        $rating_class = ratingClass($p['rating']);
        echo '
                                <tr>
                                    <td>'.$rank.'</td>
                                    <td><a href="player.php?id='.$p['id'].'" class="text-white">'.h($p['name']).'</a></td>
                                    <td>'.$p['matches'].'</td>
                                    <td>'.$p['kills'].'</td>
                                    <td>'.$p['deaths'].'</td>
                                    <td>'.$p['kdr'].'</td>
                                    <td>'.$p['adr'].'</td>
                                    <td class="'.$rating_class.'" style="font-weight:bold;">'.$p['rating'].'</td>
                                </tr>';
        $rank++;
    }

    echo '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    if ($hasRoster && !$showAll) {
        $rosterNote = 'Showing <strong>'.count($displayPlayers).'</strong> active roster players.';
    } elseif ($hasRoster && $showAll) {
        $rosterNote = 'Showing all <strong>'.count($displayPlayers).'</strong> players.';
    } else {
        $rosterNote = '<span style="color:#f0ad4e;">bot_rosters.txt not found — showing all players.</span>';
    }

    echo '
    <p class="text-center text-muted" style="margin-top:10px;"><small>Minimum '.$leaderboard_min_matches.' matches required. Updates automatically after each match.</small></p>
    <p class="text-center text-muted" style="margin-top:2px;"><small>'.$rosterNote.'</small></p>';
} else {
    echo '<h4 class="empty-state">No players with enough matches yet!</h4>';
}
?>
    <script>
    // Sortable table columns
    document.querySelectorAll('.sortable th[data-sort]').forEach(function(th) {
        th.addEventListener('click', function() {
            var table = th.closest('table');
            var tbody = table.querySelector('tbody');
            var rows = Array.from(tbody.querySelectorAll('tr'));
            var colIndex = Array.from(th.parentNode.children).indexOf(th);
            var sortType = th.getAttribute('data-sort');
            var isAsc = th.getAttribute('data-order') !== 'asc';

            table.querySelectorAll('.sort-arrow').forEach(function(s) { s.textContent = ''; });
            th.querySelector('.sort-arrow').textContent = isAsc ? ' ▲' : ' ▼';
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
