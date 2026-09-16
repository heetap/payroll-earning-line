# AGENTS.md

Instructions for AI coding agents working in this repository.

The requirements this project implements live in `docs/superpowers/specs/` (the design of
record) and `docs/superpowers/plans/` (the task-by-task plan). Read the spec before any
task that touches the domain.

There is also a local, **untracked** brief at `docs/docs.md`. It is the repository owner's
own instruction file and is deliberately kept out of version control, so it will not exist
in a fresh clone. When it is present it outranks this file; when this file and the spec
conflict, the spec wins. **Point out the conflict rather than silently picking a side.**

---

## 1. Commands

```bash
composer install     # on the host only — see below
composer test        # PHPUnit
composer analyse     # PHPStan, level max
composer cs          # PHP-CS-Fixer dry run
composer cs:fix      # PHP-CS-Fixer apply
composer check       # cs + analyse + test — must be green before every commit
composer scenario    # print the reference scenario and its audit table
```

**Run everything inside the container.** `vendor/` is installed on the host and
bind-mounted in, so dependency resolution happens once, on the host, and every tool then
runs against PHP 8.5 regardless of what the host has installed:

```bash
docker compose run --rm php composer check
docker compose run --rm php vendor/bin/phpunit --testsuite unit
```

The only commands that belong on the host are `composer install`, `composer require` and
`composer update`, because they write `composer.lock`.

For a reviewer with no PHP 8.5 and no interest in compose:

```bash
docker build -t alcor . && docker run --rm alcor composer check
```

---

## 2. How to work

1. **Plan before code.** For any non-trivial task, propose files, types and tests first.
   Wait for approval when the task spans more than one commit.
2. **TDD.** Failing test → minimal code → refactor. Never write production code without a
   test that needed it.
3. **Do not build ahead.** Write the branch when a test can exercise it, not before. An
   unreachable branch is untested code pretending to be a rule.
4. **Stop and ask** when requirements are ambiguous, when two instructions conflict, or
   when a decision is hard to reverse. Give a recommendation, not a survey of options.
5. **Never silently lower the bar.** Do not downgrade syntax, tools or the PHP version,
   add a PHPStan baseline, `@phpstan-ignore`, `@phpstan-ignore-next-line` or
   `@codeCoverageIgnore`, or edit test expectations to make code pass. If blocked, report
   what failed and why.
6. **No new dependencies** without asking. The runtime dependency list is `psr/clock` and
   nothing else. Prefer PHP core and PSR interfaces.
7. **Small commits**, each green, Conventional Commits: `feat(scope): …`, `fix: …`,
   `test: …`, `refactor: …`, `docs: …`, `chore: …`. Imperative, subject ≤ 72 chars, body
   explains *why* when non-obvious.
8. **Record assumptions** as you make them, in the spec's Assumptions section and in the
   README. An undocumented assumption is a hidden bug.
9. After finishing, summarize: what changed, what was decided, what is left.

---

## 3. PHP baseline

- PHP **8.5**, `declare(strict_types=1);` in every PHP file, tests included.
- PSR-4 autoloading: `Alcor\Payroll\` → `src/`, `Alcor\Payroll\Tests\` → `tests/`.
  PER-CS formatting enforced by PHP-CS-Fixer.
- `final` by default. Remove `final` only with a concrete extension need.
- `readonly` classes for value objects, events, commands, queries, DTOs, views.
- Full native types on every parameter, return and property. `mixed` requires a
  justification in review.
- Docblocks only for what native types cannot express (`list<Foo>`,
  `array<string, Bar>`, `non-empty-string`, `positive-int`, templates) or for *why*. Never
  restate native types, never describe *what* the code does.
- Enums instead of string/int constants for closed sets. `match` instead of `switch`.
- Named constructors (`fromDecimal`, `ofMinor`, `calculate`) over public constructors with
  parsing logic; private constructor when invariants depend on it.
- Throw specific exceptions; never return `false`/`null` to signal failure.

### `match` and the `default` arm

The rule depends on what you are matching, and getting it wrong fails static analysis:

- **`match ($enum)` over an enum's cases** — omit `default`. The analyser proves
  exhaustiveness, and a `default` would be unreachable code. See `Currency::minorUnits()`.
- **`match (true)` over `instanceof` checks** — a `default` arm is **required**. PHPStan at
  level max reports *"Match expression does not handle remaining value: true"* without one,
  because it cannot prove a subject of type `true` is exhausted. See `EarningLine::apply()`,
  where `default` throws `LogicException` and a test covers that arm by passing an anonymous
  `DomainEvent` implementation.

Never satisfy the analyser by adding a `default` you cannot test. If a `default` is
unreachable, restructure until it is reachable or until the subject is genuinely exhaustive.

---

## 4. PHP 8.5 feature policy

Use a feature when it makes code clearer or safer. Never to demonstrate it.

### Use freely

| Feature | Rule |
|---|---|
| `#[\NoDiscard]` | On every method whose return value must be used: immutable withers, value-object arithmetic, `pullRecordedEvents()`. See the trap below before writing a bare call. |
| `#[\Override]` | On every method and property that implements an interface member or overrides a parent. |
| `array_first()` / `array_last()` | Instead of `$a[array_key_first($a)] ?? null` or `reset()`/`end()`. |
| Interface properties | `public DateTimeImmutable $occurredAt { get; }` on `DomainEvent`. A promoted `readonly` property satisfies it; PHPStan level max accepts this. |
| `new Foo()->bar()` | No wrapping parentheses. PHP-CS-Fixer rewrites the old spelling. |
| Fatal error backtraces | Nothing to do — do not suppress them in config. |

