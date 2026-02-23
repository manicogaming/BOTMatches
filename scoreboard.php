<?php
require_once("config.php");
require_once("functions.php");

if (isset($_GET["id"])) {
    $match_id = (int)$_GET["id"]; // Cast to integer - fixes SQL injection
} else {
    $match_id = 0;
}

renderPageStart($page_title);

$stmt = $conn->prepare("SELECT sql_players.id, sql_matches.match_id, sql_matches_scoretotal.timestamp, sql_matches_scoretotal.map, sql_matches_scoretotal.team_2, sql_matches_scoretotal.team_2_name, sql_matches_scoretotal.team_3, sql_matches_scoretotal.team_3_name, sql_matches.name, sql_matches.kills, sql_matches.assists, sql_matches.deaths, sql_matches.team, sql_matches.`5k`, sql_matches.`4k`, sql_matches.`3k`, sql_matches.damage, sql_matches.kastrounds
    FROM sql_matches_scoretotal INNER JOIN sql_matches INNER JOIN sql_players
    ON sql_matches_scoretotal.match_id = sql_matches.match_id AND sql_players.name = sql_matches.name
    WHERE sql_matches_scoretotal.match_id = ?
    ORDER BY sql_matches.kills DESC");
$stmt->bind_param("i", $match_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $t = '';
    $ct = '';
    // Initialize with defaults in case a team has no players
    $t_name = 'Terrorists';
    $ct_name = 'Counter-Terrorists';
    $t_score = 0;
    $ct_score = 0;
    $map = '';
    $timestamp_str = '';

    while ($row = $result->fetch_assoc()) {
        $rounds = ($row["team_2"] + $row["team_3"]);
        $map = $row["map"];
        $timestamp_str = formatTimestamp($row["timestamp"]);

        $kdr = calculateKDR($row["kills"], $row["deaths"]);
        $ADR_roundup = calculateADR($row["damage"], $rounds);
        $HLTV2_roundup = calculateHLTV2($row["kills"], $row["deaths"], $row["assists"], $row["damage"], $row["kastrounds"], $rounds);
        $ratingCls = ratingClass($HLTV2_roundup);

        if ($row["team"] == 2) {
            $t_name = $row["team_2_name"];
            if ($t_name == NULL) {
                $t_name = "Terrorists";
            }
            $t_score = $row["team_2"];

            $t .= '
            <tr>
                <td><a href="player.php?id='.(int)$row['id'].'" class="text-white">'.unicode2html(h(substr($row["name"], 0, 12))).'</a></td>
                <td>'.(int)$row["kills"].'</td>
                <td>'.(int)$row["deaths"].'</td>
                <td>'.$kdr.'</td>
                <td>'.$ADR_roundup.'</td>
                <td class="'.$ratingCls.'" style="font-weight:bold;">'.$HLTV2_roundup.'</td>
            </tr>';
        } elseif ($row["team"] == 3) {
            $ct_name = $row["team_3_name"];
            if ($ct_name == NULL) {
                $ct_name = "Counter-Terrorists";
            }
            $ct_score = $row["team_3"];

            $ct .= '
            <tr>
                <td><a href="player.php?id='.(int)$row['id'].'" class="text-white">'.unicode2html(h(substr($row["name"], 0, 12))).'</a></td>
                <td>'.(int)$row["kills"].'</td>
                <td>'.(int)$row["deaths"].'</td>
                <td>'.$kdr.'</td>
                <td>'.$ADR_roundup.'</td>
                <td class="'.$ratingCls.'" style="font-weight:bold;">'.$HLTV2_roundup.'</td>
            </tr>';
        }
    }

    if (empty($ct)) {
        $ct = '<tr><td colspan="6" class="empty-state" style="margin-top:10px;">No Players Recorded!</td></tr>';
    }
    if (empty($t)) {
        $t = '<tr><td colspan="6" class="empty-state" style="margin-top:10px;">No Players Recorded!</td></tr>';
    }

    echo '
    <div class="row" style="margin-top:20px;">
        <div class="col-md-12">
            <div class="card rounded-borders" style="border: none !important;">
                <div class="card-body" style="padding:0;">
                    <div class="card-header-bar bg-ct">
                        <h3><a href="team.php?name='.urlencode($ct_name).'" class="text-white" style="text-decoration:none;">'.h($ct_name).'</a></h3>
                    </div>
                    <div class="table-responsive" style="border: none !important;">
                        <table class="table sortable" id="ct-table">
                            <thead class="table-borderless" style="border: none !important;">
                                <tr>
                                    <th style="width:200px;" data-sort="string">Player</th>
                                    <th data-sort="number">Kills <span class="sort-arrow"></span></th>
                                    <th data-sort="number">Deaths <span class="sort-arrow"></span></th>
                                    <th data-sort="number">KDR <span class="sort-arrow"></span></th>
                                    <th data-sort="number">ADR <span class="sort-arrow"></span></th>
                                    <th data-sort="number">Rating <span class="sort-arrow"></span></th>
                                </tr>
                            </thead>
                            <tbody>
                            '.$ct.'
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <h1 class="d-flex align-items-center justify-content-center text-center" style="margin-top:10px;margin-bottom:5px;">
                <img src="assets/img/icons/'.h($ct_name).'.png" style="margin-right:8px;" height="38" width="38">
                <span style="color:#5b768d;">'.(int)$ct_score.'</span>:<span style="color:#ac9b66;">'.(int)$t_score.'</span>
                <img src="assets/img/icons/'.h($t_name).'.png" style="margin-left:8px;" height="38" width="38">
            </h1>
        </div>
        <div class="col-md-12">
            <span class="d-flex align-items-center justify-content-center text-center" style="margin-bottom:5px;">Map: '.h($map).'</span>
        </div>
        <div class="col-md-12">
            <small class="d-flex align-items-center justify-content-center text-center text-muted" style="margin-bottom:10px;">'.$timestamp_str.'</small>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card rounded-borders" style="border: none !important;">
                <div class="card-body" style="padding:0;">
                    <div class="card-header-bar bg-t">
                        <h3><a href="team.php?name='.urlencode($t_name).'" class="text-white" style="text-decoration:none;">'.h($t_name).'</a></h3>
                    </div>
                    <div class="table-responsive" style="border-top:none !important;">
                        <table class="table sortable" id="t-table">
                            <thead class="table-borderless" style="border: none !important;">
                                <tr>
                                    <th style="width:200px;" data-sort="string">Player</th>
                                    <th data-sort="number">Kills <span class="sort-arrow"></span></th>
                                    <th data-sort="number">Deaths <span class="sort-arrow"></span></th>
                                    <th data-sort="number">KDR <span class="sort-arrow"></span></th>
                                    <th data-sort="number">ADR <span class="sort-arrow"></span></th>
                                    <th data-sort="number">Rating <span class="sort-arrow"></span></th>
                                </tr>
                            </thead>
                            <tbody>
                            '.$t.'
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>';
} else {
    echo '<h4 class="empty-state">No Match with that ID!</h4>';
}

$conn->close();
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

            // Reset all arrows in this table
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
