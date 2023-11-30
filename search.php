<?php
header("Access-Control-Allow-Origin: https://localhost:5001");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit; // No further action needed for preflight request
}

session_start();
$sessionId = session_id();

// Error reporting setup
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load environment variables
require_once __DIR__ . './../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Read POST parameters
$mediaType = $_POST['mediaType'] ?? 'defaultMediaType';
$searchTerm = $_POST['searchTerm'] ?? 'defaultSearchTerm';
$tmdbApiKey = $_ENV['TMDB_API_KEY'] ?? 'defaultApiKey';

// Prepare API request URL
$searchTerm = urlencode($searchTerm);
$url = "https://api.themoviedb.org/3/search/";
$url .= ($mediaType === "movie") ? "movie" : "tv";
$url .= "?api_key={$tmdbApiKey}&query={$searchTerm}";

// Initialize and set cURL options
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => ["cache-control: no-cache"],
]);

// Execute cURL request
$response = curl_exec($curl);
$err = curl_error($curl);
$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

// Handle cURL errors
if ($err) {
    error_log("cURL Error: " . $err);
    echo "cURL Error: " . $err;
    exit;
}

// Decode the response
$data = json_decode($response, true);
if (!$data) {
    error_log("Failed to decode JSON. Raw response: " . $response);
    echo "Error processing response";
    exit;
}

// Handle case of no results
if (!isset($data['results']) || empty($data['results'])) {
    error_log("No results found. Data: " . json_encode($data));
    echo json_encode(["error" => "No results found."]);
    exit;
}

// Combine PHPSESSID and response data
$combinedResponse = [
    'PHPSESSID' => $sessionId,
    'data' => $data
];

// Return combined data as JSON
header('Content-Type: application/json');
echo json_encode($combinedResponse);
?>
