<?php
/**
 * check_status.php
 * AJAX endpoint: returns current traffic usage and limit for a user (JSON).
 * Called periodically by the JS on the hotspot success page.
 */
include("config.php");
header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if (!isset($_GET['username']) || $_GET['username'] === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username']);
    exit();
}

$username = mysqli_real_escape_string($db, $_GET['username']);

// Get traffic from the most recent active session in radacct
$sql = "SELECT acctinputoctets + acctoutputoctets AS traffic
        FROM radacct
        WHERE username = '$username'
        ORDER BY acctstarttime DESC
        LIMIT 1";
$result = mysqli_query($db, $sql);
$traffic = 0;
if ($result && $row = mysqli_fetch_assoc($result)) {
    $traffic = intval($row['traffic']);
}

// Look up per-user traffic limit in radreply first
$sql = "SELECT value FROM radreply
        WHERE username = '$username'
          AND attribute = 'ChilliSpot-Max-Total-Octets'
        LIMIT 1";
$result = mysqli_query($db, $sql);
$traffic_limit = 0;
if ($result && $row = mysqli_fetch_assoc($result)) {
    $traffic_limit = intval($row['value']);
}

// Fall back to group-level limit in radgroupreply
if ($traffic_limit === 0) {
    $sql = "SELECT value FROM radgroupreply
            WHERE attribute = 'ChilliSpot-Max-Total-Octets'
            LIMIT 1";
    $result = mysqli_query($db, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $traffic_limit = intval($row['value']);
    }
}

echo json_encode([
    'traffic'       => $traffic,
    'traffic_limit' => $traffic_limit,
]);
