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
            $table->tinyInteger('level', false, true);
            $table->date('opening_date')->nullable();
            $table->date('review_date')->nullable();
            $table->boolean('isClosed')->default(false);
            $table->foreignId('nc_id')->constrained();
            $table->foreignId('section_id')->constrained();
            $table->boolean('is_validated')->default(0);
            $table->unsignedBigInteger("validator_id")->nullable();
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
        Schema::dropIfExists('non_conformites');
    }
};
