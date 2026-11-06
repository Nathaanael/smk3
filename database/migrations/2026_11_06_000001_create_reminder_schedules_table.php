<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The automatic reminder: when it goes out and with which message. A new schedule stays off
 * until someone turns it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_schedules', function (Blueprint $table) {
            $table->id();
            // daily, weekly or monthly
            $table->string('frequency', 10);
            // ISO day of the week (1 = Monday), for weekly
            $table->unsignedTinyInteger('weekday')->nullable();
            // 1-28, for monthly
            $table->unsignedTinyInteger('monthday')->nullable();
            // "HH:MM" in the timezone of ReminderEmail::TIMEZONE
            $table->string('time', 5);
            // null = every plant
            $table->string('pabrik', 10)->nullable();
            $table->string('subject');
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('reminder_emails', function (Blueprint $table) {
            $table->boolean('scheduled')->default(false)->after('sender_id');
            // the mailer used; "log" means it was only written to the application log
            $table->string('mailer', 20)->nullable()->after('scheduled');
        });
    }

    public function down(): void
    {
        Schema::table('reminder_emails', function (Blueprint $table) {
            $table->dropColumn(['scheduled', 'mailer']);
        });

        Schema::dropIfExists('reminder_schedules');
    }
};
