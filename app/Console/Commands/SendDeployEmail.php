<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendDeployEmail extends Command
{
    protected $signature = 'mail:deploy-success
                            {email? : Recipient email address (defaults to DEPLOY_EMAIL)}
                            {commit? : Git commit short hash}
                            {changes? : Description of what changed}';

    protected $description = 'Send a deployment-success notification email';

    public function handle(): int
    {
        $to = $this->argument('email') ?: config('okyema.app.deploy_email');

        if (! $to) {
            $this->error('No recipient configured. Pass an email argument or set DEPLOY_EMAIL.');

            return self::FAILURE;
        }

        $commit = $this->argument('commit') ?: 'unknown';
        $changes = $this->argument('changes');
        $version = config('okyema.app.version', '0.0.0');
        $deployedAt = Carbon::now()->toDateTimeString();

        $body = "Okyema was deployed successfully.\n\n"
            ."Version: {$version}\n"
            ."Commit: {$commit}\n"
            ."Deployed at: {$deployedAt}\n"
            .'URL: '.config('app.url')."\n";

        if ($changes) {
            $body .= "\nWhat changed:\n{$changes}\n";
        }

        try {
            Mail::raw($body, function ($message) use ($to, $version) {
                $message->to($to)->subject("Okyema v{$version} deployed successfully");
            });
        } catch (\Throwable $e) {
            report($e);
            $this->error('Failed to send deploy email: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Deploy email sent to {$to}");

        return self::SUCCESS;
    }
}
