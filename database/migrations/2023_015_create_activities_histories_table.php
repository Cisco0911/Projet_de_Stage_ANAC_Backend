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
        Schema::dropIfExists('activities_histories');
        Schema::create('activities_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_inspector_number');
            $table->string("user_name", 255);
            $table->unsignedBigInteger('target_id');
            $table->string("target_type", 255);
            $table->string("target_name", 255);
            $table->string("operation");
            $table->json("services");
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
        Schema::dropIfExists('activities_histories');
    }
};
