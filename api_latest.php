<?php
require_once("config.php");

$result = $conn->query("SELECT MAX(match_id) AS latest FROM sql_matches_scoretotal");
$row = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode(['match_id' => (int)$row['latest']]);

$conn->close();
?>
