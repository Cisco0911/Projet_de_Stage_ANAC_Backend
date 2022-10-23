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
        Schema::create('non_conformites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('isClosed');
            $table->foreignId('nc_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('section_id')->constrained();
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
        Schema::dropIfExists('non_conformites');
    }
};
