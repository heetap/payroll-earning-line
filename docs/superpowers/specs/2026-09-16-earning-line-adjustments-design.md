# Manual Adjustments to an Earning Line — Design

**Date:** 2026-09-16
**Status:** approved
**Requirements source:** `docs/docs.md` (agent instruction file) and the Alcor
coding-test brief. Both are untracked inputs; this document is the design of
record.

## 1. Problem

A payroll earning line is calculated automatically by the system. A payroll
specialist may correct it with a signed amount and a mandatory comment. Six
rules govern the line:

1. Corrections are append-only — never edited, never deleted.
2. A mistake is fixed by adding a compensating correction, not by undoing one.
3. Once a line carries at least one correction, system recalculation must never
   change its value again — permanently.
4. A line may carry many corrections over time.
5. The current value must be available at any time.
6. The full audit history that produced the current value must be available at
   any time.

The reference scenario in `docs/docs.md` §1 is the acceptance criterion and is
reproduced there verbatim; it is not restated here so the two cannot drift.

## 2. Why event sourcing

The requirement *"no correction may ever be edited or deleted"* is the
definition of an append-only event stream. Modelling it as one makes the rule
structural rather than declarative: there is no code path that could mutate a
past correction, because past corrections are not stored as mutable rows.

Rule 3 — permanent immunity to recalculation — is the second reason. A CRUD
model would store a `system_value` column that recalculation overwrites, and
immunity would depend on remembering to check a flag at every write site. As an
event stream, the frozen value is simply the latest system value in effect when
the first `ManualAdjustmentAdded` is recorded — carried by whichever of
`EarningLineCalculated` or `EarningLineRecalculated` came last, so a line
adjusted without ever being recalculated freezes at its originally calculated
value. Later recalculations are recorded as `SystemRecalculationIgnored` and
change nothing.

A plain OO model would also satisfy the brief and would be a defensible answer.
The README will say so explicitly and give the trade-off: event sourcing costs
a projection and a store, and buys an audit trail that cannot be forged.

## 3. Layering

```
Infrastructure  ──▶  Application  ──▶  Domain  ──▶  (PHP only)
```

Dependencies point inward only. Ports live inward, adapters outward.

**Deviation from `docs/docs.md` §5 as originally written, approved:** the
`EventStore` port and `ConcurrencyConflict` live in `Application/Port/`, not in
`Infrastructure/Persistence/`. The read side folds the raw event stream without
loading the aggregate — that is the point of CQRS — so the query handler needs
the store. Had the port stayed in `Infrastructure`, Application would depend on
Infrastructure and the stated rule would be violated by the very first query
handler. `docs/docs.md` §2/§5 have been updated to match.

`LayerDependencyTest` enforces both arrows by scanning `use` statements:
`src/Domain` may not reference `Application` or `Infrastructure`;
`src/Application` may not reference `Infrastructure`.

## 4. Domain

### 4.1 Shared

| Type | Shape |
|---|---|
| `Currency` | `enum: string`, ISO 4217 — `USD`, `EUR`, `GBP`. `minorUnits(): int` returns 2 for all three. No symbols: formatting is infrastructure. |
| `Money` | `final readonly`, `int $minor` + `Currency $currency`. Private constructor; named constructors `ofMinor`, `zero`, `fromDecimal`. |
| `DomainEvent` | `interface { public DateTimeImmutable $occurredAt { get; } }` — a PHP 8.4+ interface property, satisfied directly by a promoted `readonly` property. |
| `DomainException` | `interface extends \Throwable`. Concrete exceptions extend the fitting SPL class *and* implement this, so a boundary can `catch (DomainException)` without losing SPL semantics. |

