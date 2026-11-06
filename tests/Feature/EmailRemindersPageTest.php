<?php

use App\Enums\Role;
use App\Mail\FindingReminderMail;
use App\Models\NonConformity;
use App\Models\ReminderEmail;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\FindingReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('admins and auditors see the menu and the email sidebar', function (Role $role) {
    $this->actingAs(User::factory()->create(['otorisasi' => $role]))
        ->get('/email-reminders')
        ->assertOk()
        ->assertSee('href="'.url('/email-reminders').'"', false)
        ->assertSee('<title>'.__('meta.emailReminders.title').'</title>', false)
        ->assertSeeInOrder([__('smk3.comingSoon'), __('reminder.title')])
        ->assertSee(__('reminder.compose.title'))
        ->assertSee(__('reminder.folders.pending'))
        ->assertSee(__('reminder.folders.sent'))
        ->assertSee(__('reminder.folders.failed'));
})->with([Role::Admin, Role::Auditor]);

test('the write message tab can send manually, while the schedule and attachments stay disabled', function () {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get('/email-reminders')
        ->assertOk()
        ->assertSee(__('reminder.compose.modeManual'))
        ->assertSee(__('reminder.compose.modeSchedule'))
        ->assertSee(__('reminder.compose.frequency'))
        ->assertSee(__('reminder.compose.subject'))
        ->assertSee(__('reminder.compose.message'))
        ->assertSee('{jumlah_temuan}')
        ->assertSee(__('reminder.compose.attachPdf'))
        ->assertSee(__('reminder.compose.attachExcel'))
        ->assertSee('x-model="attachPdf"', false)
        ->assertSee('x-model="attachExcel"', false)
        ->assertSee(__('reminder.compose.notReady'));

    expect($response->getContent())
        ->toContain('name="subject"')
        ->toContain('name="message"')
        ->toContain(route('email-reminders.send'))
        ->toContain('email-reminders\/preview')
        ->not->toContain('@js(')
        ->toMatch('/<input[^>]*disabled[^>]*x-model="attachPdf"/')
        ->toMatch('/<button[^>]*disabled[^>]*x-show="mode === \'schedule\'"|<button[^>]*x-show="mode === \'schedule\'"[^>]*disabled/');
});

function reminderMessage(array $overrides = []): array
{
    return $overrides + ['subject' => 'Pengingat untuk {nama}', 'message' => "Yth. {nama},\n{departemen}: {jumlah_temuan} temuan.\n{tautan}"];
}

test('each PIC gets one email holding all of their findings', function () {
    Mail::fake();
    $budi = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Budi Auditee']);
    $sari = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Sari Auditee']);
    NonConformity::factory()->open()->count(2)->create(['pic' => $budi->email]);
    NonConformity::factory()->open()->create(['pic' => $sari->email]);
    NonConformity::factory()->open()->create(['pic' => 'gone@example.test']);
    NonConformity::factory()->closed()->create(['pic' => $sari->email]);
    NonConformity::factory()->open()->create(['pic' => $sari->email])
        ->photos()->create(['field' => 'foto_perbaikan', 'path' => 'uploads/a.jpg']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('email-reminders.send'), reminderMessage(['recipients' => [$budi->id, $sari->id]]))
        ->assertRedirect(route('email-reminders'))
        ->assertSessionHas('success', __('reminder.send.sent', ['sent' => 2]));

    Mail::assertSentCount(2);
    Mail::assertSent(FindingReminderMail::class, fn (FindingReminderMail $mail) => $mail->hasTo($budi->email) && $mail->findings->count() === 2);
    Mail::assertSent(FindingReminderMail::class, fn (FindingReminderMail $mail) => $mail->hasTo($sari->email) && $mail->findings->count() === 1);
});

