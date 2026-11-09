import { trans } from 'laravel-vue-i18n';
import { defineComponent, h } from 'vue';

/**
 * Translates `keypath` and swaps each `:name` placeholder for the slot of the same name, so
 * placeholders can hold markup. Registered globally as `<i18n-t>`.
 */
export default defineComponent({
  name: 'I18nT',
  props: {
    keypath: { type: String, required: true },
    tag: { type: String, default: 'span' },
  },
  setup(props, { slots }) {
    return () =>
      h(
        props.tag,
        trans(props.keypath)
          .split(/:(\w+)/)
          .map((part, index) =>
            // Odd indexes are the placeholder names captured by the split.
            index % 2 === 0 ? part : (slots[part]?.() ?? `:${part}`),
          ),
      );
  },
});
