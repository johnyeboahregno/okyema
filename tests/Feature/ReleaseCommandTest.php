<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;

test('the release command supports a dry run without changing anything', function () {
    $this->artisan('okyema:release', ['type' => 'patch', '--dry-run' => true])
        ->expectsOutputToContain('next')
        ->assertSuccessful();
});

test('the release command refuses an unknown release type', function () {
    $this->artisan('okyema:release', ['type' => 'banana'])->assertFailed();
});

test('the release command never writes in the test environment', function () {
    $this->artisan('okyema:release', ['type' => 'patch', '--skip-tests' => true])->assertFailed();
});

test('the deploy email command fails without a recipient', function () {
    config(['okyema.app.deploy_email' => '']);

    $this->artisan('mail:deploy-success')->assertFailed();
});

test('the deploy email command sends to the configured recipient', function () {
    Mail::fake();

    $this->artisan('mail:deploy-success', [
        'email' => 'ops@okyema.test',
        'commit' => 'abc123',
        'changes' => 'Added deploy script',
    ])->expectsOutputToContain('Deploy email sent to ops@okyema.test')
        ->assertSuccessful();
});
