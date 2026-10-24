<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/components/AppButton.vue';
import { RadioField, TextareaField } from '@/components/forms/fields';

defineOptions({ layout: { title: 'How did we do?' } });

const { ticket, rating, options } = defineProps<{
  ticket: App.Data.Portal.PortalTicketData;
  rating: App.Enums.SatisfactionScore;
  options: Record<string, string>;
}>();

const page = usePage();
const form = useForm({
  rating,
  comment: ticket.satisfaction_rating?.comment ?? '',
});

// The signed link is the credential: post back to this exact URL, query string included.
const submit = () => form.post(page.url);
</script>

<template>
  <Head title="Rate your support" />

  <p class="mb-6 text-sm">#{{ ticket.number }} {{ ticket.subject }}</p>

  <form class="max-w-md space-y-4" @submit.prevent="submit">
    <RadioField
      v-model="form.rating"
      label="How was the support you received?"
      :options="options"
      :error="form.errors.rating"
    />
    <TextareaField
      v-model="form.comment"
      label="Anything to add? (optional)"
      :error="form.errors.comment"
    />
    <AppButton type="submit" :loading="form.processing">
      {{ ticket.satisfaction_rating ? 'Update my rating' : 'Send my rating' }}
    </AppButton>
  </form>
</template>
