<?php

declare(strict_types=1);

use App\Services\Meetings\MeetingExtractor;

it('extracts decisions and actions from note markers', function () {
    $result = (new MeetingExtractor)->extract([
        "Decision: Ship the mobile app by Q3\nAction: Draft release notes @John by 2026-09-30 #high\nAction: Book venue #low\nJust a normal line.\n",
    ]);

    expect($result['decisions'])->toHaveCount(1);
    expect($result['decisions'][0]['title'])->toBe('Ship the mobile app by Q3');
    expect($result['decisions'][0]['quote'])->toContain('Decision:');

    expect($result['actions'])->toHaveCount(2);
    expect($result['actions'][0]['title'])->toBe('Draft release notes');
    expect($result['actions'][0]['owner'])->toBe('John');
    expect($result['actions'][0]['due_date'])->toBe('2026-09-30');
    expect($result['actions'][0]['priority'])->toBe('high');
    expect($result['actions'][1]['title'])->toBe('Book venue');
    expect($result['actions'][1]['priority'])->toBe('low');
});

it('ignores empty markers', function () {
    $result = (new MeetingExtractor)->extract(["Decision:   \nAction:\n"]);

    expect($result['decisions'])->toBe([]);
    expect($result['actions'])->toBe([]);
});

it('produces a factual, non-fabricated summary', function () {
    $summary = (new MeetingExtractor)->summarise(['Decision: X', 'Action: Y'], 1, 1);

    expect($summary)->toContain('2 notes');
    expect($summary)->toContain('1 decision');
    expect($summary)->toContain('1 action');
});
