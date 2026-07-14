<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('author_role', 20);   // customer | admin | vendor
            $table->boolean('is_internal')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->index('support_ticket_id', 'tmsg_ticket_idx');
            $table->index(['support_ticket_id', 'created_at'], 'tmsg_ticket_created_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('support_ticket_messages'); }
};
