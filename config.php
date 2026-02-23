<?php
$site_name = "Matches"; // Name of Site
$page_title = "Matches"; // Page title in browser.
$limit = 10; // Page Limit for match cards.
$leaderboard_min_matches = 100; // Minimum matches for leaderboard
$leaderboard_cache_seconds = 300; // How often leaderboard recalculates (5 minutes)
$teams_cache_seconds = 300; // How often team rankings recalculate (5 minutes)
$teams_min_matches = 10; // Minimum matches for a team to appear in rankings

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
