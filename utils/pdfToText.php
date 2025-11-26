<?php

function extractTextFromPDF($filePath) {
    $output = shell_exec("pdftotext " . escapeshellarg($filePath) . " -");
    return $output ?: "";
}

?>
