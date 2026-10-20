<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import CardAction from '@/components/CardAction.vue';
import CardPadding from '@/components/CardPadding.vue';
import CardWrapper from '@/components/CardWrapper.vue';
import AppFieldset from '@/components/forms/AppFieldset.vue';
import { InputField } from '@/components/forms/fields';
import { update } from '@/routes/workspace/general';

defineOptions({ layout: { title: 'Workspace' } });

const page = usePage();

const form = useForm({
  name: page.props.workspace?.name ?? '',
} satisfies App.Data.Workspaces.UpdateWorkspaceData);
</script>

<template>
  <Head title="Workspace" />

  <CardWrapper>
    <form @submit.prevent="form.submit(update(), { preserveScroll: true })">
      <CardPadding>
        <AppFieldset>
          <InputField
            v-model="form.name"
            label="Name"
            :error="form.errors.name"
            required
          />
        </AppFieldset>
      </CardPadding>
      <CardAction :loading="form.processing" />
    </form>
  </CardWrapper>
</template>
