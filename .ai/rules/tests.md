---
paths:
  - 'tests/**'
---

# Tests

## Test scoped table content with assertCanSeeTableRecords, not raw assertSee/assertDontSee
Asserting a Filament table excludes a record via $response->assertDontSee($record->someField) on the raw page HTML is fragile — it was observed to pass in isolation but fail only when run alongside enough other tests in the same suite (full-page HTML includes more than the table, e.g. global search payloads). Use Livewire::test(ListXxx::class)->assertCanSeeTableRecords([...])->assertCanNotSeeTableRecords([...]) instead — it checks the table's actual record set, not page text.

## Filament relation manager tables render lazily — test them with Livewire::test(), not assertSee
A resource's initial page HTML (plain $this->get()) does not contain a relation manager's table rows or a modal action's form — those hydrate via a follow-up Livewire request. Assert relation manager content with Livewire::test(RelationManagerClass::class, ['ownerRecord' => ..., 'pageClass' => EditXxx::class])->assertCanSeeTableRecords([...]) / ->mountTableAction('edit', $record). Plain HTTP assertSee only proves the outer resource page itself loads.

## Don't chain several expectsOutputToContain() calls — assert Artisan::output() instead
expectsOutputToContain() is matched against each individual OutputStyle::doWrite() call, not the command's whole buffered output (Illuminate\Testing\PendingCommand::mockConsoleOutput). Mockery satisfies only the FIRST registered expectation that matches a given write, so when two expected substrings live in the same write — anything rendered by $this->table(), or one line containing both — the later expectation fails with a misleading "Output does not contain X" even though X is plainly in the output. Hit this on imports:process (a table row holding both "0/351" and "NOT FINISHED"). Use Artisan::call(...) then expect(Artisan::output())->toContain(...)->toContain(...), which reads the full buffer and reports the real text on failure.
