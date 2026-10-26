<?php

declare(strict_types=1);

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/changelog-'.Str::random();

    File::ensureDirectoryExists($this->directory);
    File::copy(__DIR__.'/../Fixtures/CHANGELOG.md', $this->directory.'/CHANGELOG.md');
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

function changelog(string ...$arguments): ProcessResult
{
    return Process::path(test()->directory)->run([__DIR__.'/../../bin/changelog', ...$arguments]);
}

function rewriteChangelog(string $search, string $replace): void
{
    $path = test()->directory.'/CHANGELOG.md';

    File::put($path, Str::replaceFirst($search, $replace, File::get($path)));
}

it('accepts the package changelog', function (): void {
    $result = Process::path(__DIR__.'/../..')->run(['bin/changelog', 'check']);

    expect($result->errorOutput())->toBe('')
        ->and($result->successful())->toBeTrue();
});

it('moves the unreleased entries into the release', function (): void {
    $result = changelog('release', '0.3.0', '2026-11-02');

    expect($result->errorOutput())->toBe('')
        ->and($result->output())->toBe("0.3.0\n")
        ->and(File::get($this->directory.'/CHANGELOG.md'))->toBe(File::get(__DIR__.'/../Fixtures/CHANGELOG-0.3.0.md'));
});

it('releases the next version after the latest release', function (string $type, string $version): void {
    $result = changelog('release', $type, '2026-11-02');

    expect($result->output())->toBe("{$version}\n")
        ->and(File::get($this->directory.'/CHANGELOG.md'))->toContain("## [{$version}] - 2026-11-02\n\n### Added\n\n- Record the queue wait time.");
})->with([
    ['patch', '0.2.1'],
    ['minor', '0.3.0'],
    ['major', '1.0.0'],
]);

it('prints the latest release', function (): void {
    expect(changelog('latest')->output())->toBe("0.2.0\n");
});

it('prints the entries of a release', function (): void {
    expect(changelog('notes', '0.2.0')->output())
        ->toBe("### Fixed\n\n- Keep the default log channel when the tap fails.\n  Report the error instead.\n");
});

it('refuses a release without unreleased entries', function (): void {
    rewriteChangelog("### Added\n\n- Record the queue wait time.\n\n", '');

    $result = changelog('release', '0.3.0', '2026-11-02');

    expect($result->failed())->toBeTrue()
        ->and($result->errorOutput())->toContain('add entries under ## [Unreleased] before the release');
});

it('refuses a release that is not newer than the last release', function (): void {
    $result = changelog('release', '0.2.0', '2026-11-02');

    expect($result->failed())->toBeTrue()
        ->and($result->errorOutput())->toContain('CHANGELOG.md:16: list releases newest first');
});

it('rejects a changelog that breaks the format', function (string $search, string $replace, string $error): void {
    rewriteChangelog($search, $replace);

    $result = changelog('check');

    expect($result->failed())->toBeTrue()
        ->and($result->errorOutput())->toContain($error);
})->with([
    'another header' => ['# Changelog', '# Release Notes', 'CHANGELOG.md:1: start the file with this header'],
    'an unknown change type' => ['### Fixed', '### Improved', 'CHANGELOG.md:16: use Added, Changed, Deprecated, Removed, Fixed or Security'],
    'a duplicate change type' => ['### Fixed', "### Added\n\n- Trace jobs.\n\n### Added", 'CHANGELOG.md:20: merge the two ### Added sections'],
    'an empty change type' => ['### Fixed', "### Security\n\n### Fixed", 'CHANGELOG.md:16: add entries under ### Security'],
    'a release without entries' => ["### Fixed\n\n- Keep the default log channel when the tap fails.\n  Report the error instead.\n", '', 'CHANGELOG.md:14: add entries to 0.2.0'],
    'releases out of order' => ['## [0.2.0]', '## [0.0.9]', 'CHANGELOG.md:21: list releases newest first'],
    'dates out of order' => ['2026-10-30', '2026-10-01', 'CHANGELOG.md:21: date 0.1.0 on or before 0.2.0'],
    'an invalid date' => ['2026-10-30', '2026-13-30', 'CHANGELOG.md:14: write the date as YYYY-MM-DD'],
    'another heading format' => ['## [0.2.0] - 2026-10-30', '## v0.2.0 (2026-10-30)', 'CHANGELOG.md:14: write the release heading as ## [1.2.3] - 2026-01-31'],
    'an asterisk list' => ['- Keep', '* Keep', 'CHANGELOG.md:18: put each entry in a - list under a ### change type'],
    'text outside a list' => ['### Fixed', "Fixes from the review.\n\n### Fixed", 'CHANGELOG.md:16: put each entry in a - list under a ### change type'],
    'a missing link' => ["[0.1.0]: https://github.com/ActiveEngagement/laravel-telemetry/releases/tag/v0.1.0\n", '', 'CHANGELOG.md: end the file with these links'],
    'text after the links' => ['tag/v0.1.0', "tag/v0.1.0\n\nThanks to all contributors.", 'CHANGELOG.md:31: keep the links at the end of the file'],
]);
