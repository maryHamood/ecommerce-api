<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();

            $table->uuid('event_id')->unique();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 100);
            $table->unsignedBigInteger('aggregate_id');

            $table->json('payload');

            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);

            $table->timestamp('available_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
