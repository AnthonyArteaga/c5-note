<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../../vendor/autoload.php';

$config = file_get_contents("../config.json");
$data = json_decode($config);
$username = $data->username;
$password = $data->password;
$db_name = $data->db_name;
$smtp_email = $data->smtp_email;
$smtp_pass = $data->smtp_password;

$json = json_decode(file_get_contents("php://input"));
$request_email = $json->email;

$connection = new mysqli("localhost:3306", $username, $password, $db_name);
$statement = $connection->prepare("SELECT * FROM users WHERE email = ?");
$statement->bind_param("s", $request_email);
$result = $statement->execute();

if ($result) {
    $output = $statement->get_result();
}

if ($result && $output->num_rows == 1) {
    $record = $output->fetch_assoc();
    $request_username = $record["username"];

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_email;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($smtp_email, 'C5-Note');
        $mail->addAddress($request_email);

        $mail->isHTML(false);
        $mail->Subject = 'C5 Note Username Reminder';
        $mail->Body = "The username associated with this email is " . $request_username . ".";

        $mail->send();

        http_response_code(200);
        die(json_encode([
            "status" => "success",
            "message" => "An email has been sent containing the username associated with " . $request_email . "."
        ]));
    } catch (Exception $e) {
        http_response_code(500);
        die(json_encode([
            "status" => "failed",
            "message" => "Mailer Error: " . $mail->ErrorInfo
        ]));
    }
} else {
    http_response_code(400);
    die(json_encode([
        "status" => "failed",
        "message" => "There is no account associated with " . $request_email . "."
    ]));
}