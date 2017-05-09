<?php

namespace App\Http\Controllers;

use App\Models\MessagePart;
use Illuminate\Http\Request;
use Google_Service_Gmail;
use Google_Client;
use App\Models\Messages;

class MainController extends Controller
{

    protected $clientSecret;
    protected $credentials;
    protected $scopes;
    protected $logFile = '../Log.txt';

    public function __construct()
    {

        $this->clientSecret = '../' . env('CLIENT_SECRET_PATH');
        $this->credentials = '../' . env('CREDENTIALS_PATH');
        $this->scopes = implode(' ', [Google_Service_Gmail::GMAIL_READONLY]);
    }


    protected function init(){
        if(file_exists($this->logFile))
            unlink($this->logFile);
    }


    public function home(Request $request)
    {
        // Get the API client and construct the service object.
        $client = $this->getClient();
        $service = new Google_Service_Gmail($client);

        // Print the labels in the user's account.
        $user = 'me';
        try{
            $results = $service->users_labels->listUsersLabels($user);
        }catch(\Exception $e){
            var_dump($e->getMessage()); die;
        }


        $this->init();
        //$results = $service->users_messages->listUsersMessages($user, ['labelIds'=>'Label_31']);
        $results = $service->users_messages->listUsersMessages($user, ['labelIds' => 'INBOX', 'q' =>'Subject:developer']);
        // $nextPageToken = $service->users_messages->listUsersMessages($user)->getNextPageToken();
        $messages = $results->getMessages();

        foreach ($messages as $message){
            Messages::saveMessage($message);
        }

        $max = 10;
        $i = 0;
        foreach ($messages as $message){

            if ($i >= $max) break;

            $message = $service->users_messages->get($user, $message->getId());
            $payload = $message->getPayload();
            $headers = $payload->getHeaders();
            $parts = $payload->getParts();
            $body = $parts[0]['body'];
            $rawData = $body->data;
            $sanitizedData = strtr($rawData,'-_', '+/');
            $decodedMessage = base64_decode($sanitizedData);

            var_dump($body); die;

            // http://stackoverflow.com/questions/32655874/cannot-get-the-body-of-email-with-gmail-php-api
            $this->printout($payload->parts[0]->parts); die;

            $msg = [];

            foreach ($headers as $header){
                if ('X-Received' == $header['name']){
                    $val = $header['value'];
                    $val = explode(';',$val);
                    $msg['received'] = $val[count($val)-1];
                }
                if ($header['name'] == 'Subject'){
                    $msg['subject'] = $header['value'];
                }
            }

            $mp = new MessagePart();
            $mp->received = ($msg['received'])?:'';
            $mp->subject = ($msg['subject'])?:'';
            $mp->g_id = $message->getId();
            $mp->message = $decodedMessage;
            $mp->save();

            $i++;
        }
    }

    protected function getClient() {

        $client = new Google_Client();
        $client->setApplicationName(env('APPLICATION_NAME'));
        $client->setScopes($this->scopes);
        $client->setAuthConfig($this->clientSecret);
        $client->setAccessType('offline');

        // Load previously authorized credentials from a file.
        $credentialsPath = $this->expandHomeDirectory($this->credentials);

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

    protected function expandHomeDirectory($path) {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        return str_replace('~', realpath($homeDirectory), $path);
    }

    protected function printout($msg){
        echo "<pre>";
        print_r($msg); die;
    }

    protected function write($msg){
        $fh = fopen('log.txt', 'a+');
        if (is_string($msg)){
            fwrite($fh, $msg);
        } else {
            fwrite($fh, print_r($msg, true)."\n\r");
        }

        fclose($fh);
    }

    protected function getLabels($results){

        if (count($results->getLabels()) == 0) {
            print "No labels found.\n";
        } else {
            print "Labels:\n";
            foreach ($results->getLabels() as $label) {
                printf("- %s\n", $label->getName());
            }
        }
    }
}
