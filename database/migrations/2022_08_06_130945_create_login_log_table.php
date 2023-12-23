<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoginLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('login_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('access_token')->nullable();
            $table->text('external_auth')->nullable();
            $table->text('external_auth_provider')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_platform', 64)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('country_code', 16)->nullable();
            $table->string('region_code', 16)->nullable();
            $table->string('are_code', 16)->nullable();
            $table->string('zip_code', 16)->nullable();
            $table->string('timezone', 16)->nullable();
            $table->timestamp('date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('login_log');
    }
}