### Use carefully

| Feature | Rule |
|---|---|
| Pipe `\|>` | Only for short linear transformations (≤ 3–4 stages) where every stage takes one argument. Arrow functions inside a pipe **must be parenthesized**: `\|> (static fn (string $s): string => …)`. Never inside an aggregate, and never where a plain variable or loop reads better. No by-reference callables. |
| `clone($obj, [...])` | For withers in class scope. It cannot modify `readonly` properties from outside class scope unless they are `public(set)`. For small value objects prefer `new self(...)`. |
| Closures in constant expressions | Allowed in attributes; must be `static`, cannot `use` outer variables. Keep them trivial. |
| `Uri\Rfc3986\Uri`, `Uri\WhatWg\Url` | Instead of `parse_url()` whenever URLs come from untrusted input. |
| `filter_var(..., FILTER_THROW_ON_FAILURE)` | Only at input boundaries; domain validation stays in value objects. |

### Also available (PHP 8.4) — same policy

- Property hooks: fine for simple derived or validated properties on DTOs and views.
  **Not** in aggregates or entities, where behaviour must be explicit methods.
- Asymmetric visibility (`public private(set)`): prefer it over getter boilerplate on
  mutable internals; prefer `readonly` for immutable types.
- `array_find`, `array_find_key`, `array_any`, `array_all`.
- `#[\Deprecated]` on userland code instead of `@deprecated` docblocks.
- Lazy objects: infrastructure only, never in the domain.

### Forbidden

- Non-canonical casts: `(integer)`, `(boolean)`, `(double)`, `(binary)` → use `(int)`,
  `(bool)`, `(float)`, `(string)`.
- Backtick shell execution.
- Constant redeclaration, `define()` for application config.
- `__sleep`/`__wakeup` → use `__serialize`/`__unserialize` if serialization is needed.
- `extract()`, `compact()`, variable variables, `@` suppression, `eval()`.
- `global`, mutable `static` state, singletons.

---

## 5. Correctness traps

Every trap below has bitten this repository at least once. Check them explicitly.

- **Regex anchors.** Use `\A` and `\z`, never `^` and `$`. In PCRE, `$` matches **before a
  final newline**, so `/^\d+\.\d\d$/` accepts `"1.00\n"`. This shipped: `Money::fromDecimal`
  refused `" 1.00"` while quietly accepting `"1.00\n"` until the anchors were fixed.
- **`#[\NoDiscard]` and `failOnWarning`.** Discarding a `#[\NoDiscard]` return raises an
  `E_WARNING`, and `phpunit.xml` sets `failOnWarning="true"`, so the test fails on the
  warning rather than on the behaviour under test. A deliberate discard must be written
  `(void) $money->add(…)`. PHP flags the discard **syntactically**, so this applies even
  inside an `expectException()` test where the call never returns at all.
- **Money and precision.** Never `float`. Integer minor units, and precision comes from the
  currency (`Currency::minorUnits()`), never from a literal `2` or `100`.
- **Integer overflow.** `int + int` that overflows becomes a `float`, which turns into an
  obscure `TypeError` against a typed property. Guard arithmetic that can overflow — check
  **before** adding rather than inspecting the result — and include `-PHP_INT_MIN`, whose
  negation is not representable.
