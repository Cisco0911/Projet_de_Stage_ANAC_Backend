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
        Schema::create('dossier_preuves', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Dossier Preuve');
            $table->foreignId('audit_id')->constrained();
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
        Schema::dropIfExists('dossier_preuves');
    }
};
