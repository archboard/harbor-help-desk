<script setup lang="ts">
import { approve, deny } from '@/routes/passport/authorizations';

// Placeholder until the frontend is designed.
defineOptions({
  layout: { title: 'Authorize application' },
});

defineProps<{
  client: { id: string; name: string };
  workspaces: { id: string; name: string }[];
  scopes: string[];
  state: string | null;
  authToken: string;
  csrfToken: string;
  errors: Record<string, string>;
}>();
</script>

<template>
  <div>
    <p>{{ client.name }} wants to access your account.</p>

    <!-- Plain forms, not Inertia: approval redirects to the client's external redirect URI. -->
    <form v-bind="approve.form()">
      <label v-for="workspace in workspaces" :key="workspace.id">
        <input type="checkbox" name="workspaces[]" :value="workspace.id" />
        {{ workspace.name }}
      </label>
      <span v-if="errors.workspaces">{{ errors.workspaces }}</span>
      <input type="hidden" name="_token" :value="csrfToken" />
      <input type="hidden" name="state" :value="state ?? ''" />
      <input type="hidden" name="client_id" :value="client.id" />
      <input type="hidden" name="auth_token" :value="authToken" />
      <button type="submit">Authorize</button>
    </form>

    <form v-bind="deny.form()">
      <input type="hidden" name="_token" :value="csrfToken" />
      <input type="hidden" name="state" :value="state ?? ''" />
      <input type="hidden" name="client_id" :value="client.id" />
      <input type="hidden" name="auth_token" :value="authToken" />
      <button type="submit">Deny</button>
    </form>
  </div>
</template>
