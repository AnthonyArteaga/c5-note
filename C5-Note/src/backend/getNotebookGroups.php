<?php

$config = file_get_contents("../config.json");
$data = json_decode($config);

$username = $data->username;
$password = $data->password;
$db_name = $data->db_name;

$connection = new mysqli("localhost:3306", $username, $password, $db_name);

if ($connection->connect_error) {
    die("Could not connect to the database");
}

// Read input
$input = json_decode(file_get_contents("php://input"), true);

$guestTitle = $input['guest'] ?? false;
$loggedInUsername = "Guest";
if($guestTitle){
    $loggedInUsername = "Guest";
}else{
    if (isset($_COOKIE['token'])) {
    $token = hash("sha256", $_COOKIE['token']);


    // Prepare statement to get user_id based on the token
$smto = $connection->prepare("SELECT user_id FROM active_users WHERE token = ?");
$smto->bind_param("s", $token);
$smto->execute();
$resulto = $smto->get_result();

if ($resulto->num_rows === 0) {
    die(json_encode(["error" => "Invalid token."]));
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

$username_found = $username_result->fetch_assoc()['username'];
$smto->close();

$loggedInUsername = $username_found;


} else {
    die(json_encode(["error" => "Token cookie is not set.", "token" => null]));
}

}



$notebookTitle = $input['title'];
$isInitialFetch = $input['isInitialFetch'] ?? false;
$currentNotebookOrder = $input['currentNotebookOrder'] ?? [];

$maxAttempts = 30; // Maximum number of attempts before timeout
$attempts = 0;

do {
    try {
        // Get the notebook ID
        $stmt = $connection->prepare("SELECT id, username, isPrivate FROM notebooks WHERE title = ?");
        $stmt->bind_param("s", $notebookTitle);
        $stmt->execute();
        $result = $stmt->get_result();
        $notebook = $result->fetch_assoc();

        if (!$notebook) {
            echo json_encode([
                "success" => false,
                "message" => "Notebook not found"
            ]);
            exit;
        }

        $notebookId = $notebook['id'];
        $notebookOwner = $notebook['username'];

        // Check access
        if ($notebookOwner !== $loggedInUsername) {
            $stmtShared = $connection->prepare("SELECT COUNT(*) as count FROM shared_users WHERE notebook_id = ? AND username = ?");
            $stmtShared->bind_param("is", $notebookId, $loggedInUsername);
            $stmtShared->execute();
            $sharedResult = $stmtShared->get_result();
            $sharedCount = $sharedResult->fetch_assoc()['count'];

            if ($sharedCount == 0 && $notebook['isPrivate'] == 0) {
                echo json_encode([
                    "success" => false,
                    "message" => "You do not have access to this notebook.",
                    "logged" => $loggedInUsername
                ]);
                exit;
            }
        }

        // Fetch the groups for the notebook
        $stmtGroups = $connection->prepare("SELECT id, group_name, group_order FROM notebook_groups WHERE notebook_id = ? ORDER BY group_order ASC");
        $stmtGroups->bind_param("i", $notebookId);
        $stmtGroups->execute();
        $resultGroups = $stmtGroups->get_result();

        $groups = [];
        $notebookOrder = [];

        while ($group = $resultGroups->fetch_assoc()) {
            $groupId = $group['id'];

        // Fetch the pages for each group
        $stmtPages = $connection->prepare("SELECT page_number, page_content, page_name FROM pages WHERE group_id = ? ORDER BY page_order ASC");
        $stmtPages->bind_param("i", $groupId);
        $stmtPages->execute();
        $resultPages = $stmtPages->get_result();

            $pages = [];
            while ($page = $resultPages->fetch_assoc()) {
                $pages[] = $page;
            }

            // Include the group_id, group_name, and group_order in the response
            $groups[] = [
                'group_id' => $group['id'],
                'group_name' => $group['group_name'],
                'group_order' => $group['group_order'],
                'pages' => $pages
            ];

            // Build the notebook order array
            $notebookOrder[] = $group['group_order'];
        }

        // If it's the initial fetch, return the notebook order immediately
        if ($isInitialFetch) {
            echo json_encode([
                "success" => true,
                "notebook_id" => $notebookId,
                "isOwner" => $notebookOwner === $loggedInUsername,
                "groups" => $groups,
                "notebookOrder" => $notebookOrder
            ]);
            exit;
        }

        // Check if the current notebook order is different from the one found
        if (!arraysAreEqual($currentNotebookOrder, $notebookOrder)) {
            echo json_encode([
                "success" => true,
                "notebook_id" => $notebookId,
                "isOwner" => $notebookOwner === $loggedInUsername,
                "groups" => $groups,
                "notebookOrder" => $notebookOrder
            ]);
            exit;
        }

        // Wait before the next attempt
        sleep(1); // Sleep for a second
        $attempts++;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error: " . $e->getMessage()
        ]);
        exit;
    }

} while ($attempts < $maxAttempts);

// If no updates were found within the time limit
echo json_encode(["success" => false, "message" => "No updates found within the time limit."]);

$connection->close();

// Helper function to compare arrays
function arraysAreEqual($array1, $array2) {
    return (count($array1) === count($array2)) && !array_diff($array1, $array2);
}
?>
