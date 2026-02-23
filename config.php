<?php
$site_name = "Matches"; // Name of Site
$page_title = "Matches"; // Page title in browser.
$limit = 10; // Page Limit for match cards.
$leaderboard_min_matches = 100; // Minimum matches for leaderboard
$teams_min_matches = 10; // Minimum matches for a team to appear in rankings
$bot_rosters_path = 'C:\\csgosl\\server\\csgo\\addons\\sourcemod\\configs\\bot_rosters.txt'; // Path to active team rosters (VDF format)

// Database Configuration
// NOTE: If you ever expose this site beyond localhost, change these credentials!
$servername = "localhost"; // Server IP
$username = "root"; // DB Username
$password = ""; // DB Password
$dbname = "db"; // DB Name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
