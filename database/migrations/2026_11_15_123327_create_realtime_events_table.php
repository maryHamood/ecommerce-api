<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realtime_events', function (Blueprint $table) {
            $table->id();

            $table->uuid('event_id')->unique();

            $table->string('event_type', 100);

            $table->unsignedBigInteger('order_id');

            $table->unsignedBigInteger('user_id')->nullable();

            $table->json('payload');

            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index(['event_type', 'id']);
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realtime_events');
    }
};
