<?php

use Dotenv\Dotenv;

if ($_SERVER['HTTP_HOST'] == getenv("DOMAIN_HOST") || $_SERVER['SERVER_NAME'] == getenv("DOMAIN_HOST")) {
    // I use Heroku for hosting wich doesnt require an environment vars library
} else {
    require_once(__DIR__ . '../../vendor/autoload.php');
      
    $dotenv  = Dotenv::createImmutable(__DIR__ . '\../');
    $dotenv->load();
}

// Define the file to store the date
define(TIMEFILE, 'date.txt');

// Get the signature from github
@$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'];

// Generate your own signature using the secret you put while making the webhook
$secret = getenv("GITHUB_WEBHOOK_SECRET");
$post_data = file_get_contents('php://input');
$realSignature = 'sha1=' . hash_hmac('sha1', $post_data, $secret);

echo "Initiated<br>";

// Verify the signature matches
if ($signature == $realSignature) {

    echo "Verified<br>";

    // Other data gihub provides
    $gitHubEvent = $_SERVER['HTTP_X_GITHUB_EVENT'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $githubDelivery = $_SERVER['HTTP_X_GITHUB_DELIVERY'];

    $payload = $_POST['payload'];
    $json = json_decode($payload);

    $repo = $json->repository->name;

    if ($gitHubEvent == "push") {

        $name = $json->pusher->name;
        $commit = $json->head_commit->message;

        $time = time();
        file_put_contents(TIMEFILE, $time);

        $username = getenv("YOUR_APP_NAME") . ' Bot ' . $time;

        $output = "***--------------------------------------------------------------------------------------------------------------
        \n$name* has pushed to `$repo` with commit \"`$commit`\"!\n\n**";

    } elseif ($gitHubEvent == "deployment") {
        
        $time = file_get_contents(TIMEFILE);
        $username = getenv("YOUR_APP_NAME") . ' Bot ' . $time;
        

        $output = "
            **A deployment has been made!** ``` X_GitHub_Event ---> $gitHubEvent
            \n User_Agent ---> $userAgent
            \n X_GitHub_Delivery ---> $githubDelivery
            \n X_Hub_Signature ---> $signature```
            $repo
        ";

    } elseif ($gitHubEvent == "deployment_status") {

        $time = file_get_contents(TIMEFILE);
        $username = getenv("YOUR_APP_NAME") . ' Bot ' . $time;

        if ($json->deployment_status->state == "success") {
            $output = "**\n\nDeployment succeeded! $repo**";
        } else {
            $output = "**\n\nDeployment failed!**";
        }

    } else {
        $output = "**Something else happened!**";
    }


    // Send the message to discord
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, getenv("DISCORD_DEPLOYMENT_URL"));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'content-type'   => "application/json",
        'content' => $output,
        'username' => $username,
        'avatar_url' => getenv("AVATAR_URL"),
    ]));

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    echo $data = curl_exec($ch);
    curl_close($ch);

} else {
    http_response_code(403);
    die("<h1>Forbidden</h1>\n");
}