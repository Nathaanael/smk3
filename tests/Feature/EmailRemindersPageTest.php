<?php

use App\Enums\Role;
use App\Models\NonConformity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('the write message tab offers manual and scheduled sending, with the actions still disabled', function () {
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

    // nothing can be sent yet: no form, and both actions are disabled
    expect($response->getContent())
        ->not->toContain('name="subject"')
        ->toMatch('/<button[^>]*disabled[^>]*x-show="mode === \'manual\'"|<button[^>]*x-show="mode === \'manual\'"[^>]*disabled/')
        ->toMatch('/<button[^>]*disabled[^>]*x-show="mode === \'schedule\'"|<button[^>]*x-show="mode === \'schedule\'"[^>]*disabled/');
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
