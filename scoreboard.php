<?php
require ("config.php");

if (isset($_GET["id"])) {
    $match_id = $_GET["id"];
} else {
    $match_id = 0;
}

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
        require ("head.php");
		
		function unicode2html($str){
            // Set the locale to something that's UTF-8 capable
            setlocale(LC_ALL, 'en_US.UTF-8');
            // Convert the codepoints to entities
            $str = preg_replace("/u([0-9a-fA-F]{4})/", "&#x\\1;", $str);
            // Convert the entities to a UTF-8 string
            return iconv("UTF-8", "ISO-8859-1//TRANSLIT", $str);
        }
		
        $match_id = $conn->real_escape_string($match_id);
		$sql = "SELECT sql_players.id, sql_matches.match_id, sql_matches_scoretotal.timestamp, sql_matches_scoretotal.map, sql_matches_scoretotal.team_2, sql_matches_scoretotal.team_2_name, sql_matches_scoretotal.team_3, sql_matches_scoretotal.team_3_name, sql_matches.name, sql_matches.kills, sql_matches.assists, sql_matches.deaths, sql_matches.team, sql_matches.5k, sql_matches.4k, sql_matches.3k, sql_matches.damage, sql_matches.kastrounds
        FROM sql_matches_scoretotal INNER JOIN sql_matches INNER JOIN sql_players
        ON sql_matches_scoretotal.match_id = sql_matches.match_id AND sql_players.name LIKE sql_matches.name
        WHERE sql_matches_scoretotal.match_id = ".$match_id." ORDER BY sql_matches.kills DESC";     
		
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0) {
            $t = '';
            $ct = '';
			$HLTV2_KAST_MOD = 0.0073; // KAST modifier
			$HLTV2_KPR_MOD = 0.3591; // KPR modifier
			$HLTV2_DPR_MOD = -0.5329; // DPR modifier
			$HLTV2_IMPACT_MOD = 0.2372; // Impact modifier
			$HLTV2_IMPACT_KPR_MOD = 2.13; //Impact KPR modifier
			$HLTV2_IMPACT_APR_MOD = 0.42; //Impact AssistPerRound modifier
			$HLTV2_IMPACT_OFFSET_MOD = -0.41; //Impact base modifier
			$HLTV2_ADR_MOD = 0.0032; // ADR modifier
			$HLTV2_OFFSET_MOD = 0.1587; // HLTV2 base modifier
			
            while ($row = $result->fetch_assoc()) {
				$rounds = ($row["team_2"] + $row["team_3"]);
				$map = $row["map"];
				
                if ($row["kills"] > 0 && $row["deaths"] > 0) {
                    $kdr = round(($row["kills"] / $row["deaths"]), 2); 
                } else {
                    $kdr = 0;
                }
					if ($row["team"] == 2) {
						$t_name = $row["team_2_name"];
						if ($t_name == NULL) {
							$t_name = "Terrorists";
						}	
						
						$t_score = $row["team_2"];
						
						$KAST = $HLTV2_KAST_MOD * ($row["kastrounds"] / $rounds) * 100.0;
						
						$KPR = $HLTV2_KPR_MOD * $row["kills"] / $rounds;
						
						$DPR = $HLTV2_DPR_MOD * $row["deaths"] / $rounds;
						
						$ADR = $HLTV2_ADR_MOD * $row["damage"] / $rounds;
						
						$Impact = $HLTV2_IMPACT_MOD * (($HLTV2_IMPACT_KPR_MOD * ($row["kills"] / $rounds)) + ($HLTV2_IMPACT_APR_MOD * ($row["assists"] / $rounds)) + $HLTV2_IMPACT_OFFSET_MOD);
						
						$HLTV2 = $KAST + $KPR + $DPR + $Impact + $ADR + $HLTV2_OFFSET_MOD;
						
						$HLTV2_roundup = round($HLTV2,2); 
						
						$ADR = $row["damage"]/ $rounds;
						$ADR_roundup = round($ADR,0);
						$t .= '
						<tr>
							<td><a href="player.php?id='.$row['id'].'" class="text-white">'.unicode2html(htmlspecialchars(substr($row["name"],0,12))).'</a></td>
							<td>'.$row["kills"].'</td>
							<td>'.$row["deaths"].'</td>
							<td>'.$kdr.'</td>
							<td>'.$ADR_roundup.'</td>
							<td>'.$HLTV2_roundup.'</td>
						</tr>';
					} elseif ($row["team"] == 3) {
						$ct_name = $row["team_3_name"];
						if ($ct_name == NULL) {
							$ct_name = "Counter-Terrorists";
						}
						
						$ct_score = $row["team_3"];
						
						$KAST = $HLTV2_KAST_MOD * ($row["kastrounds"] / $rounds) * 100.0;
						
						$KPR = $HLTV2_KPR_MOD * $row["kills"] / $rounds;
						
						$DPR = $HLTV2_DPR_MOD * $row["deaths"] / $rounds;
						
						$ADR = $HLTV2_ADR_MOD * $row["damage"] / $rounds;
						
						$Impact = $HLTV2_IMPACT_MOD * (($HLTV2_IMPACT_KPR_MOD * ($row["kills"] / $rounds)) + ($HLTV2_IMPACT_APR_MOD * ($row["assists"] / $rounds)) + $HLTV2_IMPACT_OFFSET_MOD);
						
						$HLTV2 = $KAST + $KPR + $DPR + $Impact + $ADR + $HLTV2_OFFSET_MOD;
						
						$HLTV2_roundup = round($HLTV2,2); 
						
						$ADR = $row["damage"]/ $rounds;
						$ADR_roundup = round($ADR,0);
						$ct .= '
						<tr>
							<td><a href="player.php?id='.$row['id'].'" class="text-white">'.unicode2html(htmlspecialchars(substr($row["name"],0,12))).'</a></td>
							<td>'.$row["kills"].'</td>
							<td>'.$row["deaths"].'</td>
							<td>'.$kdr.'</td>
							<td>'.$ADR_roundup.'</td>
							<td>'.$HLTV2_roundup.'</td>
						</tr>';
					}
                }
				if (!isset($ct)) {
                    $ct = '<h3 style="margin-top:20px;text-align:center;">No Players Recorded!</h3>';
                }
                if (!isset($t)) {
                    $t = '<h3 style="margin-top:20px;text-align:center;">No Players Recorded!</h3>';
                }
                echo '
                <div class="row" style="margin-top:20px;">
                    <div class="col-md-12">
                        <div class="card rounded-borders" style="border: none !important;">
                            <div class="card-body" style="padding-right:0px;padding-left:0px;padding-bottom:0px;padding-top:0px;">
                            <div style="background-color:#5b768d;height:25px;">
                                <h3 class="text-uppercase text-center text-white" style="font-size:22px;">'.$ct_name.'<br></h3>
                            </div>
                                <div class="table-responsive" style="border: none !important;">
                                    <table class="table">
                                        <thead class="table-borderless" style="border: none !important;">
                                            <tr>
                                                <th style="width:200px;">Player</th>
                                                <th>Kills</th>
                                                <th>Deaths</th>
                                                <th>KDR</th>
                                                <th>ADR</th>
                                                <th>Rating</th>
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
                        <h1 class="d-flex align-items-center justify-content-center text-center" style="margin-top:10px;margin-bottom:5px;"><img src="assets/img/icons/'.$ct_name.'.png" style="margin-right:8px;" height=38px width=38px><span style="color:#5b768d;">'.$ct_score.'</span>:<span style="color:#ac9b66;">'.$t_score.'</span><img src="assets/img/icons/'.$t_name.'.png" style="margin-left:8px;" height=38px width=38px></h1>
                    </div>
					<div class="col-md-12">
                        <span class="d-flex align-items-center justify-content-center text-center" style="margin-bottom:10px;">Map: '.$map.'</h1>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card rounded-borders" style="border: none !important;">
                            <div class="card-body" style="padding-right:0px;padding-left:0px;padding-bottom:0px;padding-top:0px;">
                                <div style="background-color:#ac9b66;height:25px;">
                                    <h3 class="text-uppercase text-center text-white" style="font-size:22px;">'.$t_name.'<br></h3>
                                </div>
                                <div class="table-responsive" style="border-top:none !important;">
                                    <table class="table">
                                        <thead class="table-borderless" style="border: none !important;">
                                            <tr>
                                                <th style="width:200px;">Player</th>
                                                <th>Kills</th>
                                                <th>Deaths</th>
                                                <th>KDR</th>
                                                <th>ADR</th>
                                                <th>Rating</th>
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
                </div>
                </div>';
        } else {
            echo '<h4 style="margin-top:40px;text-align:center;">No Match with that ID!</h4>';
        }
?>
    <a class="text-white" href="https://github.com/DistrictNineHost/Sourcemod-SQLMatches" target="_blank" style="position:fixed;bottom:0px;right:10px;">Developed by DistrictNine.Host</a>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.2/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/bs-animation.js?h=98fdbbd86223499341d76166d015c405"></script>
</body>

</html>