test('only the chosen people get an email', function () {
    Mail::fake();
    $budi = User::factory()->create(['otorisasi' => Role::Auditee]);
    $sari = User::factory()->create(['otorisasi' => Role::Auditee]);
    NonConformity::factory()->open()->create(['pic' => $budi->email]);
    NonConformity::factory()->open()->create(['pic' => $sari->email]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('email-reminders.send'), reminderMessage(['recipients' => [$sari->id]]))
        ->assertSessionHas('success', __('reminder.send.sent', ['sent' => 1]));

    Mail::assertSentCount(1);
    Mail::assertSent(FindingReminderMail::class, fn (FindingReminderMail $mail) => $mail->hasTo($sari->email));
});

test('someone without findings in the list cannot be emailed by asking for their id', function () {
    Mail::fake();
    $stranger = User::factory()->create(['otorisasi' => Role::Auditee]);
    NonConformity::factory()->closed()->create(['pic' => $stranger->email]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('email-reminders.send'), reminderMessage(['recipients' => [$stranger->id]]))
        ->assertSessionHas('error', __('reminder.send.none'));

    Mail::assertNothingSent();
});

test('at least one recipient has to be chosen', function () {
    Mail::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->from('/email-reminders')
        ->post(route('email-reminders.send'), reminderMessage())
        ->assertSessionHasErrors('recipients');

    Mail::assertNothingSent();
});

test('the page offers the people to choose from, following the filters', function () {
    [$plant, $otherPlant] = array_keys(config('smk3.plants'));
    $admin = User::factory()->admin()->create();
    $budi = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Budi Pilihan']);
    $sari = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Sari Pilihan']);
    NonConformity::factory()->open()->count(2)->create(['pabrik' => $plant, 'pic' => $budi->email]);
    NonConformity::factory()->open()->create(['pabrik' => $otherPlant, 'pic' => $sari->email]);
    NonConformity::factory()->open()->create(['pabrik' => $plant, 'pic' => 'gone@example.test']);

    $response = $this->actingAs($admin)->get('/email-reminders')
        ->assertOk()
        ->assertSee(__('reminder.pick.button'))
        ->assertSee(__('reminder.pick.title'))
        ->assertSee(__('reminder.compose.recipients'))
        ->assertSee('Budi Pilihan')
        ->assertSee('Sari Pilihan');

    $recipients = app(FindingReminders::class)->recipients(NonConformity::query()->awaitingRepairPhoto());

    expect($recipients)->toHaveCount(2)
        ->and($recipients[0])->toMatchArray(['id' => $budi->id, 'email' => $budi->email, 'findings' => 2])
        ->and($response->getContent())->not->toContain('@js(');

    // the list follows the filter, also when only the results are reloaded
    $this->actingAs($admin)->get(route('email-reminders', ['pabrik' => $plant]), ['X-Partial' => 'results'])
        ->assertOk()
        ->assertSee('Budi Pilihan')
        ->assertDontSee('Sari Pilihan');
});

test('the written variables are filled in for the recipient', function () {
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Budi Auditee']);
    $finding = NonConformity::factory()->open()->create(['pic' => $pic->email, 'deskripsi_temuan' => 'lantai licin']);

    $mails = app(FindingReminders::class)->mails(NonConformity::query()->awaitingRepairPhoto(), 'Halo {nama}', "{nama} / {departemen} / {jumlah_temuan}\n{tautan}");
    $mail = $mails->sole();

    $mail->assertHasSubject('Halo Budi Auditee');
    $mail->assertSeeInHtml('Budi Auditee / '.$finding->departmentName().' / 1<br>', false);
    $mail->assertSeeInHtml('<a href="'.route('non-conformities', ['status' => 'open']).'"', false);
    $mail->assertSeeInHtml('lantai licin');
    $mail->assertSeeInText('lantai licin');
    $mail->assertDontSeeInHtml('{nama}');
});

test('text written in the message or a finding cannot add HTML to the email', function () {
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => '<b>Budi</b>']);
    NonConformity::factory()->open()->create(['pic' => $pic->email, 'deskripsi_temuan' => '<img src=x>']);

    $mail = app(FindingReminders::class)->mails(NonConformity::query()->awaitingRepairPhoto(), 'Halo', '<script>alert(1)</script> {nama}')->sole();

    expect($mail->render())
        ->not->toContain('<script>')
        ->not->toContain('<b>Budi</b>')
        ->not->toContain('<img src=x>');
});

