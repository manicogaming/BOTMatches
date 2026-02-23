<?php
require_once("config.php");
require_once("functions.php");

if (isset($_GET["id"])) {
    $id = (int)$_GET["id"];
} else {
    $id = 0;
}

$stmt = $conn->prepare("SELECT sql_players.id, sql_matches.name, SUM(sql_matches.kills) AS totalkills, SUM(sql_matches.assists) AS totalassists, SUM(sql_matches.deaths) AS totaldeaths, SUM(sql_matches.`5k`) AS total5k, SUM(sql_matches.`4k`) AS total4k, SUM(sql_matches.`3k`) AS total3k, SUM(sql_matches.damage) AS totaldamage, SUM(sql_matches.kastrounds) AS totalkastrounds, COUNT(DISTINCT sql_matches.match_id) AS matches
    FROM sql_matches INNER JOIN sql_players
    ON sql_players.name = sql_matches.name
    WHERE sql_players.id = ?
    GROUP BY sql_players.id, sql_matches.name");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$stmtRounds = $conn->prepare("SELECT SUM(sql_matches_scoretotal.team_2) AS team_2_total, SUM(sql_matches_scoretotal.team_3) AS team_3_total
    FROM sql_matches_scoretotal INNER JOIN sql_matches INNER JOIN sql_players
    ON sql_matches_scoretotal.match_id = sql_matches.match_id AND sql_players.name = sql_matches.name
    WHERE sql_players.id = ?");
$stmtRounds->bind_param("i", $id);
$stmtRounds->execute();
$roundresult = $stmtRounds->get_result();

renderPageStart($page_title);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $rowround = $roundresult->fetch_assoc();
    $rounds = ($rowround["team_2_total"] + $rowround["team_3_total"]);
    if ($rounds <= 0) $rounds = 1; // Guard against division by zero

    $kdr_roundup = calculateKDR($row["totalkills"], $row["totaldeaths"]);
    $ADR_roundup = calculateADR($row["totaldamage"], $rounds);
    $HLTV2_roundup = calculateHLTV2($row["totalkills"], $row["totaldeaths"], $row["totalassists"], $row["totaldamage"], $row["totalkastrounds"], $rounds);

    $rating_color = ($HLTV2_roundup > 1) ? 'green' : 'red';

    echo '<div style="width:100%;margin-right:auto;margin-left:auto;background-color:#282828;margin-top:25px;">
    <div class="container-fluid card-body">
        <div class="row">
            <div class="col-md-12 text-white text-center" style="font-size:50px;margin-bottom:25px;">
                <strong>'.h($row["name"]).'</strong>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <ul class="list-group">
                    <li class="list-group-item font-weight-bold text-center mt-2" style="color:'.$rating_color.';margin-top:10px;">
                        <span style="color:white;">Average Rating: </span>'.$HLTV2_roundup.'
                    </li>
                    <li class="list-group-item text-center" style="margin-top:10px;"><strong>Total Matches: </strong>'.(int)$row["matches"].'</li>
                    <li class="list-group-item text-center" style="margin-top:10px;"><strong>Total Kills: </strong>'.(int)$row["totalkills"].'</li>
                    <li class="list-group-item text-center" style="margin-top:10px;"><strong>Total Deaths: </strong>'.(int)$row["totaldeaths"].'</li>
                    <li class="list-group-item text-center" style="margin-top:10px;"><strong>Average KDR: </strong>'.$kdr_roundup.'</li>
                    <li class="list-group-item text-center" style="margin-top:10px;"><strong>Average ADR: </strong>'.$ADR_roundup.'</li>
                    <li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 5Ks: </strong>'.(int)$row["total5k"].'</li>
                    <li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 4Ks: </strong>'.(int)$row["total4k"].'</li>
                    <li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 3Ks: </strong>'.(int)$row["total3k"].'</li>
                </ul>
            </div>
        </div>
    </div>
    </div>';
} else {
    echo '<h4 style="margin-top:40px;text-align:center;">No Player with that ID!</h4>';
}

$conn->close();

renderPageEnd();
?>
