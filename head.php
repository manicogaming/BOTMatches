<?php
global $site_name;

// Determine current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);

echo '        
    <a class="text-white" href="./">
        <h1 class="text-center">'.$site_name.'</h1>
    </a>
    <div class="nav-links">
        <a href="./"'.($current_page == 'index.php' ? ' class="active"' : '').'>Matches</a>
        <a href="leaderboard.php"'.($current_page == 'leaderboard.php' ? ' class="active"' : '').'>Leaderboard</a>
        <a href="teams.php"'.($current_page == 'teams.php' ? ' class="active"' : '').'>Teams</a>
    </div>';
?>
