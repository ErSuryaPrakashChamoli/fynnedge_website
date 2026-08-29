/**
 * Livewire bundles and starts its own Alpine instance — importing and
 * starting a second one here (as this file used to) makes two Alpine
 * instances run at once. That breaks Livewire's own wire:model DOM
 * morphing unpredictably (e.g. a slider and its paired number input,
 * both wire:model-bound to the same property, stop reflecting each
 * other's changes) since Livewire's Alpine plugin hooks can end up
 * registered against the wrong instance. Registering through the
 * `alpine:init` event instead targets Livewire's own instance, which it
 * dispatches just before calling Alpine.start() itself.
 *
 * Drives client-side conditional field visibility for the customer journey engine.
 * `conditions` maps field key -> { field, operator, value } | null, mirroring
 * App\Modules\Journey\Models\Concerns\EvaluatesCondition on the server.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('journeyStep', (initialValues, conditions) => ({
        values: initialValues,

        isVisible(key) {
            const condition = conditions[key];

            if (!condition) {
                return true;
            }

            const actual = this.values[condition.field];

            if (condition.operator === '!=') {
                return actual != condition.value;
            }

            if (condition.operator === 'in') {
                return Array.isArray(condition.value) && condition.value.includes(actual);
            }

            return actual == condition.value;
        },
    }));
});
