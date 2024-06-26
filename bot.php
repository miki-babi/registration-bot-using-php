<?php

include 'dbconfig.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$botToken = 'YOUR_BOT_TOKEN';
$apiUrl = "https://api.telegram.org/bot$botToken/";



// Get the incoming update from Telegram
$update = json_decode(file_get_contents("php://input"), TRUE);
file_put_contents('php://stderr', print_r($update, true));

if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $message = $update["message"]["text"];
    $contact = isset($update["message"]["contact"]) ? $update["message"]["contact"] : null;

    
    if ($contact) {
        $phoneNumber = $contact["phone_number"];
        
        // Check if phone  exists
        $stmt = $conn->prepare("SELECT chat_id FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $phoneNumber);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            //  if Phone number already exists
            sendMessage($chatId, "This phone number is already registered.");
        } else {
            // Register the phone number
            $stmt = $conn->prepare("UPDATE users SET phone_number = ? WHERE chat_id = ?");
            $stmt->bind_param("si", $phoneNumber, $chatId);
            $stmt->execute();
            sendMessage($chatId, "Thank you! Please provide your name.");
            updateUserState($chatId, 'awaiting_name');
        }

        $stmt->close();
    } elseif ($message == '/start') {
        $stmt = $conn->prepare("INSERT INTO users (chat_id) VALUES (?) ON DUPLICATE KEY UPDATE chat_id = chat_id");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $stmt->close();

        sendContactRequest($chatId);
        updateUserState($chatId, 'awaiting_contact');
    } else {
        $userState = getUserState($chatId);
        if ($userState == 'awaiting_name') {
            $stmt = $conn->prepare("UPDATE users SET name = ? WHERE chat_id = ?");
            $stmt->bind_param("si", $message, $chatId);
            $stmt->execute();
            $stmt->close();

            sendMessage($chatId, "Thank you! Please provide your university.");
            updateUserState($chatId, 'awaiting_university');
        } elseif ($userState == 'awaiting_university') {
            $stmt = $conn->prepare("UPDATE users SET university = ? WHERE chat_id = ?");
            $stmt->bind_param("si", $message, $chatId);
            $stmt->execute();
            $stmt->close();

            sendMessage($chatId, "Thank you for registering!");
            updateUserState($chatId, 'registered');
        } else {
            sendMessage($chatId, "Please start the registration process by typing /start");
        }
    }
} else {
    file_put_contents('php://stderr', "No message in update\n");
}

function sendMessage($chatId, $message) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode($message);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    if ($output === FALSE) {
        file_put_contents('php://stderr', "cURL error: " . curl_error($ch) . "\n");
    } else {
        file_put_contents('php://stderr', "Message sent: $message\n");
    }
    curl_close($ch);
}

function sendContactRequest($chatId) {
    global $apiUrl;
    $keyboard = [
        'keyboard' => [
            [['text' => 'Share Contact', 'request_contact' => true]]
        ],
        'one_time_keyboard' => true,
        'resize_keyboard' => true
    ];
    $replyMarkup = json_encode($keyboard);

    $url = $apiUrl . "sendMessage?chat_id=$chatId&text=" . urlencode("Please share your contact information.") . "&reply_markup=" . urlencode($replyMarkup);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $output = curl_exec($ch);
    if ($output === FALSE) {
        file_put_contents('php://stderr', "cURL error: " . curl_error($ch) . "\n");
    } else {
        file_put_contents('php://stderr', "Contact request sent\n");
    }
    curl_close($ch);
}

function updateUserState($chatId, $state) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO user_states (chat_id, state) VALUES (?, ?) ON DUPLICATE KEY UPDATE state = ?");
    $stmt->bind_param("iss", $chatId, $state, $state);
    $stmt->execute();
    $stmt->close();
}

function getUserState($chatId) {
    global $conn;
    $stmt = $conn->prepare("SELECT state FROM user_states WHERE chat_id = ?");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $stmt->bind_result($state);
    $stmt->fetch();
    $stmt->close();
    return $state;
}
?>
