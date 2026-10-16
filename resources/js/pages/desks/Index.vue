<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { restore, show, store } from '@/routes/desks';

// Placeholder until the frontend is designed.
type Desk = { id: string; name: string; slug: string };

defineProps<{
  desks: Desk[];
  archivedDesks: Desk[];
  canManageDesks: boolean;
}>();
</script>

<template>
  <div>
    <ul>
      <li v-for="desk in desks" :key="desk.id">
        <Link :href="show(desk.id)">{{ desk.name }}</Link> ({{ desk.slug }})
      </li>
    </ul>

    <Form
      v-if="canManageDesks"
      v-bind="store.form()"
      reset-on-success
      v-slot="{ errors }"
    >
      <input name="name" placeholder="Desk name" required />
      <span v-if="errors.name">{{ errors.name }}</span>
      <input name="slug" placeholder="Slug (optional)" />
      <span v-if="errors.slug">{{ errors.slug }}</span>
      <button type="submit">Create desk</button>
    </Form>

    <template v-if="archivedDesks.length">
      <p>Archived</p>
      <ul>
        <li v-for="desk in archivedDesks" :key="desk.id">
          {{ desk.name }}
          <Form v-bind="restore.form(desk.id)" v-slot="{ errors }">
            <button type="submit">Restore</button>
            <span v-if="errors.slug">{{ errors.slug }}</span>
          </Form>
        </li>
      </ul>
    </template>
  </div>
</template>
