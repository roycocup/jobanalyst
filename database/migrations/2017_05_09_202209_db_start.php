<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DbStart extends Migration
{


    public function up()
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('g_id');
            $table->timestamps();
        });
    }


    public function down()
    {
        Schema::drop('messages');
    }
}
