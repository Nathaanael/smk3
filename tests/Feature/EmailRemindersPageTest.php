<?php

use App\Enums\Role;
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

test('auditees neither see the menu nor open the page', function () {
    $auditee = User::factory()->create(['otorisasi' => Role::Auditee]);

    $this->actingAs($auditee)
        ->get('/progress-monitoring')
        ->assertOk()
        ->assertDontSee('href="'.url('/email-reminders').'"', false);

    $this->actingAs($auditee)->get('/email-reminders')->assertForbidden();
});
