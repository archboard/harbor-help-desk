<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { destroy, store } from '@/routes/api-tokens';

// Placeholder until the frontend is designed.
defineProps<{
  tokens: {
    id: string;
    name: string;
    created_at: string | null;
    expires_at: string | null;
  }[];
}>();

const page = usePage();
</script>

<template>
  <div>
    <p v-if="page.flash.apiToken">
      Copy this token now, it won't be shown again:
      <code>{{ page.flash.apiToken }}</code>
    </p>

    <Form v-bind="store.form()" reset-on-success v-slot="{ errors }">
      <input name="name" placeholder="Token name" required />
      <span v-if="errors.name">{{ errors.name }}</span>
      <button type="submit">Create token</button>
    </Form>

    <ul>
      <li v-for="token in tokens" :key="token.id">
        {{ token.name }} (expires {{ token.expires_at }})
        <Form v-bind="destroy.form(token.id)">
          <button type="submit">Revoke</button>
        </Form>
      </li>
    </ul>
  </div>
</template>
