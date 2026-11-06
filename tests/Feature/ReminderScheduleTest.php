<?php

use App\Enums\Role;
use App\Mail\FindingReminderMail;
use App\Models\NonConformity;
use App\Models\ReminderEmail;
use App\Models\ReminderSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** 9 November 2026 is a Monday. */
function atJakarta(string $time): Carbon
{
    return Carbon::parse($time, ReminderEmail::TIMEZONE);
}

function reminderSchedule(array $attributes = []): ReminderSchedule
{
    $schedule = new ReminderSchedule;
    $schedule->fill($attributes + [
        'frequency' => 'weekly',
        'weekday' => 1,
        'time' => '08:00',
        'subject' => 'Pengingat {nama}',
        'message' => 'Yth. {nama}, ada {jumlah_temuan} temuan.',
        'active' => true,
    ])->save();

    return $schedule;
}

function schedulePayload(array $overrides = []): array
{
    return $overrides + [
        'frequency' => 'weekly',
        'weekday' => 1,
        'monthday' => 1,
        'time' => '08:00',
        'pabrik' => '',
        'subject' => 'Pengingat',
        'message' => 'Isi pesan',
        'attachments' => [],
        'active' => true,
    ];
}

function picWithFinding(array $finding = []): User
{
    $pic = User::factory()->create(['otorisasi' => Role::Auditee]);
    NonConformity::factory()->open()->create($finding + ['pic' => $pic->email]);

    return $pic;
}

test('the command is on the Laravel schedule, checking every minute', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('reminders:send-scheduled')->assertSuccessful();
});

test('scheduled emails never reach the real mail server while the mailer is log', function () {
    $logFile = tempnam(sys_get_temp_dir(), 'mail-log');
    // MAIL_MAILER points at a mail server: reaching it would fail this send
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
        'mail.mailers.smtp.timeout' => 1,
        'mail.mailers.log.channel' => 'mail-check',
        'logging.channels.mail-check' => ['driver' => 'single', 'path' => $logFile],
    ]);
    expect(config('smk3.reminder_schedule_mailer'))->toBe('log');

    $pic = picWithFinding();
    reminderSchedule();
    $this->travelTo(atJakarta('2026-11-09 08:00'));

    $this->artisan('reminders:send-scheduled')->assertSuccessful();

    $log = ReminderEmail::sole();
    expect($log)
        ->status->toBe(ReminderEmail::SENT)
        ->mailer->toBe('log')
        ->scheduled->toBeTrue()
        ->sender_id->toBeNull()
        ->and(file_get_contents($logFile))->toContain($pic->email);

    unlink($logFile);

    $this->actingAs(User::factory()->admin()->create())->get('/email-reminders')
        ->assertSee(__('reminder.history.logOnly'))
        ->assertSee(__('reminder.history.scheduled'));
});

