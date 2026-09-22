<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActionItem;
use App\Models\Calendar;
use App\Models\ConnectorAccount;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Note;
use App\Models\Profile;
use App\Models\User;
use App\Models\WorkspaceContext;
use App\Services\WorkspaceContextService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A demo user with two calendars and a handful of events so Milestone 1 can
 * be seen working without any real provider credentials.
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoSeeder
 *
 * Sign in as john@okyema.test / password.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'john@okyema.test'],
            [
                'name' => 'John Yeboah',
                'password' => Hash::make('password'),
            ],
        );

        Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => 'John Yeboah',
                'timezone' => 'Europe/London',
                'currency' => config('okyema.currency.code'),
            ],
        );

        app(WorkspaceContextService::class)->seedDefaultsFor($user);

        $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();
        $launchpad = WorkspaceContext::where('type', 'LAUNCHPAD')->firstOrFail();
        $personal = WorkspaceContext::where('type', 'PERSONAL')->firstOrFail();

        $google = ConnectorAccount::firstOrCreate(
            ['user_id' => $user->id, 'provider' => 'google', 'external_account_id' => 'john@okyema.test'],
            ['status' => 'connected', 'scopes' => ['calendar.read']],
        );
        $microsoft = ConnectorAccount::firstOrCreate(
            ['user_id' => $user->id, 'provider' => 'microsoft', 'external_account_id' => 'john@okyema.test'],
            ['status' => 'connected', 'scopes' => ['calendar.read']],
        );

        $regnoCalendar = Calendar::firstOrCreate(
            ['connector_account_id' => $google->id, 'provider_calendar_id' => 'primary'],
            [
                'user_id' => $user->id,
                'workspace_context_id' => $regno->id,
                'name' => 'Google Calendar',
                'provider' => 'google',
                'timezone' => 'Europe/London',
                'colour' => '#7457FF',
                'is_primary' => true,
            ],
        );

        $launchpadCalendar = Calendar::firstOrCreate(
            ['connector_account_id' => $microsoft->id, 'provider_calendar_id' => 'primary'],
            [
                'user_id' => $user->id,
                'workspace_context_id' => $launchpad->id,
                'name' => 'Outlook Calendar',
                'provider' => 'microsoft',
                'timezone' => 'Europe/London',
                'colour' => '#36D7EB',
                'is_primary' => true,
            ],
        );

        $personalCalendar = Calendar::firstOrCreate(
            ['user_id' => $user->id, 'workspace_context_id' => $personal->id, 'name' => 'Personal'],
            [
                'connector_account_id' => null,
                'provider' => 'microsoft',
                'timezone' => 'Europe/London',
                'colour' => '#FFFFFF',
            ],
        );

        $this->events($user, $regno, $regnoCalendar, [
            [$this->at(10, 00), $this->at(10, 45), 'Regno stand-up', 'Stand-up with the Regno team', 'confirmed', false],
            [$this->at(14, 30), $this->at(15, 30), 'Investor update prep', null, 'tentative', false],
            [$this->at(16, 0), $this->at(17, 0), 'Board pack review', null, 'confirmed', false],
            [$this->day(2)->setTime(9, 0), $this->day(2)->setTime(10, 0), 'Product demo', null, 'confirmed', false],
            [$this->day(5)->startOfDay(), null, 'Company offsite', null, 'confirmed', true],
        ], 'google');

        $this->events($user, $launchpad, $launchpadCalendar, [
            [$this->at(11, 0), $this->at(12, 0), 'Launchpad roadmap', null, 'confirmed', false],
            [$this->at(14, 30), $this->at(15, 0), 'Launchpad quick sync', 'Overlaps the Regno investor slot to demo conflicts', 'confirmed', false],
            [$this->day(3)->setTime(13, 0), $this->day(3)->setTime(14, 0), 'Candidate interview', null, 'confirmed', false],
        ], 'microsoft');

        $this->events($user, $personal, $personalCalendar, [
            [$this->day(1)->setTime(19, 0), $this->day(1)->setTime(20, 0), 'Dinner with friends', null, 'confirmed', false],
        ], 'microsoft');

        $this->seedMeetingWorkspace($user, $regno);
    }

    private function seedMeetingWorkspace(User $user, WorkspaceContext $regno): void
    {
        $meeting = Meeting::firstOrCreate(
            ['user_id' => $user->id, 'workspace_context_id' => $regno->id, 'title' => 'Regno product kickoff'],
            [
                'agenda' => 'Agree the launch plan and owners.',
                'starts_at' => $this->day(1)->setTime(9, 0),
                'ends_at' => $this->day(1)->setTime(10, 0),
            ],
        );

        Note::firstOrCreate(
            ['meeting_id' => $meeting->id, 'body' => "Decision: Launch the mobile app in October\nAction: Finalise pricing @John by ".$this->day(3)->toDateString()." #high\nAction: Book the launch venue #medium"],
            ['user_id' => $user->id, 'workspace_context_id' => $regno->id, 'source_type' => 'manual'],
        );

        MeetingParticipant::firstOrCreate(
            ['meeting_id' => $meeting->id, 'name' => 'Ama Mensah'],
            ['organisation' => 'Regno', 'role' => 'Product'],
        );

        ActionItem::firstOrCreate(
            ['user_id' => $user->id, 'workspace_context_id' => $regno->id, 'title' => 'Send the investor update'],
            ['priority' => 'high', 'due_date' => $this->day(-1)->toDateString()],
        );
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable|null, 2: string, 3: string|null, 4: string, 5: bool}>  $rows
     */
    private function events(User $user, WorkspaceContext $context, Calendar $calendar, array $rows, string $provider): void
    {
        foreach ($rows as [$starts, $ends, $title, $location, $state, $allDay]) {
            Event::updateOrCreate(
                ['provider' => $provider, 'provider_event_id' => 'demo-'.str_replace(' ', '-', strtolower($title))],
                [
                    'calendar_id' => $calendar->id,
                    'user_id' => $user->id,
                    'workspace_context_id' => $context->id,
                    'title' => $title,
                    'location' => $location,
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                    'is_all_day' => $allDay,
                    'state' => $state,
                    'timezone' => 'Europe/London',
                ],
            );
        }
    }

    private function at(int $hour, int $minute): CarbonImmutable
    {
        return CarbonImmutable::now('Europe/London')->setTime($hour, $minute, 0)->utc();
    }

    private function day(int $offset): CarbonImmutable
    {
        return CarbonImmutable::now('Europe/London')->addDays($offset);
    }
}
