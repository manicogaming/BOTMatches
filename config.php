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
    "assets/img/maps/austria.jpg" => "de_austria",
    "assets/img/maps/cache.jpg" => "de_cache",
    "assets/img/maps/canals.jpg" => "de_canals",
    "assets/img/maps/cbble.jpg" => "de_cbble",
    "assets/img/maps/dust.png" => "de_dust",
	"assets/img/maps/dust.png" => "de_shortdust",
    "assets/img/maps/dust2.jpg" => "de_dust2",
    "assets/img/maps/mirage.jpg" => "de_mirage",
    "assets/img/maps/nuke.jpg" => "de_nuke",
	"assets/img/maps/nuke.jpg" => "de_shortnuke",
    "assets/img/maps/overpass.jpg" => "de_overpass",
    "assets/img/maps/train.jpg" => "de_train",
    "assets/img/maps/inferno.jpg" => "de_inferno"
);

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>