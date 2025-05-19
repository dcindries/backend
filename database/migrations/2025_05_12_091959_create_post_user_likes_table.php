<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostUserLikesTable extends Migration
{
    public function up()
    {
        Schema::create('post_user_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_id','user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('post_user_likes');
    }
}