test('sending follows the filters and the plants of the user', function () {
    Mail::fake();
    [$plant, $otherPlant] = array_keys(config('smk3.plants'));
    $own = User::factory()->create(['otorisasi' => Role::Auditee]);
    $other = User::factory()->create(['otorisasi' => Role::Auditee]);
    $category = config('smk3.categories')[0];
    NonConformity::factory()->open()->create(['pabrik' => $plant, 'pic' => $own->email, 'kategori' => $category]);
    NonConformity::factory()->open()->create(['pabrik' => $plant, 'pic' => $other->email, 'kategori' => config('smk3.categories')[1]]);
    NonConformity::factory()->open()->create(['pabrik' => $otherPlant, 'pic' => $other->email, 'kategori' => $category]);

    $auditor = User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => $plant]);

    $this->actingAs($auditor)
        ->post(route('email-reminders.send', ['kategori' => $category]), reminderMessage(['recipients' => [$own->id, $other->id]]))
        ->assertRedirect(route('email-reminders', ['kategori' => $category]));

    Mail::assertSentCount(1);
    Mail::assertSent(FindingReminderMail::class, fn (FindingReminderMail $mail) => $mail->hasTo($own->email));
});

test('nothing is sent when there are no recipients', function () {
    Mail::fake();
    NonConformity::factory()->open()->create(['pic' => 'gone@example.test']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('email-reminders.send'), reminderMessage(['recipients' => [999]]))
        ->assertSessionHas('error', __('reminder.send.none'));

    Mail::assertNothingSent();
});

test('a subject and message are required, and the rejected message returns to its tab', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    NonConformity::factory()->open()->create(['pic' => User::factory()->create(['otorisasi' => Role::Auditee])->email]);

    $this->actingAs($admin)
        ->from('/email-reminders')
        ->post(route('email-reminders.send'), ['subject' => '', 'message' => 'isi yang ditulis'])
        ->assertRedirect('/email-reminders')
        ->assertSessionHasErrors('subject');

    Mail::assertNothingSent();

    $this->actingAs($admin)->get('/email-reminders')
        ->assertSee("folder: 'compose'", false)
        ->assertSee('isi yang ditulis');
});

test('a mail server that cannot be reached shows as failed instead of an error page', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
    $pic = User::factory()->create(['otorisasi' => Role::Auditee]);
    NonConformity::factory()->open()->create(['pic' => $pic->email]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('email-reminders.send'), reminderMessage(['recipients' => [$pic->id]]))
        ->assertRedirect(route('email-reminders'))
        ->assertSessionHas('error', __('reminder.send.partial', ['sent' => 0, 'failed' => 1]));
});

function reminderLog(string $status, array $attributes = []): ReminderEmail
{
    return ReminderEmail::create($attributes + [
        'recipient_name' => 'Budi Auditee',
        'recipient_email' => 'budi@example.test',
        'subject' => 'Pengingat',
        'findings_count' => 1,
        'status' => $status,
    ]);
}

test('a failed email is recorded with its reason and shown on the failed tab', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
    $admin = User::factory()->admin()->create();
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Budi Auditee']);
    NonConformity::factory()->open()->count(2)->create(['pic' => $pic->email]);

    $this->actingAs($admin)
        ->post(route('email-reminders.send'), reminderMessage(['recipients' => [$pic->id]]))
        ->assertSessionHas('folder', 'failed');

    $log = ReminderEmail::sole();
    expect($log)
        ->status->toBe(ReminderEmail::FAILED)
        ->recipient_email->toBe($pic->email)
        ->subject->toBe('Pengingat untuk Budi Auditee')
        ->findings_count->toBe(2)
        ->sender_id->toBe($admin->id)
        ->and($log->error)->toContain('Connection could not be established');

    $this->actingAs($admin)->get('/email-reminders')
        ->assertOk()
        ->assertSee("folder: 'failed'", false)
        ->assertSee('Connection could not be established')
        ->assertSee(__('reminder.history.error'));
});

