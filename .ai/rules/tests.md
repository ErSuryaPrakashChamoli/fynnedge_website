---
paths:
  - 'tests/**'
---

# Tests

## Test scoped table content with assertCanSeeTableRecords, not raw assertSee/assertDontSee
Asserting a Filament table excludes a record via $response->assertDontSee($record->someField) on the raw page HTML is fragile — it was observed to pass in isolation but fail only when run alongside enough other tests in the same suite (full-page HTML includes more than the table, e.g. global search payloads). Use Livewire::test(ListXxx::class)->assertCanSeeTableRecords([...])->assertCanNotSeeTableRecords([...]) instead — it checks the table's actual record set, not page text.
