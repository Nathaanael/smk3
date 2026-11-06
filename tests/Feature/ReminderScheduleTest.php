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

test('scheduled emails use the mailer of the application and show in the history', function () {
    // phpunit.xml: MAIL_MAILER=array, so this stays in memory
    $pic = picWithFinding();
    reminderSchedule();
    $this->travelTo(atJakarta('2026-11-09 08:00'));

    $this->artisan('reminders:send-scheduled')->assertSuccessful();

    $sent = Mail::mailer()->getSymfonyTransport()->messages();
    expect(array_map(fn ($address) => $address->getAddress(), $sent->sole()->getEnvelope()->getRecipients()))->toBe([$pic->email]);

    expect(ReminderEmail::sole())
        ->status->toBe(ReminderEmail::SENT)
        ->mailer->toBe('array')
        ->scheduled->toBeTrue()
        ->sender_id->toBeNull();

    $this->actingAs(User::factory()->admin()->create())->get('/email-reminders')
        ->assertSee(__('reminder.history.scheduled'))
        ->assertDontSee(__('reminder.history.logOnly'));
});

test('an email that was only written to the log stays marked in the history', function () {
    ReminderEmail::create([
        'scheduled' => true,
        'mailer' => 'log',
        'recipient_name' => 'Budi',
        'recipient_email' => 'budi@example.test',
        'subject' => 'Pengingat',
        'findings_count' => 1,
        'status' => ReminderEmail::SENT,
    ]);

    $this->actingAs(User::factory()->admin()->create())->get('/email-reminders')
        ->assertSee(__('reminder.history.logOnly'));
});

test('the command has no way to skip the schedule', function () {
    $this->artisan('reminders:send-scheduled --now')->assertFailed();
})->throws(Symfony\Component\Console\Exception\RuntimeException::class);

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

test('admins can save the schedule, which is off until they turn it on', function () {
    $this->travelTo(atJakarta('2026-11-06 09:00'));
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/email-reminders')
        ->assertSee(__('reminder.schedule.statusInactive'));

    $this->actingAs($admin)
        ->postJson(route('email-reminders.schedule'), schedulePayload(['active' => false]))
        ->assertOk()
        ->assertJsonPath('status.text', __('reminder.schedule.statusInactive'));

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
