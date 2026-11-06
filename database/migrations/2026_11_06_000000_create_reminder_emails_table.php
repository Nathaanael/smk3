<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every attempt to send a reminder email. The recipient's name and address are copied, so the
 * history still reads correctly after their account changes or is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_email');
            // the address it really went to while trying out a mail server (MAIL_ALWAYS_TO)
            $table->string('redirected_to')->nullable();
            $table->string('subject');
            $table->unsignedInteger('findings_count');
            // sent or failed
            $table->string('status', 10);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_emails');
    }
};
