# Manual Adjustments to an Earning Line

Proof of concept for the Alcor OS payroll platform: an event-sourced domain model
for a payroll earning line that the system calculates automatically and that a
payroll specialist may correct manually, permanently and traceably.

> **Status:** work in progress — this README is finalised in the last phase.

## Table of contents

1. [What this is](#what-this-is)
2. [How to run](#how-to-run)
3. [Domain model](#domain-model)
4. [Why event sourcing](#why-event-sourcing)
5. [How each business rule is enforced](#how-each-business-rule-is-enforced)
6. [Assumptions](#assumptions)
7. [Trade-offs and what I would do next](#trade-offs-and-what-i-would-do-next)
8. [How AI was used](#how-ai-was-used)

## What this is

_TBD_

## How to run

Requires PHP 8.5 and Composer.

```bash
composer install
composer check     # coding standards + PHPStan (level max) + PHPUnit
composer scenario  # prints the reference scenario step by step
```

Without a local PHP 8.5:

```bash
docker build -t alcor .
docker run --rm alcor composer check
docker run --rm alcor composer scenario
```

## Domain model

_TBD_

## Why event sourcing

_TBD_

## How each business rule is enforced

_TBD_

## Assumptions

_Maintained throughout the implementation._

## Trade-offs and what I would do next

_TBD_

## How AI was used

_TBD_
