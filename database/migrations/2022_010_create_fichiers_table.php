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
        Schema::create('fichiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('extension');
            $table->string('path');
            $table->bigInteger('size');
            $table->integer('parent_id');
            $table->string('parent_type');
            $table->foreignId('section_id')->constrained();
            $table->boolean('is_validated');
            $table->foreign('validator_id')
                ->references('id')->on('users')
                ->onUpdate('restrict')
                ->onDelete('restrict');
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
        Schema::dropIfExists('fichiers');
    }
};