test('a weekly schedule goes out once on its day, from its time on', function () {
    Mail::fake();
    picWithFinding();
    reminderSchedule();

    $this->travelTo(atJakarta('2026-11-09 07:59'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertNothingSent();

    $this->travelTo(atJakarta('2026-11-09 08:00'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertSentCount(1);

    // the check runs every minute, but that day it is done
    $this->travelTo(atJakarta('2026-11-09 08:01'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertSentCount(1);

    $this->travelTo(atJakarta('2026-11-10 08:00'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertSentCount(1);

    $this->travelTo(atJakarta('2026-11-16 08:00'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertSentCount(2);
});

test('daily and monthly schedules follow their own days', function () {
    Mail::fake();
    picWithFinding();
    $schedule = reminderSchedule(['frequency' => 'daily', 'weekday' => null]);

    foreach (['2026-11-09 08:00', '2026-11-10 08:00'] as $moment) {
        $this->travelTo(atJakarta($moment));
        $this->artisan('reminders:send-scheduled');
    }
    Mail::assertSentCount(2);

    $schedule->update(['frequency' => 'monthly', 'monthday' => 15]);

    $this->travelTo(atJakarta('2026-11-14 08:00'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertSentCount(2);

    $this->travelTo(atJakarta('2026-11-15 08:00'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertSentCount(3);
});

test('a server that was off at the time still sends later that day, but not the next day', function () {
    Mail::fake();
    picWithFinding();
    reminderSchedule();

    $this->travelTo(atJakarta('2026-11-09 11:30'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertSentCount(1);

    Mail::fake();
    ReminderSchedule::query()->update(['last_run_at' => null]);
    $this->travelTo(atJakarta('2026-11-10 09:00'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertNothingSent();
});

test('a schedule that is off or was never saved sends nothing', function () {
    Mail::fake();
    picWithFinding();
    $this->travelTo(atJakarta('2026-11-09 08:00'));

    $this->artisan('reminders:send-scheduled')->assertSuccessful();

    reminderSchedule(['active' => false]);
    $this->artisan('reminders:send-scheduled')->assertSuccessful();

    Mail::assertNothingSent();
});

test('the schedule goes to who still needs it at that moment, within its plant', function () {
    Mail::fake();
    [$plant, $otherPlant] = array_keys(config('smk3.plants'));
    $own = picWithFinding(['pabrik' => $plant]);
    $other = picWithFinding(['pabrik' => $otherPlant]);
    $done = User::factory()->create(['otorisasi' => Role::Auditee]);
    NonConformity::factory()->open()->create(['pabrik' => $plant, 'pic' => $done->email])
        ->photos()->create(['field' => 'foto_perbaikan', 'path' => 'uploads/a.jpg']);

    reminderSchedule(['pabrik' => $plant]);
    $this->travelTo(atJakarta('2026-11-09 08:00'));
    $this->artisan('reminders:send-scheduled');

    Mail::assertSentCount(1);
    Mail::assertSent(FindingReminderMail::class, fn (FindingReminderMail $mail) => $mail->hasTo($own->email) && ! $mail->hasTo($other->email));
});

test('the schedule includes its attachments', function () {
    Mail::fake();
    picWithFinding();
    reminderSchedule(['attachments' => ['pdf']]);
    $this->travelTo(atJakarta('2026-11-09 08:00'));

    $this->artisan('reminders:send-scheduled');

    Mail::assertSent(FindingReminderMail::class, fn (FindingReminderMail $mail) => count($mail->attachments()) === 1);
});

test('the try-out option runs right away, even when it is off', function () {
    Mail::fake();
    picWithFinding();
    reminderSchedule(['active' => false]);
    $this->travelTo(atJakarta('2026-11-10 13:00'));

    $this->artisan('reminders:send-scheduled --now')->assertSuccessful();

    Mail::assertSentCount(1);
});

test('admins can save the schedule, which is off until they turn it on', function () {
    $this->travelTo(atJakarta('2026-11-06 09:00'));
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/email-reminders')
        ->assertSee(__('reminder.schedule.statusInactive'));

    $this->actingAs($admin)
        ->postJson(route('email-reminders.schedule'), schedulePayload(['active' => false]))
        ->assertOk()
        ->assertJsonPath('status.text', __('reminder.schedule.statusInactive'))
        ->assertJsonPath('status.logOnly', true);

    $response = $this->actingAs($admin)
        ->postJson(route('email-reminders.schedule'), schedulePayload(['time' => '08:30', 'attachments' => ['xlsx']]))
        ->assertOk();

    expect($response->json('status.text'))->toContain('09 Nov 2026 08:30 WIB');

    expect(ReminderSchedule::sole())
        ->frequency->toBe('weekly')
        ->weekday->toBe(1)
        ->monthday->toBeNull()
        ->time->toBe('08:30')
        ->pabrik->toBeNull()
        ->attachments->toBe(['xlsx'])
        ->active->toBeTrue()
        ->updated_by->toBe($admin->id);
});

test('turning it on after its time today does not fire it right away', function () {
    Mail::fake();
    picWithFinding();
    // Monday 10:00, for a schedule on Mondays at 08:00
    $this->travelTo(atJakarta('2026-11-09 10:00'));

    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('email-reminders.schedule'), schedulePayload())
        ->assertOk();

    expect($response->json('status.text'))->toContain('16 Nov 2026 08:00 WIB');

    $this->artisan('reminders:send-scheduled');
    Mail::assertNothingSent();

    $this->travelTo(atJakarta('2026-11-16 08:00'));
    $this->artisan('reminders:send-scheduled');
    Mail::assertSentCount(1);
});

test('the schedule is checked before it is saved', function (array $values, string $error) {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('email-reminders.schedule'), schedulePayload($values))
        ->assertJsonValidationErrors($error);

    expect(ReminderSchedule::count())->toBe(0);
})->with([
    'frequency' => [['frequency' => 'hourly'], 'frequency'],
    'weekday' => [['weekday' => 8], 'weekday'],
    'monthday' => [['frequency' => 'monthly', 'monthday' => 31], 'monthday'],
    'time' => [['time' => '25:00'], 'time'],
    'plant' => [['pabrik' => 'zzz'], 'pabrik'],
    'subject' => [['subject' => ''], 'subject'],
    'attachment' => [['attachments' => ['exe']], 'attachments.0'],
]);

test('only admins can change the schedule', function (Role $role) {
    $this->actingAs(User::factory()->create(['otorisasi' => $role]))
        ->postJson(route('email-reminders.schedule'), schedulePayload())
        ->assertForbidden();

    expect(ReminderSchedule::count())->toBe(0);
})->with([Role::Auditor, Role::Auditee]);

test('auditors see the schedule status with the note that only admins change it', function () {
    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditor]))->get('/email-reminders')
        ->assertOk()
        ->assertSee(__('reminder.schedule.adminOnly'));

    $this->actingAs(User::factory()->admin()->create())->get('/email-reminders')
        ->assertDontSee(__('reminder.schedule.adminOnly'));
});
