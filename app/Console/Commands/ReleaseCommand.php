<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Cut a release: bump the version, prove the tests pass, commit, tag, push.
 *
 *     php artisan okyema:release patch
 *
 * The push to `main` is what triggers the deploy workflow, and the version is
 * the `?v=` cache-buster for the CSS and PWA icons — so this is the step that
 * makes a release actually reach the users.
 */
class ReleaseCommand extends Command
{
    protected $signature = 'okyema:release
        {type=patch : major, minor or patch}
        {--dry-run : Show what would happen and change nothing}
        {--no-push : Commit and tag locally, but do not push}
        {--skip-tests : Do not run the test suite first}
        {--repo= : Working tree to release from (defaults to the project root)}';

    protected $description = 'Bump the version, commit it, tag it and push (which deploys)';

    /** The working tree being released. */
    private string $repo = '';

    public function handle(): int
    {
        $type = (string) $this->argument('type');
        $dryRun = (bool) $this->option('dry-run');
        $this->repo = (string) ($this->option('repo') ?: base_path());

        if (! in_array($type, Version::TYPES, true)) {
            $this->error("Unknown release type [{$type}] — use major, minor or patch.");

            return self::FAILURE;
        }

        $current = (string) config('okyema.app.version');
        $next = Version::bump($current, $type);
        $tag = 'v'.$next;

        $this->newLine();
        $this->line("  <fg=gray>current</>  v{$current}");
        $this->line("  <fg=gray>next</>     <fg=green>v{$next}</> ({$type})");
        $this->line('  <fg=gray>config</>   config/okyema.php');
        $this->line('  <fg=gray>tag</>      '.$tag);
        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run: nothing written. Run without --dry-run to release v{$next}.");

            return self::SUCCESS;
        }

        if ($this->tagExists($tag)) {
            $this->error("Tag {$tag} already exists — pick a different release type.");

            return self::FAILURE;
        }

        if ($this->treeIsDirty()) {
            $this->error('Your working tree has uncommitted changes — commit or stash them first.');
            $this->line('  A release commit must contain the version bump and nothing else.');

            return self::FAILURE;
        }

        if (! $this->option('skip-tests') && ! $this->testsPass()) {
            $this->error('Tests are failing — not releasing.');

            return self::FAILURE;
        }

        // Last gate before anything is written: this command commits, tags and
        // pushes, and a test suite must never be able to do that.
        if (app()->runningUnitTests() || app()->environment('testing')) {
            $this->error('Refusing to release: this is the test environment. Use --dry-run.');

            return self::FAILURE;
        }

        $this->writeVersion($next);
        $this->line("  bumped config/okyema.php to {$next}");

        if (! $this->git('git add config/okyema.php')) {
            return self::FAILURE;
        }

        if (! $this->git('git commit -m '.escapeshellarg("Release {$tag}"))) {
            return self::FAILURE;
        }

        if (! $this->git('git tag -a '.escapeshellarg($tag).' -m '.escapeshellarg("Okyema {$tag}"))) {
            $this->error("Committed, but the tag failed — tag {$tag} by hand.");

            return self::FAILURE;
        }

        $this->line("  committed and tagged {$tag}");

        if ($this->option('no-push')) {
            $this->info("Released {$tag} locally. Push it with: git push origin master && git push origin {$tag}");

            return self::SUCCESS;
        }

        if (! $this->git('git push origin master') || ! $this->git('git push origin '.escapeshellarg($tag))) {
            $this->error('Pushed nothing further — check the output above.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("✓ Released {$tag}. The push to master triggers the deploy workflow.");

        return self::SUCCESS;
    }

    protected function treeIsDirty(): bool
    {
        return trim($this->outputOf('git status --porcelain')) !== '';
    }

    protected function tagExists(string $tag): bool
    {
        return Process::path($this->repo)
            ->run('git rev-parse --verify --quiet refs/tags/'.escapeshellarg($tag))
            ->successful();
    }

    private function testsPass(): bool
    {
        $this->line('  running the test suite…');

        $result = Process::timeout(300)
            ->env($this->testEnvironment())
            ->run('php vendor/bin/pest');

        if ($result->successful()) {
            return true;
        }

        foreach (array_slice(array_filter(explode("\n", $result->output())), -15) as $line) {
            $this->line('  '.$line);
        }

        if (trim($result->errorOutput()) !== '') {
            $this->line('  '.trim($result->errorOutput()));
        }

        return false;
    }

    /**
     * The environment the suite expects, taken straight from phpunit.xml.
     *
     * @return array<string, string>
     */
    private function testEnvironment(): array
    {
        $environment = ['APP_ENV' => 'testing'];

        $path = $this->repo.DIRECTORY_SEPARATOR.'phpunit.xml';

        if (! is_file($path)) {
            return $environment;
        }

        $xml = @simplexml_load_string((string) file_get_contents($path));

        if ($xml === false || ! isset($xml->php)) {
            return $environment;
        }

        foreach ($xml->php->env as $env) {
            $name = (string) $env['name'];
            $value = (string) $env['value'];

            if ($name !== '') {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }

    /**
     * Bump the version in the config of the working tree being released.
     */
    private function writeVersion(string $version): void
    {
        $path = $this->repo.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'okyema.php';

        if (! is_file($path)) {
            throw new \RuntimeException("No config/okyema.php in {$this->repo}.");
        }

        $contents = (string) file_get_contents($path);
        $updated = preg_replace("/'version'\s*=>\s*'[^']*'/", "'version' => '{$version}'", $contents, 1);

        if ($updated === null || $updated === $contents) {
            throw new \RuntimeException('Could not find the version line in '.$path);
        }

        file_put_contents($path, $updated);
    }

    private function git(string $command): bool
    {
        $result = Process::path($this->repo)->run($command);

        foreach (trim($result->output()) === '' ? [] : explode("\n", trim($result->output())) as $line) {
            $this->line('  '.$line);
        }

        if ($result->failed()) {
            $this->error('  failed: '.$command);

            if (trim($result->errorOutput()) !== '') {
                $this->line('  '.trim($result->errorOutput()));
            }
        }

        return $result->successful();
    }

    private function outputOf(string $command): string
    {
        return Process::path($this->repo)->run($command)->output();
    }
}
