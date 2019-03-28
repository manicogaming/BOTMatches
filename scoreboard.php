<?php
require ('config.php');

if(isset($_GET["id"])){
    $id = $_GET["id"];
} else {
    $id = 0;
}

settype($id, "integer"); 

$sql = "SELECT sql_players.id, sql_matches.match_id, sql_matches_scoretotal.timestamp, sql_matches_scoretotal.map, sql_matches_scoretotal.team_2, sql_matches_scoretotal.team_2_name, sql_matches_scoretotal.team_3, sql_matches_scoretotal.team_3_name, sql_matches.name, sql_matches.kills, sql_matches.deaths, sql_matches.team, sql_matches.5k, sql_matches.4k, sql_matches.3k, sql_matches.damage
        FROM sql_matches_scoretotal INNER JOIN sql_matches INNER JOIN sql_players
        ON sql_matches_scoretotal.match_id = sql_matches.match_id AND sql_players.name LIKE sql_matches.name
        WHERE sql_matches_scoretotal.match_id = ".$id." ORDER BY sql_matches.kills DESC";

$result_stats1 = $conn->query($sql);
$result_stats2 = $conn->query($sql);
$result = $conn->query($sql);
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
		$AVERAGE_KPR = 0.679;
		$AVERAGE_SPR = 0.317;
		$AVERAGE_RMK = 1.277;
		$rounds = ($row["team_2"] + $row["team_3"]);
		
        echo '<div class="card pulse" style="width:100%;margin-right:auto;margin-left:auto;background-color:#282828;background-image: linear-gradient(#282828 , white 60%);margin-top:25px;">
        <div class="container-fluid card-body">
			<div class="row">
				<div class="col-lg-2 d-flex justify-content-start justify-content-lg-end">
					<img src="assets/img/logos/'.$row["team_2_name"].'.png" style="width:125px;height:125px;">
				</div>
				<div class="col-lg-8">
					<div class="row" style="font-size:50px;margin-bottom:0px;margin-top:25px;">
						<div class="col-lg-5">
							<p class="text-white">'.$row["team_2_name"].'</p>
						</div>
						<div class="col-lg-2 text-center text-white">
							<strong style="color:rgb(91,118,141);">'.$row["team_2"].'</strong>:<strong style="color:rgb(172,155,102);">'.$row["team_3"].'</strong>
						</div>
						<div class="col-lg-5 text-right">
							<p class="text-white">'.$row["team_3_name"].'</p>
						</div>
					</div>
				</div>
				<div class="col-lg-2 d-flex justify-content-end justify-content-lg-start">
					<img src="assets/img/logos/'.$row["team_3_name"].'.png" style="width:125px;height:125px;">
				</div>
			</div>
			<div class="row my-4">
				<div class="col-lg-12">
					<h1 class="text-center text-white" style="font-size:20px;">Map: '.$row["map"].'</h1>
					<h1 class="text-center text-white" style="font-size:20px;">Ended: '.$row["timestamp"].'</h1>
				</div>
			</div>
			<div class="row mt-3">
				<div class="col-lg-5">
					<div class="row no-gutters text-center">
						<div class="col-lg-7">
							<p>Player</p>
						</div>
						<div class="col-lg-1">
							<p>Kills</p>
						</div>
						<div class="col-lg-1">
							<p>Deaths</p>
						</div>
						<div class="col-lg-1">
							<p>KDR</p>
						</div>
						<div class="col-lg-1">
							<p class="pr-2">ADR</p>
						</div>
						<div class="col-lg-1">
							<p class="pr-3">Rating</p>
						</div>
					</div>
				</div>
				<div class="col-lg-2">
				</div>
				<div class="col-lg-5">
					<div class="row no-gutters text-center">
						<div class="col-lg-7">
							<p>Player</p>
						</div>
						<div class="col-lg-1">
							<p>Kills</p>
						</div>
						<div class="col-lg-1">
							<p>Deaths</p>
						</div>
						<div class="col-lg-1">
							<p>KDR</p>
						</div>
						<div class="col-lg-1">
							<p class="pr-2">ADR</p>
						</div>
						<div class="col-lg-1">
							<p class="pr-3">Rating</p>
						</div>
					</div>
				</div>
			</div>
            <div class="row">
				<div class="col-lg-5">
				<ul class="list-group">';

                while($row = $result_stats1->fetch_assoc()) {
                    if($row['team'] == '2') { 
    
                        if($row["kills"] && $row["deaths"] > 0){
                            $kdr = ($row["kills"]/$row["deaths"]); 
                            $kdr_roundup = round($kdr,2);
    
                        } else {
                            $kdr_roundup = $row["kills"];

                        }
						$killRating = $row["kills"] / $rounds / $AVERAGE_KPR;
						
						$survivalRating = ($rounds - $row["deaths"]) / $rounds / $AVERAGE_SPR;
						
						$rounds1k = $rounds - ($row["3k"] + $row["4k"] + $row["5k"]);
						$roundsWithMultipleKillsRating = ($rounds1k + 4 * 0 + 9 * $row["3k"] + 16 * $row["4k"] + 25 * $row["5k"]) / $rounds / $AVERAGE_RMK;
						
						$rating = ($killRating + 0.7 * $survivalRating + $roundsWithMultipleKillsRating) / 2.7;
						$rating_roundup = round($rating,2); 
						
						$ADR = $row["damage"]/ $rounds;
						$ADR_roundup = round($ADR,0);
						
						if($rating_roundup > 1)
						{
							echo '<li class="list-group-item text-center mt-2"><span class="float-right font-weight-bold" style="color:green;margin-top:10px;width:39px;">'.$rating_roundup;
						}
						else
						{
							echo '<li class="list-group-item text-center mt-2"><span class="float-right font-weight-bold" style="color:red;margin-top:10px;width:39px;">'.$rating_roundup;
						}
						
						echo '</span><span class="float-right" style="margin-top:10px;width:39px;margin-right:20px;">'.$ADR_roundup.'</span><span class="float-right" style="margin-top:10px;width:39px;margin-right:20px;">'.$kdr_roundup.'</span><span class="float-right" style="margin-top:10px;width:39px;margin-right:20px;">'.$row["deaths"].'</span><span class="float-right" style="margin-top:10px;width:50px;margin-right:20px;">'.$row["kills"].'</span><a href="players/'.htmlspecialchars(substr($row['name'],0,128)).'.php"><a href="player.php?id='.$row['id'].'"><span class="float-left" style="margin-top:10px;margin-left:5px;color:#000000;">'.htmlspecialchars(substr($row['name'],0,128)).'</span></a></li>';
                    }
                }
                
                echo '</ul></div><div class="col-lg-2"></div><div class="col-lg-5"><ul class="list-group">';             

                while($row = $result_stats2->fetch_assoc()){
                    if($row['team'] == '3'){
    
                        if($row["kills"] && $row["deaths"] > 0){
                            $kdr = ($row["kills"]/$row["deaths"]); 
                            $kdr_roundup = round($kdr,2);
    
                        } else {
                            $kdr_roundup = $row["kills"];
                        }
						$killRating = $row["kills"] / $rounds / $AVERAGE_KPR;
						
						$survivalRating = ($rounds - $row["deaths"]) / $rounds / $AVERAGE_SPR;
						
						$rounds1k = $rounds - ($row["3k"] + $row["4k"] + $row["5k"]);
						$roundsWithMultipleKillsRating = ($rounds1k + 4 * 0 + 9 * $row["3k"] + 16 * $row["4k"] + 25 * $row["5k"]) / $rounds / $AVERAGE_RMK;
						
						$rating = ($killRating + 0.7 * $survivalRating + $roundsWithMultipleKillsRating) / 2.7;
						$rating_roundup = round($rating,2); 
						
						$ADR = $row["damage"]/ $rounds;
						$ADR_roundup = round($ADR,0);
                        
                        if($rating_roundup > 1)
						{
							echo '<li class="list-group-item text-center mt-2"><span class="float-right font-weight-bold" style="color:green;margin-top:10px;width:39px;">'.$rating_roundup;
						}
						else
						{
							echo '<li class="list-group-item text-center mt-2"><span class="float-right font-weight-bold" style="color:red;margin-top:10px;width:39px;">'.$rating_roundup;
						}
						
						echo '</span><span class="float-right" style="margin-top:10px;width:39px;margin-right:20px;">'.$ADR_roundup.'</span><span class="float-right" style="margin-top:10px;width:39px;margin-right:20px;">'.$kdr_roundup.'</span><span class="float-right" style="margin-top:10px;width:39px;margin-right:20px;">'.$row["deaths"].'</span><span class="float-right" style="margin-top:10px;width:50px;margin-right:20px;">'.$row["kills"].'</span><a href="players/'.htmlspecialchars(substr($row['name'],0,128)).'.php"><a href="player.php?id='.$row['id'].'"><span class="float-left" style="margin-top:10px;margin-left:5px;color:#000000;">'.htmlspecialchars(substr($row['name'],0,128)).'</span></a></li>';
                    }
                }
                
                echo '</ul></div></div></div></div>';
                
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
