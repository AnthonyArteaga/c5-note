<?php

$config = file_get_contents("../config.json");
$data = json_decode($config);

$username = $data->username;
$password = $data->password;
$db_name = $data->db_name;

if (isset($_COOKIE["token"])) {
  $token = $_COOKIE["token"];
}
else {
  http_response_code(400);
  die(json_encode([
    "status" => "failed",
    "message" => "You aren't signed in."
  ]));
}

$connection = new mysqli("localhost:3306", $username, $password, $db_name);

if($connection->connect_error) {
    http_response_code(500);
    die(json_encode([
      "status" => "failed",
      "message" => "Could not connect to the database"
    ]));
}

$statement = $connection->prepare("SELECT * FROM active_users WHERE token = ?");

$hashedToken = hash("sha256", $token);
$statement->bind_param("s", $hashedToken);

$statement->execute();
$result = $statement->get_result();

if($result->num_rows == 1) {
  $id = $result->fetch_assoc()["user_id"];
}
else {
  http_response_code(401);
  die(json_encode([
    "status" => "failed",
    "message" => "Invalid token."
  ]));
  
}

$target_dir = "uploads/";

$target_dir = "uploads/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
$target_file = $target_dir . $id . "." . $imageFileType;

// Check if image file is a actual image or fake image
$check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
  if($check == false) {
    http_response_code(400);
    die(json_encode([
      "status" => "failed",
      "message" => "Only JPG, JPEG, PNG, GIF allowed."
    ]));
  }


// Check file size less than 2MB
if ($_FILES["fileToUpload"]["size"] > 2000000) {
  http_response_code(413);
  die(json_encode([
    "status" => "failed",
    "message" => "File is too large."
  ]));
}

// Check file size less than 2MB
if ($_FILES["fileToUpload"]["size"] == 0) {
  http_response_code(400);
  die(json_encode([
    "status" => "failed",
    "message" => "File is empty."
  ]));
}

// Allow certain file formats
if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
&& $imageFileType != "gif" ) {
  http_response_code(400);
  die(json_encode([
    "status" => "failed",
    "message" => "Only JPG, JPEG, PNG, GIF allowed."
  ]));
}

$files = glob("uploads/$id.*");

foreach ($files as $file) {
    unlink(realpath($file));
}


  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
    http_response_code(200);
    die(json_encode([
      "status" => "success",
      "message" => "File successfully uploaded."
    ]));
  } else {
    http_response_code(500);
    die(json_encode([
      "status" => "failed",
      "message" => "There was an error uploading your file."
    ]));
  }
