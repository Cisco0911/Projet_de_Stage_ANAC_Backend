<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('operation_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operable_id');
            $table->string('operable_type');
            $table->string('operation_type');
            $table->unsignedBigInteger('validator_id');
            $table->foreign('validator_id')->references('id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('operation_notifications');
    }
};