test('a sent email is recorded and shown on the sent tab', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $pic = User::factory()->create(['otorisasi' => Role::Auditee]);
    NonConformity::factory()->open()->create(['pic' => $pic->email]);

    $this->actingAs($admin)
        ->post(route('email-reminders.send'), reminderMessage(['recipients' => [$pic->id]]))
        ->assertSessionHas('folder', 'sent');

    expect(ReminderEmail::sole())->status->toBe(ReminderEmail::SENT)->error->toBeNull();

    $this->actingAs($admin)->get('/email-reminders')
        ->assertSee("folder: 'sent'", false)
        ->assertSee(__('reminder.history.showing', ['from' => 1, 'to' => 1, 'total' => 1]))
        ->assertSee(__('reminder.history.emptyFailed'))
        ->assertDontSee(__('reminder.history.error'));
});

test('admins and auditors see the whole send history', function (Role $role) {
    $sender = User::factory()->admin()->create(['name' => 'Pengirim Lain']);
    reminderLog(ReminderEmail::SENT, ['sender_id' => $sender->id, 'recipient_name' => 'Kiriman Terkirim']);
    reminderLog(ReminderEmail::FAILED, ['sender_id' => $sender->id, 'recipient_name' => 'Kiriman Gagal', 'error' => 'alasan gagal']);

    $this->actingAs(User::factory()->create(['otorisasi' => $role]))->get('/email-reminders')
        ->assertOk()
        ->assertSee('Kiriman Terkirim')
        ->assertSee('Kiriman Gagal')
        ->assertSee('Pengirim Lain')
        ->assertSee('alasan gagal');
})->with([Role::Admin, Role::Auditor]);

test('an email redirected by the trial address says so in the history', function () {
    Mail::fake();
    config(['mail.always_to' => 'trial@example.test']);
    $admin = User::factory()->admin()->create();
    $pic = User::factory()->create(['otorisasi' => Role::Auditee]);
    NonConformity::factory()->open()->create(['pic' => $pic->email]);

    $this->actingAs($admin)->post(route('email-reminders.send'), reminderMessage(['recipients' => [$pic->id]]));

    expect(ReminderEmail::sole())->redirected_to->toBe('trial@example.test');

    $this->actingAs($admin)->get('/email-reminders')
        ->assertSee(__('reminder.history.redirected', ['address' => 'trial@example.test']));
});

test('a page link of the history reopens its tab and stays on its own list', function () {
    $admin = User::factory()->admin()->create();
    reminderLog(ReminderEmail::FAILED, ['recipient_name' => 'Gagal Terlama', 'error' => 'x']);
    foreach (range(1, 10) as $number) {
        reminderLog(ReminderEmail::FAILED, ['recipient_name' => 'Gagal Baru', 'error' => 'x']);
    }

    $this->actingAs($admin)->get('/email-reminders')
        ->assertDontSee('Gagal Terlama')
        ->assertSee('failed_page=2', false);

    $this->actingAs($admin)->get('/email-reminders?failed_page=2&q=abc')
        ->assertOk()
        ->assertSee("folder: 'failed'", false)
        ->assertSee('Gagal Terlama');
});

test('the preview shows the first recipient without sending anything', function () {
    Mail::fake();
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Budi Auditee']);
    NonConformity::factory()->open()->create(['pic' => $pic->email, 'deskripsi_temuan' => 'lantai licin']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('email-reminders.preview'), reminderMessage(['recipients' => [$pic->id]]))
        ->assertOk()
        ->assertViewIs('pages.emailReminders.preview')
        ->assertSee($pic->email)
        ->assertSee('Pengingat untuk Budi Auditee')
        ->assertSee(__('reminder.preview.note', ['count' => 1]));

    expect($response->viewData('html'))->toContain('lantai licin');
    Mail::assertNothingSent();
});

