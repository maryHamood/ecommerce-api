<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_events', function (Blueprint $table) {
            $table->id();

            $table->string('consumer_name', 100);
            $table->uuid('event_id');

            $table->timestamp('processed_at');

            $table->timestamps();

            $table->unique(
                ['consumer_name', 'event_id'],
                'processed_events_consumer_event_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
    }
};
