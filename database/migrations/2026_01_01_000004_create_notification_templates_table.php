<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_key');                    // e.g. user.registered, order.placed
            $table->string('channel');                      // mail / database / sms / whatsapp / push
            $table->string('locale', 5)->default('en');
            $table->string('subject')->nullable();          // null for non-mail channels
            $table->text('body');
            $table->jsonb('placeholders')->nullable();      // list of supported placeholders
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_key', 'channel', 'locale']);
            $table->index(['event_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
