<?php
require_once("config.php");
require_once("functions.php");

renderPageStart($page_title . " - Leaderboard");

$cache_file = __DIR__ . '/cache_leaderboard.json';
$players = null;

// Serve from cache if fresh enough
if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $leaderboard_cache_seconds) {
    $players = json_decode(file_get_contents($cache_file), true);
}

// Cache miss or expired — recompute
if ($players === null) {
    // Single query: player stats + total rounds in one pass
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
        HAVING COUNT(DISTINCT m.match_id) >= ?
        ORDER BY m.name");
    $stmt->bind_param("i", $leaderboard_min_matches);
    $stmt->execute();
    $result = $stmt->get_result();

    $players = [];
    while ($row = $result->fetch_assoc()) {
        $rounds = (int)$row['totalrounds'];
        if ($rounds <= 0) continue;

        $players[] = [
            'id'       => (int)$row['id'],
            'name'     => $row['name'],
            'matches'  => (int)$row['matches'],
            'kills'    => (int)$row['totalkills'],
            'deaths'   => (int)$row['totaldeaths'],
            'kdr'      => calculateKDR($row['totalkills'], $row['totaldeaths']),
            'adr'      => calculateADR($row['totaldamage'], $rounds),
            'rating'   => calculateHLTV2($row['totalkills'], $row['totaldeaths'], $row['totalassists'], $row['totaldamage'], $row['totalkastrounds'], $rounds),
        ];
    }

    // Sort by rating descending
    usort($players, function($a, $b) {
        return $b['rating'] <=> $a['rating'];
    });

    // Write cache
    file_put_contents($cache_file, json_encode($players));
}

$conn->close();

if (count($players) > 0) {
    $cache_age = file_exists($cache_file) ? time() - filemtime($cache_file) : 0;
    $cache_remaining = max(0, $leaderboard_cache_seconds - $cache_age);
    $cache_minutes = ceil($cache_remaining / 60);

    echo '
    <div class="row" style="margin-top:20px;">
        <div class="col-md-12">
            <div class="card rounded-borders" style="border: none !important;">
                <div class="card-body" style="padding:0;">
                    <div style="background-color:#375a7f;height:25px;">
                        <h3 class="text-uppercase text-center text-white" style="font-size:22px;">Player Leaderboard</h3>
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
    foreach ($players as $p) {
        $rating_color = ($p['rating'] >= 1.0) ? 'color:green;' : 'color:red;';
        echo '
                                <tr>
                                    <td>'.$rank.'</td>
                                    <td><a href="player.php?id='.$p['id'].'" class="text-white">'.h($p['name']).'</a></td>
                                    <td>'.$p['matches'].'</td>
                                    <td>'.$p['kills'].'</td>
                                    <td>'.$p['deaths'].'</td>
                                    <td>'.$p['kdr'].'</td>
                                    <td>'.$p['adr'].'</td>
                                    <td style="'.$rating_color.' font-weight:bold;">'.$p['rating'].'</td>
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
    </div>
    <p class="text-center text-muted" style="margin-top:10px;"><small>Minimum '.$leaderboard_min_matches.' matches required. Updates every '.(int)($leaderboard_cache_seconds / 60).' minutes (next update in ~'.$cache_minutes.' min).</small></p>';
} else {
    echo '<h4 style="margin-top:40px;text-align:center;">No players with enough matches yet!</h4>';
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
            th.querySelector('.sort-arrow').textContent = isAsc ? ' \u25B2' : ' \u25BC';
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
