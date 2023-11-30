<?php
header("Access-Control-Allow-Origin: https://localhost:5001");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/error.log');

session_start();

require __DIR__ . './../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$aiApiKey = $_ENV['AI_API_KEY'];

// Retrieve the unique identifier from PHPSESSID
$phpSessionId = $_COOKIE['PHPSESSID'] ?? $_POST['PHPSESSID'] ?? 'default_session_id';

// Retrieve workout options and user-submitted subtitle from POST request
$intensityLevel = $_POST['intensity'] ?? 'medium';
$workoutType = $_POST['wk-type'] ?? 'bodyweight';
$userSub = $_POST['usersub'] ?? 'jokes about apologies due to the error';
$specs = $_POST['specs'] ?? '';

// Prepare the API request
$apiEndpoint = 'https://api.openai.com/v1/chat/completions';
$requestPayload = [
    'model' => 'gpt-3.5-turbo-16k',
    'messages' => [
        [ 'role' => 'system', 'content' => 'You are an expert at creating specific workouts. Your next task is to create a workout for someone who will be watching a film/TV show while they exercise. The exercise names and descriptions should further immerse the user in what they are watching. The workout should be at a ' . $intensityLevel . ' intensity and should focus on ' . $workoutType . ' exercises. With regards to the workout take into account these additional specifications [' . $specs . '], if the brackets are empty disreagard this sentence. Please generate exercises with funny and creative names and descriptions. All exercises should be designed to be performed without the need for equipment, unless specifically mentioned otherwise. Each exercise should be formatted in XML with the following tags: <Exercise><Name>Exercise Name</Name><Description>Exercise Description</Description><Sets>Number of Sets</Sets><Reps>Number of Reps</Reps><Rest>Rest Period</Rest><TimestampStart>Start Time</TimestampStart><TimestampEnd>End Time</TimestampEnd></Exercise>. The timestamps should correspond to the timing of the film/TV show and should provide more than ample time for both the exercise and rest periods (show each exercise for a minimum of 3 minutes). THE TIMESTAMPS SHALL BE SEQUENTIAL, MEANING, THE END TIMESTAMP OF ONE EXERCISE SHOULD BE THE START TIMESTAMP OF THE NEXT EXERCISE, AND THE FIRST TIMESTAMP SHOULD START AT 3 SECONDS.' // Delete sample below above for funny descriptions but worse display time range (need to troubleshoot that to have both) // (for example if you say 3 sets 10 jumping jacks rest period 10secs, understand that an average human will take ~10-12 seconds for 10 jumnping jacks, so 30 secs for all 30 and with an additional 10 seconds between each set means at the absolute minimum 1 minute should be given for that exercise combination)' 
        ],
        [
            'role' => 'user',
            'content' => $userSub
        ]
    ],
    'max_tokens' => 3096,
    'temperature' => 0.75,
    'n' => 1,
    'stop' => '\n',
    'frequency_penalty' => 0.1,
    'presence_penalty' => 0.2,
];

// Send API request
$ch = curl_init($apiEndpoint);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $aiApiKey, 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if ($response === false) {
    http_response_code(500);
    echo 'Error generating movie workout: ' . curl_error($ch);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo 'Error generating movie workout: ' . $response;
    exit;
}

$responseData = json_decode($response, true);

if ($responseData === null) {
    http_response_code(500);
    echo 'Error parsing API response: ' . json_last_error_msg();
    exit;
}

function formatTimestamp($timestamp) {
    $timeComponents = explode(':', $timestamp);

    // Ensure that the timestamp has three parts (hours, minutes, and seconds)
    if (count($timeComponents) < 3) {
        // Handle the error or skip this timestamp
        // For example, you can return a default value or log an error
        return 0; // Example default value
    }

    $hours = intval($timeComponents[0]);
    $minutes = intval($timeComponents[1]);
    $seconds = floatval($timeComponents[2]);

    $milliseconds = (($hours * 60 * 60) + ($minutes * 60) + $seconds) * 1000;

    return $milliseconds;
}

// Create the XML document
$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Exercises></Exercises>');

// Parse the response and add exercises to the XML document
foreach ($responseData['choices'] as $choice) {
    $message = $choice['message']['content'];
    preg_match_all('/<Name>(.*?)<\/Name>.*?<Description>(.*?)<\/Description>.*?<Sets>(.*?)<\/Sets>.*?<Reps>(.*?)<\/Reps>.*?<Rest>(.*?)<\/Rest>.*?<TimestampStart>(.*?)<\/TimestampStart>.*?<TimestampEnd>(.*?)<\/TimestampEnd>/s', $message, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
        foreach ($matches as $match) {
            $exercise = $xml->addChild('Exercise');
            $exercise->addChild('Name', $match[1]);
            $exercise->addChild('Description', $match[2]);
            $exercise->addChild('Sets', $match[3]);
            $exercise->addChild('Reps', $match[4]);
            $exercise->addChild('Rest', $match[5]);
            $exercise->addChild('TimestampStart', formatTimestamp($match[6]));
            $exercise->addChild('TimestampEnd', formatTimestamp($match[7]));
        }
    }
}

// Check the HTTP status code of the API response
if ($httpCode == 200) {
    // Define the XML file path using PHPSESSID
    $file = __DIR__ . '/workouts/' . $phpSessionId . '_workout.xml';

    // Attempt to save the XML file
    if ($xml->asXML($file) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error saving the XML file: ' . error_get_last()['message']]);
    } else {
        // Send a success response
        echo json_encode(['success' => true]);
    }
} else {
    // Handle non-200 responses from the API
    http_response_code($httpCode);
    echo json_encode(['error' => 'Error generating movie workout: HTTP status code ' . $httpCode]);
}

exit;
