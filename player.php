<?php
require ('config.php');

if(isset($_GET["id"])){
    $id = $_GET["id"];
} else {
    $id = 0;
}

settype($id, "integer"); 

$sql = "SELECT sql_players.id, sql_matches.name, SUM(sql_matches.kills) AS totalkills, SUM(sql_matches.deaths) AS totaldeaths, SUM(sql_matches.5k) AS total5k, SUM(sql_matches.4k) AS total4k, SUM(sql_matches.3k) AS total3k, SUM(sql_matches.damage) AS totaldamage
        FROM sql_matches INNER JOIN sql_players
        ON sql_players.name LIKE sql_matches.name
        WHERE sql_players.id = ".$id."";
		
$sqltotalrounds = "SELECT SUM(sql_matches_scoretotal.team_2) AS team_2_total, SUM(sql_matches_scoretotal.team_3) AS team_3_total
        FROM sql_matches_scoretotal INNER JOIN sql_matches INNER JOIN sql_players
        ON sql_matches_scoretotal.match_id = sql_matches.match_id AND sql_players.name LIKE sql_matches.name
        WHERE sql_players.id = ".$id."";

$result = $conn->query($sql);
$roundresult = $conn->query($sqltotalrounds);
?>
<!DOCTYPE html>
<html>

<?php
include ('head.php');
?>

<body>
    <a href="index.php" style="color:#000000;"><h1 class="text-center" style="margin-top:15px;"><?php echo $site_name; ?></h1></a>
    <?php 
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $rowround = $roundresult->fetch_assoc();
		$AVERAGE_KPR = 0.679;
		$AVERAGE_SPR = 0.317;
		$AVERAGE_RMK = 1.277;
		$rounds = ($rowround["team_2_total"] + $rowround["team_3_total"]);
		
        echo '<div class="card pulse" style="width:100%;margin-right:auto;margin-left:auto;background-color:#282828;background-image: linear-gradient(#282828 40%, white );margin-top:25px;">
        <div class="container-fluid card-body">
			<div class="row">
				<div class="col-md-12 text-white text-center" style="font-size:50px;margin-bottom:25px;">
					<strong>'.$row["name"].'</strong>
				</div>
			</div>
            <div class="row">
				<div class="col-lg-12">
				<ul class="list-group">';
    
                        if($row["totalkills"] && $row["totaldeaths"] > 0){
                            $kdr = ($row["totalkills"]/$row["totaldeaths"]); 
                            $kdr_roundup = round($kdr,2);
    
                        } else {
                            $kdr_roundup = $row["totalkills"];

                        }
						$killRating = $row["totalkills"] / $rounds / $AVERAGE_KPR;
						
						$survivalRating = ($rounds - $row["totaldeaths"]) / $rounds / $AVERAGE_SPR;
						
						$rounds1k = $rounds - ($row["total3k"] + $row["total4k"] + $row["total5k"]);
						$roundsWithMultipleKillsRating = ($rounds1k + 4 * 0 + 9 * $row["total3k"] + 16 * $row["total4k"] + 25 * $row["total5k"]) / $rounds / $AVERAGE_RMK;
						
						$rating = ($killRating + 0.7 * $survivalRating + $roundsWithMultipleKillsRating) / 2.7;
						$rating_roundup = round($rating,2); 
						
						$ADR = $row["totaldamage"]/ $rounds;
						$ADR_roundup = round($ADR,0);
						
						if($rating_roundup > 1)
						{
							echo '<li class="list-group-item font-weight-bold text-center mt-2" style="color:green;margin-top:10px;"><span style="color:#212529;">Average Rating: </span>'.$rating_roundup;
						}
						else
						{
							echo '<li class="list-group-item font-weight-bold text-center mt-2" style="color:red;margin-top:10px;"><span style="color:#212529;">Average Rating: </span>'.$rating_roundup;
						}
						
						echo '</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total Kills: </strong>'.$row["totalkills"].'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total Deaths: </strong>'.$row["totaldeaths"].'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Average KDR: </strong>'.$kdr_roundup.'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Average ADR: </strong>'.$ADR_roundup.'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 5Ks: </strong>'.$row["total5k"].'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 4Ks: </strong>'.$row["total4k"].'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 3Ks: </strong>'.$row["total3k"].'</li>';
                  
                
                echo '</ul></div><div class="col-lg-12 text-center" style="margin-top:10px;"><a class="btn btn-outline-dark py-2 px-4" href="localhost:8080/player/'.$row['id'].'" target="_blank" rel="noopener nofollow">More Stats</a></div></div></div></div>';    
                
    } else {
        echo '<h4 style="margin-top:40px;text-align:center;">No Match with that ID!</h4>';
    }
    $conn->close();
    ?>
    <div class="bottom"><a href="https://github.com/WardPearce/Sourcemod-SQLMatches" target="_blank">Created By Ward, Edited by manico</a></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.2/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/bs-animation.js"></script>
</body>

</html>
