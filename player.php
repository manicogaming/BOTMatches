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

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootswatch/4.1.2/darkly/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lato:400,700,400italic">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.5.2/animate.min.css">
    <link rel="stylesheet" href="assets/css/Search-Field-With-Icon.css?h=407fbd3e4331a9634a54008fed5b49b9">
    <link rel="stylesheet" href="assets/css/styles.css?h=a95bd1c65d4dfacc3eae1239db3fae0b">
</head>

<body>
<div class="container" style="margin-top:20px;">
    <?php
include ('head.php');
?>
    <?php 
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $rowround = $roundresult->fetch_assoc();
		$AVERAGE_KPR = 0.679;
		$AVERAGE_SPR = 0.317;
		$AVERAGE_RMK = 1.277;
		$rounds = ($rowround["team_2_total"] + $rowround["team_3_total"]);
		
        echo '<div style="width:100%;margin-right:auto;margin-left:auto;background-color:#282828;margin-top:25px;">
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
							echo '<li class="list-group-item font-weight-bold text-center mt-2" style="color:green;margin-top:10px;"><span style="color:white;">Average Rating: </span>'.$rating_roundup;
						}
						else
						{
							echo '<li class="list-group-item font-weight-bold text-center mt-2" style="color:red;margin-top:10px;"><span style="color:white;">Average Rating: </span>'.$rating_roundup;
						}
						
						echo '</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total Kills: </strong>'.$row["totalkills"].'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total Deaths: </strong>'.$row["totaldeaths"].'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Average KDR: </strong>'.$kdr_roundup.'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Average ADR: </strong>'.$ADR_roundup.'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 5Ks: </strong>'.$row["total5k"].'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 4Ks: </strong>'.$row["total4k"].'</li><li class="list-group-item text-center" style="margin-top:10px;"><strong>Total 3Ks: </strong>'.$row["total3k"].'</li>';
                  
                
                echo '</ul></div><div class="col-lg-12 text-center" style="margin-top:10px;"><a class="btn btn-light py-2 px-4" href="http://192.168.1.69/rankme/profile.php?steamID='.$row['name'].'" target="_blank" rel="noopener nofollow">More Stats</a></div></div></div></div>'; 
				
                
    } else {
        echo '<h4 style="margin-top:40px;text-align:center;">No Match with that ID!</h4>';
    }
    $conn->close();
    ?>
    <a class="text-info" href="https://github.com/DistrictNineHost/Sourcemod-SQLMatches" target="_blank" style="position:fixed;bottom:0px;right:10px;">Developed by DistrictNine.Host</a>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.2/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/bs-animation.js?h=98fdbbd86223499341d76166d015c405"></script>
</body>

</html>
