<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$url = "http://localhost:8000";

// Initialize cURL session
$ch = curl_init($url);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);  // Include headers in the response
curl_setopt($ch, CURLOPT_NOBODY, false); // We want body too
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // Follow redirects

$response = curl_exec($ch);



// Get HTTP status code
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Get header size and extract body if needed
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $header_size);

curl_close($ch);

// Return response based on status code
if ($http_code === 208) {
    http_response_code(200);
    echo "<html><body>Ok</body></html>";
    
} else {
    http_response_code(404);
    
    
}

