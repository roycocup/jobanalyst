<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Google_Service_Gmail_Message;

class Messages extends Model
{

    public static function saveMessage(Google_Service_Gmail_Message $message)
    {
        $dbMessage = Messages::where('g_id', $message->getId())->get();
        if ($dbMessage->count() < 1){
            $m = new Messages();
            $m->g_id = $message->getId();
            $m->save();
        }

        return $message;
    }

}
