<?php
header("Access-Control-Allow-Origin: https://localhost:5001");

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load dependencies and environment variables
require __DIR__ . './../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Retrieve API keys
$osApiKey = $_ENV['OS_API_KEY'];

// Retrieve posted data
$mediaType = $_POST['mediaType'] ?? '';  // Add mediaType to your POST data
$selectedTMDBId = $_POST['selectedTMDBId'] ?? '';
$seasonNumber = $_POST['seasonNumber'] ?? '';
$episodeNumber = $_POST['episodeNumber'] ?? '';
$phpSessionId = $_POST['PHPSESSID'] ?? "default";

// Initialize the cURL session
$curl = curl_init();

// Build the API request URL based on media type
if ($mediaType === 'movie') {
    $apiUrl = "https://api.opensubtitles.com/api/v1/subtitles?tmdb_id={$selectedTMDBId}";
} else {
    // Assume TV show if not movie
    $apiUrl = "https://api.opensubtitles.com/api/v1/subtitles?parent_tmdb_id={$selectedTMDBId}&season_number={$seasonNumber}&episode_number={$episodeNumber}";
}

// Use the variables in the query string
curl_setopt_array($curl, array(
  CURLOPT_URL => $apiUrl,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    'Api-Key: ' . $osApiKey,
    'User-Agent: movfit'
  ),
));

// Execute the cURL session
$response = curl_exec($curl);
$httpStatusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (curl_errno($curl)) {
    $errorMessage = curl_error($curl);
    curl_close($curl);
    die("cURL Error: {$errorMessage}");
}

if ($httpStatusCode != 200) {
    curl_close($curl);
    die("HTTP Request Failed with Status Code {$httpStatusCode}");
}
curl_close($curl);

// Decode the response
$responseObj = json_decode($response, true);

// Check if response is not empty and has the expected structure
if (empty($responseObj) || !isset($responseObj['data'][0]['attributes']['files'][0]['file_id'])) {
    die("Error: Invalid response structure or File ID not found from API.");
}

// Extract the file ID
$file_id = $responseObj['data'][0]['attributes']['files'][0]['file_id'];

// Log the file ID for debugging
file_put_contents('debug.log', "File ID: {$file_id}\n", FILE_APPEND);

// Set up the download request
$downloadUrl = "https://api.opensubtitles.com/api/v1/download";

// Initialize cURL for download
$downloadCurl = curl_init($downloadUrl);
curl_setopt_array($downloadCurl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode(['file_id' => $file_id]), // Use the actual file ID
    CURLOPT_HTTPHEADER => [
        "Accept: application/json",
        "Api-Key: {$osApiKey}", // Use your API key here instead of Bearer token, if that's what OpenSubtitles requires
        "Content-Type: application/json",
        "User-Agent: movfit" // Make sure to use the User-Agent that you used in the first request
    ],
]);

$response = curl_exec($downloadCurl);
$err = curl_error($downloadCurl);

if ($err) {
    curl_close($downloadCurl);
    die("cURL Error #:" . $err);
}

// Check for HTTP errors
$httpStatusCode = curl_getinfo($downloadCurl, CURLINFO_HTTP_CODE);
if ($httpStatusCode != 200) {
    curl_close($downloadCurl);
    die("Download Request Failed with Status Code {$httpStatusCode}");
}

curl_close($downloadCurl);

// Handle the response for the subtitle file
$downloadedFile = json_decode($response, true);
if (!isset($downloadedFile['link'])) {
    die("Error: Download link not found in the response.");
}


$subtitleLink = $downloadedFile['link'];

// Download the subtitle file using the link
$subtitleFile = file_get_contents($subtitleLink);
if ($subtitleFile === false) {
    die("Failed to download the subtitle file.");
}

// Remove HTML-like tags
$cleanedSubtitle = preg_replace('/<[^>]+>/', '', $subtitleFile);

// Convert time format from hh:mm:ss,ms to hh:mm:ss
$cleanedSubtitle = preg_replace('/(\d{2}:\d{2}:\d{2}),\d{3}/', '$1', $cleanedSubtitle);

// Remove numerical prefixes before timestamps
$cleanedSubtitle = preg_replace('/^\d+\s*$/m', '', $cleanedSubtitle);

// Remove all line breaks
$cleanedSubtitle = preg_replace('/\n{3,}/', "\n\n", $cleanedSubtitle);
$cleanedSubtitle = preg_replace('/\n+/', '', $cleanedSubtitle);

// Adjust the timestamp format and remove leading zeros/unnecessary hour part
$cleanedSubtitle = preg_replace_callback('/(\d{2}):(\d{2}):(\d{2}) --> (\d{2}):(\d{2}):(\d{2})/', function ($matches) {
    // Remove leading zeros and hour part if it's '00'
    $start = ltrim($matches[1], '0') == '00' ? "{$matches[2]}:{$matches[3]}" : "{$matches[1]}:{$matches[2]}:{$matches[3]}";
    $end = ltrim($matches[4], '0') == '00' ? "{$matches[5]}:{$matches[6]}" : "{$matches[4]}:{$matches[5]}:{$matches[6]}";
    return "{$start}->{$end}";
}, $cleanedSubtitle);

// Store the cleaned and formatted subtitle in a variable
$formattedSubtitle = $cleanedSubtitle;



// Send headers to browser to initiate file download
//header('Content-Description: File Transfer');
//header('Content-Type: text/plain;charset=UTF-8');
//header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
//header('Expires: 0');
//header('Cache-Control: must-revalidate');
//header('Pragma: public');
//header('Content-Length: ' . strlen($cleanedSubtitle));

// Output file content for download
//echo $cleanedSubtitle;

// Directory for storing subtitles
$subtitleDir = __DIR__ . '/subtitles/';

// Ensure the directory exists
if (!file_exists($subtitleDir)) {
    mkdir($subtitleDir, 0777, true);
}

// Retrieve the session variable for the filename
$filename = "{$phpSessionId}.txt";
$subtitleFilePath = $subtitleDir . $filename;

// Save the subtitle file
file_put_contents($subtitleFilePath, $formattedSubtitle);

// Store the file path in the session for later use
$_SESSION['subtitleFilePath'] = $subtitleFilePath;

echo $cleanedSubtitle;

/*
ob_end_clean();
exit;
*/
?>