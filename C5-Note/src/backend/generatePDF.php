<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['htmlContent']) && isset($_POST['filename'])) {
    $htmlContent = $_POST['htmlContent'];
    $filename = $_POST['filename'];

    try {
        // Configure Dompdf options
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);  // Allow loading images from URLs

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($htmlContent);

        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to the browser
        header('Content-Type: application/pdf');
        header("Content-Disposition: attachment; filename={$filename}.pdf");
        echo $dompdf->output();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate PDF']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}