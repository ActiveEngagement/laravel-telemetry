# laravel-telemetry

`activeengagement/laravel-telemetry` is the shared OpenTelemetry setup for ActiveEngagement Laravel apps. It is a Laravel package, not an app: there is no `artisan`, no `.env` and no database. Linear ticket: HEIM-72 (team Heimdall).

## Foundational context

- php - ^8.4
- laravel/framework (illuminate/*) - ^12.0 | ^13.30
- keepsuit/laravel-opentelemetry - ^2.2.4
- laravel/ai - ^1.3 (optional)
- laravel/horizon - ^5 (optional)
- orchestra/testbench - ^10 | ^11
- pestphp/pest - v4
- phpstan/phpstan with larastan/larastan - level max
- rector/rector with driftingly/rector-laravel
- laravel/pint

## Package design

- Laravel's first-party packages (Telescope, Pulse, Horizon, Nightwatch, laravel/ai) are the model. Read their source before you propose a design, and cite the file you followed.
- The package extends keepsuit. It does not wrap it. OpenTelemetry settings stay in keepsuit's `opentelemetry` config and the `OTEL_*` env vars.
- `config/telemetry.php` holds only the package's own keys: `enabled`, `deployment` and `watchers`. Add an option only when it must exist. Prefer events and hooks for extension.
- Only adapter classes (for example `Support\KeepsuitContextResolver`) read keepsuit config. Watchers never do.
- Keep keepsuit types out of the public API.
- Features are watchers (`Watchers\Watcher`) listed in `telemetry.watchers`, as in Telescope.
- Telemetry must never break the app. Wrap runtime hooks in `rescue(..., report: false)`. Wrap boot-time config errors in `rescue()`, so that they are reported.
- When a contract exists, inject it in `__construct()`. When none exists, use the facade.
- Classes are never `final`. Use `protected`, not `private`, so that apps can extend the classes.

## Conventions

- Follow the structure, approach and naming of sibling files.
- Use descriptive names for variables and methods.
- Do not create verification scripts when a test can prove the behavior.
- Do not change dependencies without approval.
- Do not create documentation files unless asked. Keep the README short.
- When a public API, env var or attribute name changes, also update `README.md` and the Boost skill in `resources/boost/skills/telemetry-development/SKILL.md`.
- Be concise in explanations.

## PHP

- Add `declare(strict_types=1);` to every PHP file.
- Always use curly braces for control structures.
- Use constructor property promotion. Do not leave an empty zero-parameter `__construct()`.
- Declare all parameter and return types. Use array shapes in PHPDoc.
- Comments are at most two lines. Use PHPDoc only for types and exceptions (`@param`, `@return`, `@var`, `@throws`). No other annotations.
- Overriding methods get `#[Override]`. Rector adds it.

## Testing

- Tests are Pest feature tests with Orchestra Testbench, in `tests/Feature`. Spans and logs go to the in-memory exporters (`phpunit.xml.dist`).
- Use the helpers in `tests/Pest.php`: `exportedSpans()`, `exportedLogs()`, `rewatch()` and `refreshWithEnv()`.
- Every change needs a new or updated test. Run the smallest set first: `vendor/bin/pest --compact tests/Feature/LogWatcherTest.php` or `--filter`.
- Do not delete tests without approval.

### Test doubles

- Fixtures and hand-written doubles live in `tests/Fixtures`. Doubles are named `Fake*` (for example `FakeLogger`).
- Write a double. Do not use Mockery or `createMock()`. Laravel's fakes (`Event::fake()`, `Exceptions::fake()`) and the laravel/ai fakes (`SupportAgent::fake()`, `Embeddings::fake()`) are fine.
- A double implements a contract. Do not double a concrete class.
- Double only external boundaries (the OpenTelemetry logger and exporters, Horizon). Do not double the package's own classes.
- Test observable behavior: exported spans and logs, reported exceptions. Do not assert private state or call private methods with reflection.

## Quality checks

After you change code, run:

```bash
composer qa
```

It fixes code style (Rector, Pint), then runs the tests, the style checks and PHPStan. The other scripts are `lint`, `test`, `test:unit`, `test:lint` and `test:types`.

CI runs `composer test:unit` on PHP 8.4, 8.5 and 8.6 with Laravel 12 and 13, and `composer test:types` and `composer test:lint` on PHP 8.4. PHP 8.6 jobs are allowed to fail.

## Committing

All commit messages and PR titles follow Conventional Commits: `<type>(<scope>): <description>`.

Types:

- `feat:` - new features
- `fix:` - bug fixes in package behavior (not CI, docs or infrastructure)
- `refactor:` - code refactoring
- `docs:` - documentation changes
- `style:` - code style or formatting, no logic changes
- `perf:` - performance improvements
- `test:` - test changes
- `chore:` - maintenance, releases, dependency updates, CI and infrastructure
- `security:` - security changes

Description style:

- Use lowercase after the colon.
- Use the imperative mood ("add feature", not "added feature").
- Keep it short but descriptive. The scope is a class or an area.
- Do not add Claude as a co-author.

Examples:

- `fix(LogWatcher): keep the default channel when the tap fails`
- `feat(ai): record reranking spans`
- `chore: require illuminate/queue`

## Pull requests

- Put the Linear ticket first in the title: `[HEIM-XX] <type>(<scope>): <description>`. Without a ticket, use the conventional title alone.
- In the description, say why the change is needed and how to test it.
- Do not mention Claude in the description.

## Releases

Run the `release` workflow in GitHub Actions and select `patch`, `minor` or `major`.
