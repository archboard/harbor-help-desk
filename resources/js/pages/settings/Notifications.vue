<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import SimpleAlert from '@/components/alerts/SimpleAlert.vue';
import CardAction from '@/components/CardAction.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppCheckbox from '@/components/forms/AppCheckbox.vue';
import FieldError from '@/components/forms/FieldError.vue';
import SettingsNav from '@/components/SettingsNav.vue';
import Table from '@/components/tables/Table.vue';
import Tbody from '@/components/tables/Tbody.vue';
import Td from '@/components/tables/Td.vue';
import Th from '@/components/tables/Th.vue';
import Thead from '@/components/tables/Thead.vue';
import { update } from '@/routes/notification-preferences';
import { index as emailSettings } from '@/routes/workspace/email';

defineOptions({ layout: { title: trans('Notifications') } });

const { preferences, channels } = defineProps<{
  preferences: App.Data.Notifications.NotificationPreferenceData[];
  channels: Record<string, string>;
  hasNotificationMailbox: boolean;
}>();

const form = useForm({
  preferences: Object.fromEntries(
    preferences.map((preference) => [
      preference.type,
      { ...preference.channels },
    ]),
  ),
});
</script>

<template>
  <Head :title="$t('Notifications')" />

  <SettingsNav />

  <div class="space-y-6">
    <SimpleAlert v-if="!hasNotificationMailbox" level="warning">
      {{
        $t(
          "This workspace has no mailbox to send email from, so email notifications won't be delivered.",
        )
      }}
      <Link :href="emailSettings().url" class="font-medium underline">{{
        $t('Set up email')
      }}</Link>
    </SimpleAlert>

    <CardWrapper>
      <form @submit.prevent="form.put(update().url, { preserveScroll: true })">
        <Table>
          <Thead>
            <tr>
              <Th>{{ $t('Notify me when') }}</Th>
              <Th v-for="(label, channel) in channels" :key="channel">
                {{ label }}
              </Th>
            </tr>
          </Thead>
          <Tbody>
            <tr v-for="preference in preferences" :key="preference.type">
              <Td darker>
                <span class="block font-medium">{{ preference.label }}</span>
                <span class="block text-gray-500 dark:text-gray-400">
                  {{ preference.description }}
                </span>
              </Td>
              <Td v-for="(label, channel) in channels" :key="channel">
                <AppCheckbox
                  v-model="form.preferences[preference.type][channel]"
                >
                  <span class="sr-only">
                    {{ label }} for {{ preference.label }}
                  </span>
                </AppCheckbox>
              </Td>
            </tr>
          </Tbody>
        </Table>
        <FieldError :error="form.errors.preferences" class="px-4" />
        <CardAction>
          <AppButton type="submit" :loading="form.processing">{{
            $t('Save')
          }}</AppButton>
        </CardAction>
      </form>
    </CardWrapper>
  </div>
</template>