Exceptions sit next to what raises them: `Domain/Shared/Exception/` holds
`InvalidMoneyAmount` and `CurrencyMismatch`; `Domain/EarningLine/Exception/`
holds `InvalidComment`, `InvalidEarningLineId`, `InvalidSpecialistId`,
`InvalidAdjustmentNumber`, `ZeroAdjustmentNotAllowed`, `UnknownAdjustment`,
`EarningLineNotFound` and `EarningLineAlreadyExists` (the last two belong to the
repository port's contract).

`Money` rules:

- **No float anywhere**, including parsing and tests.
- `fromDecimal(string, Currency)` accepts an optional leading `+`/`-`, at least
  one integer digit, and at most `Currency::minorUnits()` decimals. Everything
  else is rejected: `1.005`, `abc`, `''`, `' 1.00'`, `1.`, `.5`, `1,00`, `1e2`.
- Scale comes from `Currency::minorUnits()`; the literals `2` and `100` do not
  appear in `Money`.
- Parsing rejects values that would not survive the cast to `int`, compared as
  digit strings against `PHP_INT_MAX`. A silently truncated money amount is a
  correctness bug, not an edge case.
- `add()` and `negate()` are `#[\NoDiscard]`. `add()` on a different currency
  throws `CurrencyMismatch`. `isZero()` and `equals()` complete the surface;
  `equals()` across currencies is `false`, not an error.
- Arithmetic guards overflow on the same principle as parsing: `add()` throws
  `InvalidMoneyAmount` when the sum would leave the `int` range, and `negate()`
  throws it for `PHP_INT_MIN`, whose negation is not representable. Parsing
  already refuses to build such values, so these guard against sums of
  individually valid amounts — a wrapped total is a correctness bug, not an
  edge case.
- `$minor` and `$currency` are public readonly so `MoneyFormatter` can read them
  without accessor noise. Formatting lives outside the domain.

### 4.2 EarningLine

Value objects: `EarningLineId` (RFC 4122 UUID, validated by pattern, normalised
to lowercase, **no `generate()`** — identity is supplied by the caller),
`SpecialistId` (opaque, trimmed, non-empty, ≤100 chars), `Comment` (trimmed,
non-empty, ≤500 chars), `AdjustmentNumber` (positive int, `equals()`).

**There is no `Adjustment` class in the domain.** The aggregate keeps decision
state only — the minimum required to enforce the invariants:

| Field | Why the invariants need it |
|---|---|
| `id` | identity |
| `currency` | rejects cross-currency adjustments; expresses "one currency per line, forever" independently of the value |
| `systemValue` | the value recalculation writes, and that freezes on first adjustment |
| `currentValue` | answers rule 5 without replaying |
| `status` | `LineStatus::SystemCalculated \| ManuallyAdjusted` — gates rule 3 |
| `lastAdjustmentNumber` | issues the next number, and bounds `$compensates` |
| `version` | optimistic concurrency |

Adjustment numbers are sequential from 1, so the set of issued numbers *is*
`1..lastAdjustmentNumber`. Storing the set as well would be redundant state:
`$compensates` is valid exactly when `1 <= n <= lastAdjustmentNumber`.

Comments, authors and timestamps are facts of the past. They live in the events
and on the read side, never in the write model. This is what makes rule 1
structural: there is no in-memory object holding a comment that code could
reassign.

Public behaviour:

```php
public static function calculate(EarningLineId $id, Money $systemValue, DateTimeImmutable $at): self
public function recalculate(Money $newSystemValue, DateTimeImmutable $at): void
public function addAdjustment(
    Money $amount,
    Comment $comment,
    SpecialistId $by,
    DateTimeImmutable $at,
    ?AdjustmentNumber $compensates = null,
): AdjustmentNumber
public function currentValue(): Money
public function version(): int
#[\NoDiscard] public function pullRecordedEvents(): array   // list<DomainEvent>
public static function reconstitute(EarningLineId $id, iterable $events): self
```

No setters, no `update*`, no `remove*`, no `delete*`.

### 4.3 Rules, mapped

| Rule | Enforced by | Result |
|---|---|---|
| Recalculation in a different currency | `recalculate()` compares `$newSystemValue->currency` against the line's `currency`, **first, in both states** | `CurrencyMismatch`; nothing recorded |
| Recalculation before any adjustment | `recalculate()` with `status === SystemCalculated` | records `EarningLineRecalculated` |
| Recalculation with an unchanged value, before any adjustment | `recalculate()` value comparison | records nothing |
| Recalculation after an adjustment | `recalculate()` with `status === ManuallyAdjusted` | records `SystemRecalculationIgnored($attemptedValue)`; state unchanged. Auditable drift, not an exception |
| Mandatory comment | `Comment` constructor | `InvalidComment` |
| Zero adjustment | `addAdjustment()` | `ZeroAdjustmentNotAllowed` |
| Cross-currency adjustment | `addAdjustment()` explicit currency check | `CurrencyMismatch` |
| Unknown compensation target | `addAdjustment()` bounds-checks against `lastAdjustmentNumber` | `UnknownAdjustment` |
| Freeze on first adjustment | `applyAdjustmentAdded()` flips `status`; no transition back exists | permanent |
| Calculating a line that already exists | `CalculateEarningLineHandler` checks the repository first | `EarningLineAlreadyExists` |

**Currency is checked first, and explicitly.** Both `recalculate()` and
`addAdjustment()` compare the incoming `Currency` against the line's own before
anything else happens, in every status — a cross-currency recalculation of an
already-frozen line is still a `CurrencyMismatch`, not a silently ignored
recalculation. `Money::equals()` returns `false` across currencies, and that
`false` must never be pressed into service as the currency check: it conflates
"a different amount" with "a different currency", and would let a cross-currency
recalculation be recorded as an ordinary value change.

**The unchanged-value short-circuit applies only while the line is still
`SystemCalculated`.** Once it is `ManuallyAdjusted`, every recalculation attempt
is recorded as ignored regardless of the attempted value: the audit fact is that
the system tried and was refused, which is true even when the attempted value
happens to match. `SystemRecalculationIgnored` is an appended event and
therefore advances the stream version, even though it changes no state.

### 4.4 Events

`final readonly`, past tense, carrying value objects (never entities, never the
aggregate) plus `occurredAt`:

- `EarningLineCalculated(EarningLineId, Money $systemValue, DateTimeImmutable)`
- `EarningLineRecalculated(EarningLineId, Money $newSystemValue, DateTimeImmutable)`
- `SystemRecalculationIgnored(EarningLineId, Money $attemptedValue, DateTimeImmutable)`
- `ManualAdjustmentAdded(EarningLineId, AdjustmentNumber, Money $amount, Comment, SpecialistId $by, ?AdjustmentNumber $compensates, DateTimeImmutable)`

### 4.5 Event-sourcing mechanics

`record()` = `apply()` + append to pending. `apply()` is the only place state
changes, and `reconstitute()` drives the same methods, so a replayed aggregate
cannot diverge from a live one.

Dispatch is `match (true)` over `instanceof`, and PHPStan at level max requires
a `default` arm here: it cannot prove a subject of type `true` is exhausted, and
reports "Match expression does not handle remaining value: true" without one.
The `default` arm throws `LogicException` and is covered by a test that passes
an anonymous `DomainEvent` implementation — `@codeCoverageIgnore` is banned, so
the arm must be genuinely reachable, not merely present to satisfy the analyser.

`version` is the version the instance was **loaded at**. `reconstitute()`
increments it; `record()` does not. The repository therefore appends with
`append($streamId, $line->version(), $events)` and needs no arithmetic. The
cost: after `save()` the value is stale. Every handler loads the aggregate
fresh, so this never bites; it will be documented in the README.

`reconstitute()` assumes a well-formed stream. The repository raises
`EarningLineNotFound` on an empty stream before calling it, so the aggregate
carries no defensive code for a case the adapter already excludes.

## 5. Application

### 5.1 Commands and handlers

Commands are `final readonly` DTOs carrying **primitives** — they are messages
that would arrive serialized over a transport:

```php
CalculateEarningLine(string $lineId, string $amount, string $currency)
RecalculateEarningLine(string $lineId, string $amount, string $currency)
AddManualAdjustment(string $lineId, string $amount, string $currency,
                    string $comment, string $specialistId, ?int $compensates = null)
```

Handlers build the value objects, so invalid input fails with a domain
exception at the edge of the application, and validation itself stays in the
domain where it belongs. No mapper class: the conversion is three lines in the
handler that needs it.

Every handler is `final`, invokable, and returns **`void`** (CQS). Constructor
dependencies: `EarningLineRepository` and `Psr\Clock\ClockInterface`. The
aggregate still returns the assigned `AdjustmentNumber`; the handler discards
it, and the CLI reads numbers back through `GetEarningLineAudit`.

`CalculateEarningLineHandler` asks the repository whether the line already
exists and raises `EarningLineAlreadyExists` if it does. Letting the append
collide and surface a `ConcurrencyConflict` would report a race that did not
happen: `ConcurrencyConflict` is reserved for genuine version races between
concurrent writers, and conflating the two would make the store's contract
unreadable. The domain port therefore carries `exists(EarningLineId): bool`
alongside `get()` and `save()`.

### 5.2 Port

```php
interface EventStore
{
    /** @param list<DomainEvent> $events */
    public function append(string $streamId, int $expectedVersion, array $events): void;
    /** @return list<DomainEvent> */
    public function load(string $streamId): array;
}
```

`append()` throws `ConcurrencyConflict` when the stored version differs from
`$expectedVersion`.

### 5.3 Read side

`GetEarningLineAudit(string $lineId)` → `GetEarningLineAuditHandler`, which
loads the raw stream from `EventStore` and folds it with
`AuditHistoryProjection`. It does not touch the aggregate.

The read model owns its own shapes and never reuses write-model classes:

```php
final readonly class AdjustmentEntry {
    public function __construct(
        public int $number, public Money $amount, public string $comment,
        public string $by, public DateTimeImmutable $at, public ?int $compensates,
    ) {}
}
final readonly class IgnoredRecalculation {
    public function __construct(public Money $attemptedValue, public DateTimeImmutable $at) {}
}
final readonly class AuditHistoryView {
    /** @param list<AdjustmentEntry> $adjustments
     *  @param list<IgnoredRecalculation> $ignoredRecalculations */
    public function __construct(
        public string $lineId,
        public Money $systemValue,          // latest system value ever recorded
        public ?Money $frozenSystemValue,   // null until the first adjustment
        public array $adjustments, public array $ignoredRecalculations,
        public Money $currentValue,
    ) {}
}
```

`frozenSystemValue` is `null` while the line has no manual adjustment, because
nothing is frozen yet — a view that labelled the live value "frozen" would be
lying about the one rule the audit exists to evidence. From the first adjustment
onward it is set and never changes, and it equals `systemValue`, since every
later recalculation is ignored. Keeping both fields makes the view a total
function over the line's whole life rather than one that only reads correctly
after step 3.

`currentValue` is accumulated **inside** the fold, not recomputed afterwards —
the projection is one pass over the stream. The pipe operator `|>` is used only
if a stage genuinely reads better left-to-right than the fold does; it is not
forced into the code.

## 6. Infrastructure

| Component | Notes |
|---|---|
| `InMemoryEventStore` | `array<string, list<DomainEvent>>`. Appends atomically, compares `count()` against `$expectedVersion`. The only implementation. |
| `EventSourcedEarningLineRepository` | Implements the domain port. `get()` loads, raises `EarningLineNotFound` on an empty stream, calls `reconstitute()`. `save()` pulls pending events and appends at `$line->version()`. |
| `SystemClock` | `ClockInterface` over `new DateTimeImmutable('now')`. The only place that reads the wall clock. |
| `UuidV4` | `random_bytes(16)` with version/variant bits set. Used **only** by `bin/scenario.php` — never by the domain. |
| `MoneyFormatter` | Currency symbols live here. Renders from `$minor` and `Currency::minorUnits()`. |
| `AuditTableRenderer` | Renders `AuditHistoryView` as the audit table. |
| `bin/scenario.php` | Composition root. Wires the store, repository, clock and handlers by hand and replays the reference scenario step by step. |

The README will describe how a SQL store would guarantee append-only: a unique
constraint on `(stream_id, version)`, and no `UPDATE`/`DELETE` privileges on the
events table for the application role.

## 7. Testing

TDD throughout: failing test, minimal code, refactor.

| Suite | Covers |
|---|---|
| `Unit/Domain` | `Money` (parsing edge cases, arithmetic, mismatch), `Comment`, `EarningLineId`, `SpecialistId`, `AdjustmentNumber`, `EarningLine` |
| `Unit/Application` | handlers, `AuditHistoryProjection` |
| `Integration` | `InMemoryEventStore` + repository, including `ConcurrencyConflict` |
| `Acceptance` | `ReferenceScenarioTest` — the 8 steps through the application layer, asserting the value after every step and the final audit view exactly |
| `Architecture` | `LayerDependencyTest` — both arrows |
| `Support` | `FrozenClock`, `EarningLineScenario`, `TestIds` (fixed UUID constants) |

Aggregate tests are written **given** (past events) / **when** (behaviour) /
**then** (recorded events or exception) and assert on events, never on private
state. Test names state business rules, e.g.
`test_recalculation_is_ignored_once_line_has_manual_adjustment()`.

No mocks of domain objects — real value objects and in-memory fakes. No floats
in tests: money is built with `Money::fromDecimal('1050.00', Currency::USD)`.
`ReferenceScenarioTest` expectations are never edited to make code pass.

Also covered: reconstitution from events yields identical state; concurrency
conflict; empty and whitespace-only comments; zero amount; unknown compensated
number.

Specifically required by the rules above:

- `recalculate()` in a foreign currency raises `CurrencyMismatch` and records
  nothing — asserted in **both** statuses, `SystemCalculated` and
  `ManuallyAdjusted`.
- The projection is asserted **before** the first adjustment
  (`frozenSystemValue === null`, `systemValue` live) and **after** it
  (`frozenSystemValue` set, equal to `systemValue`, unmoved by later ignored
  recalculations).
- `CalculateEarningLine` on an existing line raises `EarningLineAlreadyExists`,
  while a genuine version race raises `ConcurrencyConflict` — two separate
  tests, so the two failures cannot be confused.
- `Money::add()` overflow and `Money::negate()` of `PHP_INT_MIN` raise
  `InvalidMoneyAmount`.

## 8. Assumptions

Maintained here during implementation, then published in `README.md`.

1. Amounts are signed deltas. Money is stored in integer minor units. A line
   has one currency for its lifetime.
2. The step-3 comment says "reversing deduction" while the amount is negative.
   The amount is authoritative; the comment is free text.
3. A line must be system-calculated before it can be adjusted — `calculate()`
   is the only constructor.
4. Recalculation with an unchanged value records nothing.
5. Ignored recalculations are recorded for drift visibility but are not part of
   the adjustment history.
6. Compensation is a link, not an enforced equal-and-opposite amount.
7. A line value may become negative; no business rule forbids it.
8. Every adjustment records who made it and when.
9. Aggregate state is minimal by design; audit detail lives in the events and
   the read model.
10. `EarningLineId` is a client-generated RFC 4122 UUID supplied in the command;
    the domain never generates identity.
11. `SpecialistId` is an opaque identifier from an external identity context;
    no format is assumed.
12. Supported currencies are USD/EUR/GBP. Precision is asked of the currency so
    0- or 3-decimal currencies can be added later.
13. The brief gives no attempted value for the ignored recalculation at step 4 —
    only that the line's value must not move. The scenario and the acceptance
    test use `1075.00`, chosen to differ from the frozen `1050.00` so that the
    test would fail if the value were silently adopted.

## 9. Out of scope

A SQL event store, snapshots, payroll-period close, drift notifications to
specialists, and idempotency keys for commands. The README lists these as next
steps.

Event **serialization** is out of scope with an in-memory store. The README will
note the consequence: once events are persisted, an infrastructure serializer
maps value objects to scalars, and deserialization of historical events must not
re-apply today's validation rules — past facts stay valid even when rules
change. That needs a dedicated reconstruction path or upcasters.
