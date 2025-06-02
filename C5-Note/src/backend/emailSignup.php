<?php

// Function to generate a random code for email verification
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $randomString;
}

//ALTER TABLE tbl_name ADD id INT PRIMARY KEY AUTO_INCREMENT;

// Loads the config JSON to be able to connect to the database
$config = file_get_contents("../config.json");
$data = json_decode($config);
$username = $data->username;
$password = $data->password;
$db_name = $data->db_name;

// Get the input from the JSON request
$json = json_decode(file_get_contents("php://input"));

$signup_name = $json->username;
$signup_email = $json->email;


if (filter_var($signup_email, FILTER_VALIDATE_EMAIL) === false) {
    die(json_encode([
        "status" => "400",
        "message" => "Invalid email format"
    ]));
}

// Ensure no line breaks in the email
$signup_email = str_replace(["\r", "\n"], "", $signup_email);

//$signup_name = htmlspecialchars($signup_name, ENT_QUOTES, 'UTF-8');
//$signup_email = htmlspecialchars($signup_email, ENT_QUOTES, 'UTF-8');

// Connect to the database
$mysqli = new mysqli("localhost:3306", $username, $password, $db_name);

// If can't connect, kill the connection
if ($mysqli->connect_error) {
    die("Could not connect to the database: " . $mysqli->connect_error);
}

try {
    // Create table if it doesn't exist
    $mysqli->query("CREATE TABLE IF NOT EXISTS email_codes(email TEXT, username TEXT, code TEXT, time INT)");

    // Check if username exists
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception("Error preparing statement for username: " . $mysqli->error);
    }

    $stmt->bind_param("s", $signup_name);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if username already exists
    if ($result->num_rows > 0) {
        die(json_encode([
            "status" => "400",
            "message" => "Username already exists"
        ]));
    }
    $stmt->close();

    // Check if email exists
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Error preparing statement for email: " . $mysqli->error);
    }

    $stmt->bind_param("s", $signup_email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if email already exists
    if ($result->num_rows > 0) {
        die(json_encode([
            "status" => "400",
            "message" => "Email already exists"
        ]));
    }
    $stmt->close();


    // Check if username exists
    $stmt = $mysqli->prepare("SELECT * FROM email_codes WHERE username = ?");
    if (!$stmt) {
        throw new Exception("Error preparing statement for username: " . $mysqli->error);
    }

    $stmt->bind_param("s", $signup_name);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if username already exists
    if ($result->num_rows > 0) {
        if (time() - $result->fetch_assoc()['time'] < 300){
        die(json_encode([
            "status" => "400",
            "message" => "Username already pending verification"
        ]));
    }else{
        $stmt->close();
        $stmt1 = $mysqli->prepare("DELETE FROM email_codes WHERE username = ?");
    if (!$stmt1) {
        throw new Exception("Error preparing statement for username: " . $mysqli->error);
    }

    $stmt1->bind_param("s", $signup_name);
    $stmt1->execute();
    $stmt1->close();
    }
}

    // Check if email exists
    $stmt = $mysqli->prepare("SELECT * FROM email_codes WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Error preparing statement for email: " . $mysqli->error);
    }

    $stmt->bind_param("s", $signup_email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if email already exists
    if ($result->num_rows > 0) {
        if (time() - $result->fetch_assoc()['time'] < 300){
        die(json_encode([
            "status" => "400",
            "message" => "Email already pending verification"
        ]));
    }else{
        $stmt->close();
        $stmt1 = $mysqli->prepare("SELECT * FROM email_codes WHERE email = ?");
    if (!$stmt1) {
        throw new Exception("Error preparing statement for email: " . $mysqli->error);
    }

    $stmt1->bind_param("s", $signup_email);
    $stmt1->execute();
    $stmt1->close();
    }
}


    // Generate a random code
    $generated_code = generateRandomString(10);

    // Insert the email code into the database
    $stmt = $mysqli->prepare("INSERT INTO email_codes(email, username, code, time) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Error preparing statement for email codes: " . $mysqli->error);
    }

    $current_time = time();
    $stmt->bind_param("sssi", $signup_email, $signup_name, $generated_code, $current_time);
    $stmt->execute();
    $stmt->close();

    // Send the verification email
    if (!mail($signup_email, "C5-Note Account Creation", "The verification code to create your account is " . $generated_code . ".\n It expires in 5 minutes.")) {
        throw new Exception("Error sending email.");
    }

    // Success response
    http_response_code(200);
    die(json_encode([
        "status" => "200",
        "message" => "An email has been sent containing a verification code."
    ]));
} catch (Exception $e) {
    // Log the error message for debugging
    error_log($e->getMessage());

    // Return error response
    http_response_code(500);
    die(json_encode([
        "status" => "500",
        "message" => "Internal server error"
    ]));
}
?>
