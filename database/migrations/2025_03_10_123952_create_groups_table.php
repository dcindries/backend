<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('access_key')->nullable(); // Para grupos privados
            $table->unsignedBigInteger('created_by'); // ID del usuario que crea el grupo
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('groups');
    }
};