- **Numeric strings and `(int)`.** Casting an oversized numeric string silently yields
  `PHP_INT_MAX`. Compare digit strings against `(string) PHP_INT_MAX` instead.
- **Cross-type equality.** An `equals()` that returns `false` across currencies or units
  must never double as an invariant check: it conflates "a different amount" with "a
  different currency". Check the invariant explicitly **first**, then compare.
- **Time.** Never `new DateTimeImmutable()` or `time()` outside the clock adapter. Inject
  `Psr\Clock\ClockInterface`. Always `DateTimeImmutable`, never `DateTime`.
- **Randomness and identity.** `random_bytes()`/`random_int()` only, in infrastructure. The
  domain never generates identity; IDs arrive with commands.
- **Existence vs concurrency.** "Already exists" and "version conflict" are different
  errors with different retry semantics. Do not collapse them into one exception.
- **Array shapes.** Use `list<T>` when order matters and keys are sequential.
  `array_filter()` preserves keys — wrap with `array_values()` where a list is promised.
- **Parameters inserted mid-signature.** When adding a parameter to an existing
  constructor, check every construction site position by position. Arguments are
  positional, and a mis-ordered pair of compatible types passes silently.

---

## 6. Architecture

Hexagonal layering, dependencies pointing inward:

```
Infrastructure  ──▶  Application  ──▶  Domain  ──▶  PHP core only
```

- **Domain:** aggregates, value objects, domain events, domain exceptions, repository
  interfaces. No framework, no I/O, no clock, no randomness.
- **Application:** commands, queries, handlers, ports, read models.
- **Infrastructure:** adapters implementing ports, persistence, CLI, clock, ID generation,
  formatting, the composition root.
- **Ports live in the layer that uses them; adapters live outside.** In this repository the
  `EventStore` port sits in `Application/Port/`, not in `Infrastructure/Persistence/`,
  because the read side folds the raw event stream and Application must never reference
  Infrastructure. Its adapters live in `Infrastructure/Persistence/`.
- `tests/Architecture/LayerDependencyTest` enforces **both** arrows by scanning source for
  cross-layer namespace references. It also asserts that the scan still finds a reference
  that *is* allowed, so a regex that stops matching cannot make the other checks pass
  vacuously.

### Modeling rules

- **Aggregates hold only decision state** — the minimum the invariants need. Audit and
  display data lives in events and read models. No setters, no `update*`, no `remove*`, no
  `delete*`, no public mutable properties.
- **Value objects** are valid by construction and immutable. Validation lives only there.
- **Domain events:** `final readonly`, past-tense names, carrying value objects and
  `occurredAt`, never entities or aggregates.
- **Event sourcing:** `record()` = `apply()` + append to pending. `apply*()` is the only
  place state changes, and `reconstitute()` drives the same `apply*()` methods, so a
  replayed aggregate cannot diverge from one that lived through its events.
- **`version` is the version the instance was loaded at.** `reconstitute()` increments it;
  `record()` does not. The repository therefore appends at exactly `$aggregate->version()`.
  The consequence is that the value is stale after `save()`; every handler reloads, so this
  never bites.
- **Historical events must stay loadable after validation rules change.** Deserialization
  must not re-run today's validation — past facts stay valid even when rules do not.
- **Commands are messages:** `final readonly`, **primitives only**, because they would
  arrive serialized. Handlers build the value objects, so invalid input raises a domain
  exception at the boundary while the rules stay in the domain.
- **CQS:** command handlers return `void`. Read through queries.
- **Read models own their shapes.** Never reuse a write-model class in a view.
- **Dependency injection** through constructors and interfaces. No service locator, no
  static facades in Domain or Application. No container: `bin/scenario.php` wires
  everything by hand and is the only composition root.

### Do not add without a concrete need

Generic base entities, event or command buses, middleware pipelines, the specification
pattern, mapper layers, abstract factories, traits for shared behaviour,
"Manager"/"Helper"/"Util" classes. Solve today's problem with the simplest correct design
and name things after the domain.

### On static methods

Named constructors are deliberate and stay: PHP has one constructor, and `Money::ofMinor`,
`Money::fromDecimal`, `EarningLine::calculate` and `EarningLine::reconstitute` express
intent a public constructor could not. Enum factories like `Currency::fromCode` have no
alternative at all.

