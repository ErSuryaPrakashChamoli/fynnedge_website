import Alpine from 'alpinejs';

/**
 * Drives client-side conditional field visibility for the customer journey engine.
 * `conditions` maps field key -> { field, operator, value } | null, mirroring
 * App\Modules\Journey\Models\Concerns\EvaluatesCondition on the server.
 */
Alpine.data('journeyStep', (initialValues, conditions) => ({
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

window.Alpine = Alpine;
Alpine.start();