test('with a trial address the email goes there and not to the real recipient', function () {
    config(['mail.always_to' => 'trial@example.test']);
    (new AppServiceProvider(app()))->boot();

    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Budi Auditee']);
    NonConformity::factory()->open()->create(['pic' => $pic->email, 'deskripsi_temuan' => 'lantai licin']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('email-reminders.send'), reminderMessage(['recipients' => [$pic->id]]))
        ->assertSessionHas('success');

    $sent = Mail::mailer()->getSymfonyTransport()->messages();

    expect($sent)->toHaveCount(1)
        ->and(array_map(fn ($address) => $address->getAddress(), $sent->first()->getEnvelope()->getRecipients()))->toBe(['trial@example.test'])
        // still the real recipient's content, so it can be checked
        ->and($sent->first()->getOriginalMessage()->getHtmlBody())->toContain('lantai licin');
});

test('auditees cannot preview or send reminders', function () {
    Mail::fake();
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee]);

    $this->actingAs($auditee)->post(route('email-reminders.preview'), reminderMessage())->assertForbidden();
    $this->actingAs($auditee)->post(route('email-reminders.send'), reminderMessage())->assertForbidden();

    Mail::assertNothingSent();
});

test('the needs reminder tab lists only open findings without a repair photo', function () {
    $pic = User::factory()->create(['otorisasi' => Role::Auditee, 'name' => 'Budi Auditee']);
    $finding = fn (string $description, array $attributes = []) => NonConformity::factory()->open()
        ->create($attributes + ['deskripsi_temuan' => $description, 'pic' => $pic->email]);

    $finding('needs-reminder-first');
    $finding('needs-reminder-second');
    $finding('needs-reminder-orphan', ['pic' => 'gone@example.test']);
    $finding('only-finding-photo')->photos()->create(['field' => 'foto_temuan', 'path' => 'uploads/a.jpg']);
    $finding('has-repair-photo')->photos()->create(['field' => 'foto_perbaikan', 'path' => 'uploads/b.jpg']);
    $finding('already-closed', ['status' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/email-reminders')
        ->assertOk()
        ->assertSee(['needs-reminder-first', 'needs-reminder-second', 'needs-reminder-orphan', 'only-finding-photo'])
        ->assertDontSee(['has-repair-photo', 'already-closed'])
        ->assertSee('Budi Auditee')
        ->assertSee('gone@example.test')
        ->assertSee(__('reminder.pending.noAccount'))
        // four findings for one PIC with an account; the orphan's address is not a recipient
        ->assertSee(__('reminder.pending.summary', ['findings' => 4, 'recipients' => 1]));
});

test('the needs reminder tab only lists findings of the plants the user may see', function () {
    [$own, $other] = array_keys(config('smk3.plants'));

    NonConformity::factory()->open()->create(['pabrik' => $own, 'deskripsi_temuan' => 'own-plant-finding']);
    NonConformity::factory()->open()->create(['pabrik' => $other, 'deskripsi_temuan' => 'other-plant-finding']);

    $this->actingAs(User::factory()->create(['otorisasi' => Role::Auditor, 'pabrik' => $own]))
        ->get('/email-reminders')
        ->assertOk()
        ->assertSee('own-plant-finding')
        ->assertDontSee('other-plant-finding');
});

test('the needs reminder tab shows the longest waiting findings first, ten per page', function () {
    $admin = User::factory()->admin()->create();

    NonConformity::factory()->open()->create(['tanggal' => '2026-03-01', 'deskripsi_temuan' => 'newest-finding']);
    NonConformity::factory()->open()->create(['tanggal' => '2026-01-01', 'deskripsi_temuan' => 'oldest-finding']);
    NonConformity::factory()->open()->count(9)->create(['tanggal' => '2026-02-01']);

    $this->actingAs($admin)->get('/email-reminders')
        ->assertOk()
        ->assertSee('oldest-finding')
        ->assertDontSee('newest-finding')
        ->assertSee('page=2', false);

    $this->actingAs($admin)->get('/email-reminders?page=2')
        ->assertOk()
        ->assertSee('newest-finding');

    // a page that no longer exists goes back to the last one
    $this->actingAs($admin)->get('/email-reminders?page=9')
        ->assertRedirect(route('email-reminders', ['page' => 2]));
});

test('the needs reminder tab says so when there is nothing to remind about', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/email-reminders')
        ->assertOk()
        ->assertSee(__('reminder.pending.empty'));
});

test('the needs reminder tab can be searched and filtered', function () {
    [$plant, $otherPlant] = array_keys(config('smk3.plants'));
    $admin = User::factory()->admin()->create();
    $category = config('smk3.categories')[0];
    $otherCategory = config('smk3.categories')[1];

    $match = NonConformity::factory()->open()->create([
        'tanggal' => '2026-05-10', 'pabrik' => $plant, 'kategori' => $category, 'deskripsi_temuan' => 'matching-finding',
    ]);
    $other = NonConformity::factory()->open()->create([
        'tanggal' => '2026-01-10', 'pabrik' => $otherPlant, 'kategori' => $otherCategory, 'deskripsi_temuan' => 'other-finding',
    ]);

    foreach ([
        ['q' => 'matching'],
        ['pabrik' => $plant],
        ['kategori' => $category],
        ['dari' => '2026-05-01', 'sampai' => '2026-05-31'],
    ] as $filters) {
        $this->actingAs($admin)->get(route('email-reminders', $filters))
            ->assertOk()
            ->assertSee('matching-finding')
            ->assertDontSee('other-finding')
            ->assertSee(__('reminder.pending.summary', ['findings' => 1, 'recipients' => 0]));
    }

    $this->actingAs($admin)->get(route('email-reminders', ['departemen' => $match->departemen, 'pabrik' => $plant]))
        ->assertSee('matching-finding')
        ->assertDontSee('other-finding');
});

test('a filter never brings back findings that do not need a reminder', function () {
    $admin = User::factory()->admin()->create();
    NonConformity::factory()->closed()->create(['deskripsi_temuan' => 'closed-finding']);
    NonConformity::factory()->open()->create(['deskripsi_temuan' => 'photo-finding'])
        ->photos()->create(['field' => 'foto_perbaikan', 'path' => 'uploads/a.jpg']);

    $this->actingAs($admin)->get(route('email-reminders', ['q' => 'finding']))
        ->assertOk()
        ->assertDontSee(['closed-finding', 'photo-finding'])
        ->assertSee(__('nc.empty'));
});

test('filtering only returns the results part over AJAX and the page keeps the filter panel without a status', function () {
    $admin = User::factory()->admin()->create();
    NonConformity::factory()->open()->create(['deskripsi_temuan' => 'ajax-finding']);

    $this->actingAs($admin)->get(route('email-reminders', ['q' => 'ajax']), ['X-Partial' => 'results'])
        ->assertOk()
        ->assertSee('ajax-finding')
        ->assertDontSee('filter-kategori')
        ->assertDontSee('<html', false);

    $this->actingAs($admin)->get(route('email-reminders', ['q' => 'ajax']))
        ->assertSee('filter-kategori')
        ->assertDontSee('filter-status');
});

test('the needs reminder filters are remembered when coming back to the page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('email-reminders', ['q' => 'saved']))->assertOk();

    $this->actingAs($admin)->get('/email-reminders')
        ->assertRedirect(route('email-reminders', ['q' => 'saved']));
});

test('auditees neither see the menu nor open the page', function () {
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee]);

    $this->actingAs($auditee)
        ->get('/progress-monitoring')
        ->assertOk()
        ->assertDontSee('href="'.url('/email-reminders').'"', false);

    $this->actingAs($auditee)->get('/email-reminders')->assertForbidden();
});
