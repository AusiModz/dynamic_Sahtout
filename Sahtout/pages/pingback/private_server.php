<?php
// Disable error reporting for production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

define('ALLOWED_ACCESS', true);

// Verify includes
try {
    require_once '../../includes/config.php';
    require_once '../../includes/session.php';
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Include failed."]);
    exit;
}

// Check database connection
if (!$site_db || !$site_db instanceof mysqli || $site_db->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit;
}

// Get and validate parameters
$secret = $_POST['secret'] ?? ($_GET['secret'] ?? null);
$userid = isset($_POST['userid']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['userid']) : (isset($_GET['userid']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['userid']) : null);
$userip = isset($_POST['userip']) ? filter_var($_POST['userip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) : (isset($_GET['userip']) ? filter_var($_GET['userip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) : null);
$voted  = isset($_POST['voted']) ? (int)$_POST['voted'] : (isset($_GET['voted']) ? (int)$_GET['voted'] : 0);

if (!$secret || !$userid || !$userip || $voted !== 1) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Missing or invalid secret, userid, userip, or voted."]);
    exit;
}

if (strlen($userid) > 32 || strlen($userid) === 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Invalid userid length."]);
    exit;
}

// Find Private-Server.ws site entry
$callback_file_name = 'private_server';
$stmt = $site_db->prepare("SELECT id, callback_secret, reward_points, cooldown_hours FROM vote_sites WHERE callback_file_name = ? AND uses_callback = 1");
if (!$stmt || !$stmt->bind_param("s", $callback_file_name) || !$stmt->execute()) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Failed to query vote_sites."]);
    exit;
}
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Private-Server.ws site not found."]);
    exit;
}
$site = $result->fetch_assoc();
$internal_site_id = (int)$site['id'];
$callback_secret  = $site['callback_secret'];
$reward_points    = (int)$site['reward_points'];
$cooldown_hours   = (int)$site['cooldown_hours'];
$stmt->close();

// Validate secret
if ($callback_secret && $secret !== $callback_secret) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Invalid secret key."]);
    exit;
}

// Validate user
$stmt = $site_db->prepare("SELECT account_id FROM user_currencies WHERE username = ? OR account_id = ?");
if (!$stmt || !$stmt->bind_param("ss", $userid, $userid) || !$stmt->execute()) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Failed to query user_currencies."]);
    exit;
}
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "User not found."]);
    exit;
}
$row = $result->fetch_assoc();
$user_id = (int)$row['account_id'];
$stmt->close();

// Check cooldown
$can_vote = true;
$stmt = $site_db->prepare("
    SELECT vote_timestamp
    FROM vote_log
    WHERE user_id = ? AND site_id = ?
    ORDER BY vote_timestamp DESC LIMIT 1
");
if ($stmt && $stmt->bind_param("ii", $user_id, $internal_site_id) && $stmt->execute()) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $last_vote = $result->fetch_assoc();
        $last_vote_time = (int)$last_vote['vote_timestamp'];
        $cooldown_seconds = $cooldown_hours * 3600;
        if (time() - $last_vote_time < $cooldown_seconds) {
            $can_vote = false;
        }
    }
    $stmt->close();
}

if (!$can_vote) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "User is on cooldown for this site."]);
    exit;
}

// Log the vote
$reward_status = 0; // Pending
$now = time();
$site_db->begin_transaction();
try {
    $stmt = $site_db->prepare("
        INSERT INTO vote_log (site_id, user_id, ip_address, vote_timestamp, reward_status)
        VALUES (?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception("Failed to prepare vote_log insert.");
    }
    $stmt->bind_param("iisii", $internal_site_id, $user_id, $userip, $now, $reward_status);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute vote_log insert.");
    }
    $stmt->close();
    $site_db->commit();

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "success",
        "message" => "Vote logged successfully for $userid (ID: $user_id). Reward pending.",
        "points_pending" => $reward_points
    ]);
} catch (Exception $e) {
    $site_db->rollback();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Error logging vote."]);
}

// Close database connections
$site_db->close();
$auth_db->close();
$world_db->close();
$char_db->close();
exit;
?>
