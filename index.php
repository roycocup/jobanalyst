<?php
require_once __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('APPLICATION_NAME', 'Job Analyst');
define('CREDENTIALS_PATH', __DIR__ . '/credentials.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');
// If modifying these scopes, delete your previously saved credentials
// at ~/.credentials/gmail-php-quickstart.json
define('SCOPES', implode(' ', array(
	Google_Service_Gmail::GMAIL_READONLY)
));

if (php_sapi_name() != 'cli') {
	throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {

	$client = new Google_Client();
	$client->setApplicationName(APPLICATION_NAME);
	$client->setScopes(SCOPES);
	$client->setAuthConfig(CLIENT_SECRET_PATH);
	$client->setAccessType('offline');

  // Load previously authorized credentials from a file.
	$credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
	if (file_exists($credentialsPath)) {
		$accessToken = json_decode(file_get_contents($credentialsPath), true);
	} else {
    // Request authorization from the user.
		$authUrl = $client->createAuthUrl();
		printf("Open the following link in your browser:\n%s\n", $authUrl);
		print 'Enter verification code: ';
		$authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
		$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

    // Store the credentials to disk.
		if(!file_exists(dirname($credentialsPath))) {
			mkdir(dirname($credentialsPath), 0700, true);
		}
		file_put_contents($credentialsPath, json_encode($accessToken));
		printf("Credentials saved to %s\n", $credentialsPath);
	}
	$client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
	if ($client->isAccessTokenExpired()) {
		$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
		file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
	}
	return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
	$homeDirectory = getenv('HOME');
	if (empty($homeDirectory)) {
		$homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
	}
	return str_replace('~', realpath($homeDirectory), $path);
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Gmail($client);


// Print the labels in the user's account.
$user = 'me';
try{
    $results = $service->users_labels->listUsersLabels($user);
}catch(Exception $e){
    var_dump($e->getMessage());
}


init();
//$results = $service->users_messages->listUsersMessages($user, ['labelIds'=>'Label_31']);
$results = $service->users_messages->listUsersMessages($user, ['labelIds' => 'INBOX']);
// $nextPageToken = $service->users_messages->listUsersMessages($user)->getNextPageToken();
$messages = $results->getMessages();

$maxMess = 10;
$i = 0;
foreach ($messages as $message){

    printout('Processing message '. $i);


    if ($i >= $maxMess){
        break;
    }

    printout('Pulling headers...');
    $headers = $service->users_messages->get($user, $message->getId())->getPayload()->getHeaders();

    $msg = [];

    foreach ($headers as $header){
        if ('X-Received' == $header['name']){
            $val = $header['value'];
            $val = explode(';',$val);
            $msg[] = $val[count($val)-1];
        }
        if ($header['name'] == 'Subject'){
            $msg[] = $header['value'];
        }
    }

    write(implode(' ', $msg));
    $i++;
}



//var_dump($message); die;

function init(){
    ob_start();
    if(file_exists('log.txt'))
        unlink('log.txt');
}

function printout($msg){
    echo ($msg."\n\r");
    ob_flush();
}

function write($msg){
    $fh = fopen('log.txt', 'a+');
    fwrite($fh, print_r($msg."\n\r", true));
    fclose($fh);
}

function getLabels($results){

    if (count($results->getLabels()) == 0) {
        print "No labels found.\n";
    } else {
        print "Labels:\n";
        foreach ($results->getLabels() as $label) {
            printf("- %s\n", $label->getName());
        }
    }
}


