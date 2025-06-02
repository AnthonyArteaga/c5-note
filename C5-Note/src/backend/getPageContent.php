<?php

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$config = file_get_contents("../config.json");
$data = json_decode($config);
$username = $data->username;
$password = $data->password;
$db_name = $data->db_name;

$connection = new mysqli("localhost", $username, $password, $db_name, 3306); // Fixed host and port

// Test the connection
if ($connection->connect_error) {
    die(json_encode(["error" => "Could not connect to the database: " . $connection->connect_error]));
}

$json = json_decode(file_get_contents('php://input')); // Ensure you read input correctly

$current_username = 'Guest';

if (isset($json->guest) && $json->guest) {
    $current_username = 'Guest';
}else{

    // Check for the "token" cookie
    if (isset($_COOKIE['token'])) {
        $token = hash("sha256", $_COOKIE['token']);
    } else {
        die(json_encode(["error" => "Token cookie is not set.", "token" => null])); // Added null value for token
    }

    // Prepare statement to get user_id based on the token
    $smto = $connection->prepare("SELECT user_id FROM active_users WHERE token = ?");
    $smto->bind_param("s", $token);
    $smto->execute();
    $resulto = $smto->get_result();

    if ($resulto->num_rows === 0) {
        die(json_encode(["error" => "Invalid token.", "token" => $token])); // Added token to error response
    }

    $user = $resulto->fetch_assoc();
    $user_id = $user['user_id'];
    $smto->close();

    // Prepare statement to get username based on user_id
    $smto = $connection->prepare("SELECT username FROM users WHERE user_id = ?");
    $smto->bind_param("i", $user_id);
    $smto->execute();
    $username_result = $smto->get_result();

    if ($username_result->num_rows === 0) {
        die(json_encode(["error" => "User not found."]));
    }

    $current_username = $username_result->fetch_assoc()['username'];
    $smto->close();

}

require_once './htmlpurifier/htmlpurifier/library/HTMLPurifier.auto.php';
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.SafeIframe', true);
$config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%'); //allow YouTube and Vimeo
$purifier = new HTMLPurifier($config);


$loadpageid = $json->pageid;
$groupid = $json->groupid;
$isInitialFetch = $json->isInitialFetch ?? false; // Default to false if not set

header("Content-Type: application/json; charset=UTF-8");

// Prepare the SQL statement to get the page content and last user
$sql = $connection->prepare("SELECT page_content, last_user, last_mod, page_name FROM pages WHERE page_number = ? AND group_id = ?");
$sql->bind_param("ii", $loadpageid, $groupid); // Fixed parameter binding
$sql->execute();
$result = $sql->get_result();

$connected_users = [];

if ($result->num_rows > 0) {
    $outp = $result->fetch_assoc(); // Fetch the result correctly
    $lastUser = $outp['last_user'];
    $lastmod = $outp['last_mod'];
    $pageName = $outp['page_name'];
    $clean_html = $purifier->purify($outp['page_content']);
    
    // Fetch the current connected users for the specific page and group
    $user_sql = $connection->prepare("SELECT username, last_activity FROM connected_users WHERE pageid = ? AND groupid = ?");
    $user_sql->bind_param("ii", $loadpageid, $groupid);
    $user_sql->execute();
    $user_result = $user_sql->get_result();

    while ($row = $user_result->fetch_assoc()) {
        $username = $row['username'];
        $last_activity = strtotime($row['last_activity']);
        
        // Remove users that have been inactive for more than 5 seconds
        if (time() - $last_activity > 5) {
            // Remove from the connected_users table
            $delete_sql = $connection->prepare("DELETE FROM connected_users WHERE username = ? AND pageid = ? AND groupid = ?");
            $delete_sql->bind_param("sii", $username, $loadpageid, $groupid);
            $delete_sql->execute();
            $delete_sql->close();
        } else {
            // Add to the connected users array
            $connected_users[$purifier->purify($username)] = $row['last_activity'];
        }
    }
    $user_sql->close();

    // Update the user's last activity time to now (this refreshes the user's session)
    $update_sql = $connection->prepare("INSERT INTO connected_users (username, pageid, groupid, last_activity) 
                                        VALUES (?, ?, ?, NOW()) 
                                        ON DUPLICATE KEY UPDATE last_activity = NOW()");
    $update_sql->bind_param("sii", $current_username, $loadpageid, $groupid);
    $update_sql->execute();
    $update_sql->close();
    
    // Always return content if it's available
    echo json_encode([
        "content" => $clean_html,
        'last_user' => $lastUser,
        'last_mod' => $lastmod,
        'page_name' => $pageName,
        'connected_users' => $connected_users // Send the list of connected users
    ]);
    exit;
} else {
    // No content found
    echo json_encode(["error" => "No content found for this page."]);
}

$sql->close(); // Close the statement
$connection->close(); // Close the connection
?>
