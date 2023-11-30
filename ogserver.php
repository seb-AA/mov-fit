<?php

session_start();

$errorMessagePhrases = [
    "Houston, we have a problem. Let's give it another shot!",
    "This isn't the droid you're looking for. Try again, you must.",
    "Great Scott! The flux capacitor failed. Let's hit 88mph one more time.",
    "I've got a bad feeling about this. Let's reroute the Millennium Falcon and try again.",
    "In the Game of Thrones, you win or you retry. Let's play again.",
    "May the force not be with us this time. Ready to jump to lightspeed again?",
    "Looks like we're not in Kansas anymore. Click those ruby slippers once more.",
    "Oh, my precious! It seems we lost our way. Let's try that again.",
    "Winter is coming, and so is our next attempt. Brace yourselves and try again.",
    "I'm sorry, Dave, I'm afraid that didn't work. Would you like to try again?",
    "We're on a mission from God, but we took a wrong turn. Hit it again!",
    "Yabba Dabba Doo-over! Let's roll back to Bedrock and try once more."
];

// Retrieve the nummy_cookie value
$nummy_cookie = $_COOKIE['nummy_cookie'] ?? 'default_filename';

// Construct the file path
$subtitleFilePath = __DIR__ . '/subtitles/' . $nummy_cookie . '.txt';

if (file_exists($subtitleFilePath)) {
    // Read the subtitle file contents
    $subtitleContent = file_get_contents($subtitleFilePath);
}   else {
        $randomErrorMessage = $errorMessagePhrases[array_rand($errorMessagePhrases)];
        echo $randomErrorMessage;
    exit;
}

    // Optionally, delete the file after reading if it's no longer needed
//    unlink($subtitleFilePath);
//} else {
//    $randomErrorMessage = $errorMessagePhrases[array_rand($errorMessagePhrases)];
//    echo $randomErrorMessage;
//    exit;
//}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$aiApiKey = $_ENV['AI_API_KEY'];


if (isset($_SESSION['subtitleFilePath'])) {
    $subtitleFilePath = $_SESSION['subtitleFilePath'];

    if (file_exists($subtitleFilePath)) {
        $subtitleContent = file_get_contents($subtitleFilePath);
        unlink($subtitleFilePath);
    } else {
        echo "Subtitle file not found.";
        exit;
    }
} else {
    echo "Subtitle file path not set.";
    exit;
}

$apiEndpoint = 'https://api.openai.com/v1/chat/completions';

$intensityLevel = $_POST['intensity-level'] ?? '';
$workoutType = $_POST['workout-type'] ?? '';

$requestPayload = [
    'model' => 'gpt-3.5-turbo-16k-0613',
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are an expert at creating specific workouts. Your next task is to create a workout for someone who will be watching a film/TV show while they exercise. The workout should be at a decent intensity and should focus on bodyweight exercises. Please generate exercises with funny and creative names and descriptions. All exercises should be designed to be performed without the need for equipment, unless specifically mentioned otherwise. Each exercise should be formatted in XML with the following tags: <Exercise><Name>Exercise Name</Name><Description>Exercise Description</Description><Sets>Number of Sets</Sets><Reps>Number of Reps</Reps><Rest>Rest Period</Rest><TimestampStart>Start Time</TimestampStart><TimestampEnd>End Time</TimestampEnd></Exercise>. The timestamps should correspond to the timing of the film/TV show and should provide ample time for both the exercise and rest periods (for example if you say 3 sets 10 jumping jacks rest period 10secs, understand that an average human will take ~10-12 seconds for 10 jumnping jacks, so 30 secs for all 30 and with an additional 10 seconds between each set means at the absolute minimum 1 minute should be given for that exercise combination). The timestamps shall be sequential, meaning, the end timestamp of one exercise should be the start timestamp of the next exercise, and the first timestamp should start at 3 seconds. the inputted subtitle: {$formattedSubtitle}'
                        // Delete sample below above for funny descriptions but worse display time range (need to troubleshoot that to have both)
                        // (for example if you say 3 sets 10 jumping jacks rest period 10secs, understand that an average human will take ~10-12 seconds for 10 jumnping jacks, so 30 secs for all 30 and with an additional 10 seconds between each set means at the absolute minimum 1 minute should be given for that exercise combination)'
        ],
        [
            'role' => 'user',
            'content' => $subtitleContent
        ]
    ],
    'max_tokens' => 128000,
    'temperature' => 0.75,
    'n' => 1,
    'stop' => '\n',
    'frequency_penalty' => 0.1,
    'presence_penalty' => 0.2,
];

$headers = [
    'Authorization: Bearer ' . $aiApiKey,
    'Content-Type: application/json',
];



$ch = curl_init($apiEndpoint);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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

// Debug: Print the XML data
//echo 'XML Data:<br>';
//echo $xml->asXML();
//echo '<br><br>';

$file = __DIR__ . '/closedcaptions/' . $session_id . '_workout.xml';

if ($xml->asXML($file) === false) {
    http_response_code(500);
    echo 'Error saving the XML file: ' . error_get_last()['message'];
    exit;
}

header("Location: workout.php?xmlFile=" . urlencode($file));
exit;

?>