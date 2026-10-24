<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import {
  InputField,
  SelectField,
  TextareaField,
} from '@/components/forms/fields';
import FieldError from '@/components/forms/FieldError.vue';
import FilePicker from '@/components/forms/FilePicker.vue';
import { firstListError } from '@/lib/files';
import { store } from '@/routes/portal/desks/tickets';
import { index } from '@/routes/portal/tickets';

defineOptions({ layout: { title: '' } });

const { desks } = defineProps<{
  desks: App.Data.Portal.PortalDeskData[];
}>();

const form = useForm({
  desk_id: desks[0]?.id ?? '',
  subject: '',
  body: '',
  attachments: [] as File[],
});

function send(): void {
  form.submit(store(form.desk_id));
}
</script>

<template>
  <Head title="New ticket" />

  <div class="space-y-6">
    <div class="space-y-3">
      <Link
        :href="index()"
        class="text-sm text-gray-500 hover:underline dark:text-gray-400"
      >
        ← Your tickets
      </Link>
      <h1 class="text-2xl font-semibold tracking-tight">New ticket</h1>
    </div>

    <form class="space-y-4" @submit.prevent="send">
      <SelectField
        v-if="desks.length > 1"
        v-model="form.desk_id"
        label="Department"
        :options="desks.map((desk) => ({ value: desk.id, label: desk.name }))"
        required
      />
      <InputField
        v-model="form.subject"
        label="Subject"
        :error="form.errors.subject"
        required
      />
      <TextareaField
        v-model="form.body"
        label="How can we help?"
        :error="form.errors.body"
        required
      />
      <FieldError v-if="firstListError(form.errors, 'attachments')">
        {{ firstListError(form.errors, 'attachments') }}
      </FieldError>
      <div class="flex items-start justify-between gap-4">
        <FilePicker v-model="form.attachments" />
        <AppButton type="submit" :loading="form.processing"
          >Open ticket</AppButton
        >
      </div>
    </form>
  </div>
</template>
