<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '@/components/AppButton.vue';
import CardAction from '@/components/CardAction.vue';
import CardHeader from '@/components/CardHeader.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { InputField, SelectField } from '@/components/forms/fields';
import ConfirmationModal from '@/components/modals/ConfirmationModal.vue';
import { notify } from '@/lib/notifications';
import { destroy, update } from '@/routes/desks';

const { desk, slaPolicies } = defineProps<{
  desk: App.Data.Desks.DeskData;
  strategies: Record<App.Enums.AssignmentStrategy, string>;
  slaPolicies: App.Data.SlaPolicies.SlaPolicyData[];
  can: { update: boolean; archive: boolean };
}>();

defineOptions({ layout: { title: trans('Desk') } });

const form = useForm({
  name: desk.name,
  slug: desk.slug,
  assignment_strategy: desk.assignment_strategy,
  sla_policy_id: desk.sla_policy_id ?? '',
});

const policyOptions = computed(() => ({
  '': trans('No SLA'),
  ...Object.fromEntries(slaPolicies.map(({ id, name }) => [id, name])),
}));

const archiving = ref(false);
const archiveLoading = ref(false);

function archive(close: () => void): void {
  router.delete(destroy(desk.id).url, {
    onStart: () => (archiveLoading.value = true),
    onFinish: () => (archiveLoading.value = false),
    onError: (errors) => {
      close();
      notify({
        level: 'error',
        text: errors.desk ?? trans('Unable to archive.'),
      });
    },
  });
}
</script>

<template>
  <Head :title="desk.name" />

  <div class="space-y-6">
    <CardWrapper v-if="can.update">
      <form
        @submit.prevent="
          form
            .transform((data) => ({
              ...data,
              sla_policy_id: data.sla_policy_id || null,
            }))
            .submit(update(desk.id), { preserveScroll: true })
        "
      >
        <CardPadding>
          <CardHeader class="mb-4">{{ desk.name }}</CardHeader>
          <AppFieldset>
            <InputField
              v-model="form.name"
              :label="$t('Name')"
              :error="form.errors.name"
              required
            />
            <InputField
              v-model="form.slug"
              :label="$t('Slug')"
              :error="form.errors.slug"
              required
            />
            <SelectField
              v-model="form.assignment_strategy"
              :label="$t('Automatic assignment')"
              :options="strategies"
              hide-null
              :error="form.errors.assignment_strategy"
            />
            <SelectField
              v-model="form.sla_policy_id"
              :label="$t('SLA policy')"
              :options="policyOptions"
              :error="form.errors.sla_policy_id"
            />
          </AppFieldset>
        </CardPadding>
        <CardAction :loading="form.processing" />
      </form>
    </CardWrapper>
    <CardWrapper v-else>
      <CardPadding>
        <CardHeader>{{ desk.name }}</CardHeader>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ desk.slug }}
        </p>
      </CardPadding>
    </CardWrapper>

    <section
      v-if="can.archive"
      class="space-y-3 rounded-2xl border border-red-300 p-4 dark:border-red-900"
      :aria-label="$t('Danger zone')"
    >
      <h2 class="text-lg font-semibold text-red-700 dark:text-red-400">
        {{ $t('Danger zone') }}
      </h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        {{
          $t(
            'Archiving removes this desk from the workspace. Its tickets stay as they are, and an admin can restore it later.',
          )
        }}
      </p>
      <AppButton type="button" color="red" @click="archiving = true">{{
        $t('Archive desk')
      }}</AppButton>
    </section>
  </div>

  <ConfirmationModal
    v-if="archiving"
    :loading="archiveLoading"
    @close="archiving = false"
    @confirmed="archive"
  >
    <template #headline>{{
      $t('Archive :name?', { name: desk.name })
    }}</template
    >{{
      $t('Its tickets stay as they are. An admin can restore the desk later.')
    }}<template #actionText>{{ $t('Archive desk') }}</template>
  </ConfirmationModal>
</template>
