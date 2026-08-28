<x-layouts.app :title="$step->title" robots="noindex, nofollow">
    <section class="mx-auto max-w-5xl px-6 py-14 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[220px_1fr]">
            <aside class="hidden lg:block">
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">
                    {{ $session->loanProduct->name }}
                </p>
                <x-ui.stepper
                    class="mt-4"
                    :steps="$steps->pluck('title')->all()"
                    :current="$steps->search(fn ($s) => $s->id === $step->id)"
                />
            </aside>

            <div>
                <div class="mb-6 lg:hidden">
                    <p class="font-mono text-xs text-ink-faint">
                        Step {{ $progress['completed'] + 1 }} of {{ $progress['total'] }}
                    </p>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                        <div class="h-full rounded-full bg-accent transition-all" style="width: {{ $progress['percent'] }}%"></div>
                    </div>
                </div>

                <h1 class="text-balance font-display text-2xl font-semibold text-ink sm:text-3xl">{{ $step->title }}</h1>
                @if ($step->description)
                    <p class="mt-2 text-ink-muted">{{ $step->description }}</p>
                @endif

                <form
                    id="journey-step-form"
                    method="POST"
                    action="{{ route('journey.update', $session) }}"
                    class="mt-8 grid gap-5 sm:grid-cols-2"
                    x-data="journeyStep(
                        @js(collect($fields)->mapWithKeys(fn ($f) => [$f->key => old($f->key, $responses[$f->key] ?? null)])),
                        @js(collect($fields)->mapWithKeys(fn ($f) => [$f->key => $f->conditional_on])),
                    )"
                >
                    @csrf

                    @foreach ($fields as $field)
                        <x-journey.field :field="$field" :value="old($field->key, $responses[$field->key] ?? null)" />
                    @endforeach
                </form>

                <div class="mt-6 flex items-center justify-between">
                    @if ($progress['completed'] > 0)
                        <form method="POST" action="{{ route('journey.back', $session) }}">
                            @csrf
                            <x-ui.button type="submit" variant="ghost">Back</x-ui.button>
                        </form>
                    @else
                        <span></span>
                    @endif
                    <x-ui.button type="submit" form="journey-step-form">Continue</x-ui.button>
                </div>

                <p class="mt-8 text-xs text-ink-faint">
                    You can leave and come back to this page any time to continue where you left off.
                </p>
            </div>
        </div>
    </section>
</x-layouts.app>
