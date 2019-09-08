<?php
$site_name = "Matches"; // Name of Site
$page_title = "Matches"; // Page title in browser.
$limit = 10; // Page Limit for match cards.


$servername = "localhost"; // Server IP
$username = "root"; // DB Username
$password = ""; // DB Password
$dbname = "db"; // DB Name

$maps = array(
    // "Path/To/Image" => "full_map_name",
    'assets/img/maps/$2000$_csgo.jpg' => '$2000$_csgo',
    'assets/img/maps/$2000$_pro_b2.jpg' => '$2000$_pro_b2',
    "assets/img/maps/35hp_colors.jpg" => "35hp_colors",
    "assets/img/maps/35hp_gladiators_go.jpg" => "35hp_gladiators_go",
    "assets/img/maps/35hp_iceworld.jpg" => "35hp_iceworld",
    "assets/img/maps/35hp_lego_go.jpg" => "35hp_lego_go",
    "assets/img/maps/35hp_peace.jpg" => "35hp_peace",
    "assets/img/maps/35hp_rush_hour.jpg" => "35hp_rush_hour",
    "assets/img/maps/aim_100hp.jpg" => "aim_100hp",
    "assets/img/maps/aim_ak-colt.jpg" => "aim_ak-colt",
    "assets/img/maps/aim_deagle7k_real.jpg" => "aim_deagle7k_real",
    "assets/img/maps/aim_headshot2_csgo.jpg" => "aim_headshot2_csgo",
    "assets/img/maps/aim_map.jpg" => "aim_map",
    "assets/img/maps/aim_map_gamma.jpg" => "aim_map_gamma",
    "assets/img/maps/aim_redline.jpg" => "aim_redline",
    "assets/img/maps/ar_baggage.jpg" => "ar_baggage",
    "assets/img/maps/ar_dizzy.jpg" => "ar_dizzy",
    "assets/img/maps/ar_monastery.jpg" => "ar_monastery",
    "assets/img/maps/ar_shoots.jpg" => "ar_shoots",
    "assets/img/maps/cs_agency.jpg" => "cs_agency",
    "assets/img/maps/cs_assault.jpg" => "cs_assault",
    "assets/img/maps/cs_italy.jpg" => "cs_italy",
    "assets/img/maps/cs_militia.jpg" => "cs_militia",
    "assets/img/maps/cs_office.jpg" => "cs_office",
    "assets/img/maps/de_austria.jpg" => "de_austria",
    "assets/img/maps/de_bank.jpg" => "de_bank",
    "assets/img/maps/de_breach.jpg" => "de_breach",
    "assets/img/maps/de_cache.jpg" => "de_cache",
    "assets/img/maps/de_canals.jpg" => "de_canals",
    "assets/img/maps/de_cbble.jpg" => "de_cbble",
    "assets/img/maps/de_dust.png" => "de_dust",
    "assets/img/maps/de_dust2.jpg" => "de_dust2",
    "assets/img/maps/de_lake.jpg" => "de_lake",
    "assets/img/maps/de_mirage.jpg" => "de_mirage",
    "assets/img/maps/de_nuke.jpg" => "de_nuke",
	"assets/img/maps/de_overpass.jpg" => "de_overpass",
	"assets/img/maps/de_safehouse.jpg" => "de_safehouse",
	"assets/img/maps/de_seaside.jpg" => "de_seaside",
	"assets/img/maps/de_shortdust.jpg" => "de_shortdust",
	"assets/img/maps/de_shortnuke.jpg" => "de_shortnuke",
	"assets/img/maps/de_stmarc.jpg" => "de_stmarc",
	"assets/img/maps/de_sugarcane.jpg" => "de_sugarcane",
    "assets/img/maps/de_train.jpg" => "de_train",
    "assets/img/maps/de_inferno.jpg" => "de_inferno",
    "assets/img/maps/de_vertigo.jpg" => "de_vertigo",
    "assets/img/maps/de_zoo.jpg" => "de_zoo"
);

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>