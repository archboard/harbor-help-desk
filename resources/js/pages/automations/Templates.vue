<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { triggerIcon } from '@/components/automations/triggerIcons';
import AppButton from '@/components/AppButton.vue';
import { create, index } from '@/routes/automations';

defineOptions({ layout: { title: trans('New automation') } });

const { templates } = defineProps<{
  templates: App.Data.Automations.AutomationTemplateData[];
}>();

/** The templates under their category, in the order the categories first appear. */
const categories = computed(() =>
  [...new Set(templates.map((template) => template.category))].map(
    (category) => ({
      category,
      templates: templates.filter((template) => template.category === category),
    }),
  ),
);
</script>

<template>
  <Head :title="$t('New automation')" />

  <div class="space-y-8">
    <div
      class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"
    >
      <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
          {{ $t('Start from scratch') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{
            $t('Build an automation step by step, starting with an empty flow.')
          }}
        </p>
      </div>
      <div class="flex gap-2">
        <AppButton :component="Link" :href="index().url" color="white">
          {{ $t('Back') }}
        </AppButton>
        <AppButton :component="Link" :href="create().url">
          {{ $t('Start from scratch') }}
        </AppButton>
      </div>
    </div>

    <section
      v-for="group in categories"
      :key="group.category"
      class="space-y-3"
    >
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">
        {{ group.category }}
      </h2>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="template in group.templates"
          :key="template.key"
          :href="create({ query: { template: template.key } }).url"
          class="flex gap-3 rounded-xl border border-gray-200 bg-white p-4 hover:border-primary-500 focus:ring-2 focus:ring-primary-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-500"
        >
          <component
            :is="triggerIcon(template.trigger_type)"
            class="mt-0.5 size-5 shrink-0 text-primary-600 dark:text-primary-400"
            aria-hidden="true"
          />
          <span class="min-w-0">
            <span
              class="block text-sm font-semibold text-gray-900 dark:text-white"
            >
              {{ template.name }}
            </span>
            <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">
              {{ template.description }}
            </span>
          </span>
        </Link>
      </div>
    </section>
  </div>
</template>
