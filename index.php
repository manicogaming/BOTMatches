<?php
require_once("config.php");
require_once("functions.php");
require_once("maps.php");

if (isset($_GET["page"])) {
    $page_number = max(1, (int)$_GET["page"]);
} else {
    $page_number = 1;
}

// Get latest match_id for AJAX polling
$latest_result = $conn->query("SELECT MAX(match_id) AS latest FROM sql_matches_scoretotal");
$latest_row = $latest_result->fetch_assoc();
$latest_match_id = (int)$latest_row['latest'];

renderPageStart($page_title);

$is_search = isset($_POST['Submit']) && !empty($_POST['search-bar']);
?>
        <form method="post">
            <div class="search-container center" style="width:70%;"><input type="text" name="search-bar" placeholder="Search Match ID, Player Name or Team Name" class="search-input" value="<?php echo $is_search ? h($_POST['search-bar']) : ''; ?>"><button class="btn btn-light search-btn" type="submit" name="Submit"> <i class="fa fa-search"></i></button></div>
        </form>
        <div id="new-match-banner" class="new-match-banner text-white" onclick="location.reload();">
            <i class="fa fa-refresh"></i> New matches available — click to refresh
        </div>
<?php
    if ($is_search) {
        $search = $_POST['search-bar'];
        $like_search = "%".$search."%";
        $match_id_search = is_numeric($search) ? (int)$search : 0;

        $stmt = $conn->prepare("SELECT s.match_id, s.timestamp, s.map, s.team_2, s.team_2_name, s.team_3, s.team_3_name
                FROM sql_matches_scoretotal s
                WHERE s.team_2_name LIKE ?
                OR s.team_3_name LIKE ?
                OR s.match_id = ?
                OR s.match_id IN (SELECT m.match_id FROM sql_matches m WHERE m.name LIKE ?)
                ORDER BY s.match_id DESC");
        $stmt->bind_param("ssis", $like_search, $like_search, $match_id_search, $like_search);
        $stmt->execute();
        $result = $stmt->get_result();

    } else {
        $offset = ($page_number - 1) * $limit;
        $stmt = $conn->prepare("SELECT * FROM sql_matches_scoretotal ORDER BY match_id DESC LIMIT ?, ?");
        $stmt->bind_param("ii", $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rounds = ($row["team_2"] + $row["team_3"]);
            $half = $rounds / 2;

            if ($row["team_3"] > $half) {
                $image = $row["team_3_name"];
            } elseif ($row["team_2"] == $half && $row["team_3"] == $half) {
                $image = 'tie_icon.png';
            } else {
                $image = $row["team_2_name"];
            }

            $map_img = array_search($row["map"], $maps);
            $timestamp_str = isset($row["timestamp"]) ? formatTimestamp($row["timestamp"]) : '';

            if ($map_img === false) {
                // Map not in the array - show prominently so it can be added
                echo '
            <a href="scoreboard.php?id='.(int)$row["match_id"].'">
                <div class="card match-card center" data-bs-hover-animate="pulse" style="margin-top:35px;">
                    <div class="missing-map-card rounded-borders">
                        <div class="text-center">
                            <i class="fa fa-exclamation-triangle"></i> Missing map image: <strong>'.h($row["map"]).'</strong>
                        </div>
                    </div>
                    <div class="card-img-overlay container">
                        <div class="row align-items-center">
                            <div class="col-sm d-none d-md-block"><h4 class="text-white float-left" style="font-size:70px;margin-bottom:0">'.(int)$row['team_3'].':'.(int)$row['team_2'].'</h4></div>
                            <div class="col-12 col-sm text-center"><img class="img-fluid float-sm-right" src="assets/img/icons/'.h($image).'" style="max-width:110px;"></div>
                        </div>
                        <div class="row"><div class="col-12"><small class="text-white-50">'.$timestamp_str.'</small></div></div>
                    </div>
                </div>
            </a>';
            } else {
                echo '
            <a href="scoreboard.php?id='.(int)$row["match_id"].'">
                <div class="card match-card center" data-bs-hover-animate="pulse" style="margin-top:35px;"><img class="card-img w-100 d-block matches-img rounded-borders" style="background-image:url(&quot;'.h($map_img).'&quot;);height:150px;">
                    <div class="card-img-overlay container">
                        <div class="row align-items-center">
                            <div class="col-sm d-none d-md-block"><h4 class="text-white float-left" style="font-size:70px;margin-bottom:0">'.(int)$row['team_3'].':'.(int)$row['team_2'].'</h4></div>
                            <div class="col-12 col-sm text-center"><img class="img-fluid float-sm-right" src="assets/img/icons/'.h($image).'" style="max-width:110px;"></div>
                        </div>
                        <div class="row"><div class="col-12"><small class="text-white-50">'.$timestamp_str.'</small></div></div>
                    </div>
                </div>
            </a>';
            }
        }
    } else {
        echo '<h4 class="empty-state">No Results!</h4>';
    }

    // Pagination (only when not searching)
    if (!$is_search) {
        $sql_pages = "SELECT COUNT(*) FROM sql_matches_scoretotal";
        $result_pages = $conn->query($sql_pages);
        $row_pages = $result_pages->fetch_assoc();
        $total_pages = ceil($row_pages["COUNT(*)"] / $limit);

        echo '
        <nav style="margin-top:30px;width:80%;" class="center">
            <ul class="pagination">';

        if ($page_number == 1) {
            echo '
                <li class="page-item disabled"><span class="page-link">Previous</span></li>';
        } else {
            echo '
                <li class="page-item"><a class="page-link" href="?page='.($page_number - 1).'">Previous</a></li>';
        }

        for ($i = max(1, $page_number - 2); $i <= min($page_number + 4, $total_pages); $i++) {
            if ($i == $page_number) {
                echo '
                <li class="page-item active"><span class="page-link">'.$i.' <span class="sr-only">(current)</span></span></li>';
            } else {
                echo '
                <li class="page-item"><a class="page-link" href="?page='.$i.'">'.$i.'</a></li>';
            }
        }

        if ($page_number == $total_pages) {
            echo '
                <li class="page-item disabled"><span class="page-link">Next</span></li>';
        } else {
            echo '
                <li class="page-item"><a class="page-link" href="?page='.($page_number + 1).'">Next</a></li>';
        }

        echo '
            </ul>
        </nav>';
    }

    $conn->close();
?>
    <script>
    // AJAX polling for new matches (replaces auto-refresh)
    (function() {
        var latestMatchId = <?php echo $latest_match_id; ?>;
        var isSearch = <?php echo $is_search ? 'true' : 'false'; ?>;
        if (isSearch) return; // Don't poll during search

        setInterval(function() {
            fetch('api_latest.php')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.match_id > latestMatchId) {
                        document.getElementById('new-match-banner').style.display = 'block';
                        latestMatchId = data.match_id;
                    }
                })
                .catch(function() {}); // Silently ignore fetch errors
        }, 10000);
    })();
    </script>
<?php renderPageEnd(); ?>