A static method that formats one message for one caller is not a named constructor. Domain
exceptions are thrown directly with their message at the throw site; a static factory is
justified only when several call sites would otherwise duplicate the wording — as with
`CurrencyMismatch::between()` and `InvalidMoneyAmount::overflow()`.

---

## 7. Testing

- PHPUnit 12. `#[DataProvider]` and other attributes; no annotations. Data providers are
  `public static`.
- `phpunit.xml` sets `failOnRisky`, `failOnWarning`, `failOnNotice`, `failOnDeprecation`,
  `beStrictAboutOutputDuringTests`, `beStrictAboutChangesToGlobalState` and random
  execution order.
- Suites: `unit`, `integration`, `acceptance`, `architecture`.
- **Test names state a business rule**, in snake_case, using the `test_` method prefix:
  `test_recalculation_is_ignored_once_line_has_manual_adjustment()`. The prefix is still
  first-class in PHPUnit 12 and the suite runs green under `failOnDeprecation`.
- **Aggregates:** given (past events) / when (behaviour) / then (recorded events or
  exception). Assert on public behaviour and recorded events, never on private state and
  never through reflection.
- **No mocks of domain objects.** Real value objects and in-memory fakes. Mocks only at a
  true external boundary, and prefer a fake even there.
- **Deterministic:** `FrozenClock` and fixed UUIDs in `tests/Support`. No sleeps, no
  network, no wall clock.
- **No floats in tests.** Build money from strings or minor units.
- Every bug fix starts with a test that reproduces it.
- **Acceptance tests derived from the requirements are never edited to fit the code.**
  `ReferenceScenarioTest` is the contract with the business; if it fails, the code is wrong.

---

## 8. Tooling configuration

- **PHPStan 2.x** at `level: max`, with `phpstan/phpstan-strict-rules` and
  `phpstan/phpstan-phpunit`. No baseline, no ignores.
- **PHP-CS-Fixer** with `@PER-CS` and `@PHP8x5Migration`, plus `declare_strict_types`,
  `ordered_imports`, `no_unused_imports`, `global_namespace_import` for classes.
  `global_namespace_import` imports classes and attributes (`use DateTimeImmutable;`,
  `use NoDiscard;`) while leaving global **constants** backslash-qualified (`\PHP_INT_MAX`)
  — that asymmetry is the configuration working, not drift.
- **Verify every tool parses PHP 8.5** before adopting it — pipe, clone-with, `(void)`
  cast, interface properties. If a tool cannot, **stop and report**; never rewrite code to
  older syntax to please a tool.
- CI runs `composer check` and `composer scenario` on PHP 8.5 via `shivammathur/setup-php`.

### `checkUninitializedProperties` is deliberately off

Turning it on reports `EarningLine::$currency`, `$systemValue` and `$currentValue`, which
are typed, non-nullable and initialised by `applyCalculated()` rather than by the
constructor. That is not an accident — it is the event-sourcing shell pattern: the
aggregate is built empty and its state is established by replaying the first event, so
those properties cannot be constructor-assigned without abandoning `reconstitute()`.

The compensating control belongs at the adapter, and it is required: the repository must
raise `EarningLineNotFound` on an empty stream **before** calling `reconstitute()`, so no
caller can obtain an aggregate whose fields were never initialised. That guard is what makes
the flag's warning theoretical rather than real — remove it and the warning becomes true.

Making the properties nullable to satisfy the flag would be worse: every read would grow a
null check for a state that cannot occur.

---

## 9. Code style

- Names from the domain language; no abbreviations except universal ones (`id`, `url`).
- Methods short and single-purpose; early returns over nesting.
- One class per file; file name equals class name.
- Import every class with `use`; no leading-backslash FQCNs in code except global
  functions and constants where the fixer requires it.
- Comments explain *why* a decision was made or a constraint exists. Delete commented-out
  code.
- No dead code, no speculative parameters, no "for future use" abstractions. A named
  constructor called only by its own test is dead code.

---

## 10. Definition of done

- [ ] `composer check` green in the container and in CI.
- [ ] New behaviour covered by tests that failed before the change.
- [ ] No new ignores, baselines, suppressions or deprecated syntax.
- [ ] `LayerDependencyTest` still passes.
- [ ] Assumptions and trade-offs recorded in the spec and the README.
- [ ] Commit messages follow Conventional Commits and explain *why*.
- [ ] README updated if usage, decisions or assumptions changed.
