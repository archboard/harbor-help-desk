<script setup lang="ts">
import { Form, useForm } from '@inertiajs/vue3';
import { destroy, update } from '@/routes/desks';
import {
  destroy as removeMember,
  update as setMember,
} from '@/routes/desks/members';

// Placeholder until the frontend is designed.
const props = defineProps<{
  desk: { id: string; name: string; slug: string };
  members: { id: string; name: string; email: string; role: string }[];
  candidates: { id: string; name: string }[];
  can: { update: boolean; manageMembers: boolean; archive: boolean };
}>();

const roles = ['lead', 'agent'];

const newMember = useForm({ user: '', role: 'agent' });

const addMember = () => {
  newMember
    .transform(({ role }) => ({ role }))
    .put(setMember.url({ desk: props.desk.id, user: newMember.user }), {
      onSuccess: () => newMember.reset(),
    });
};
</script>

<template>
  <div>
    <p>{{ desk.name }} ({{ desk.slug }})</p>

    <Form v-if="can.update" v-bind="update.form(desk.id)" v-slot="{ errors }">
      <input name="name" :default-value="desk.name" required />
      <span v-if="errors.name">{{ errors.name }}</span>
      <input name="slug" :default-value="desk.slug" required />
      <span v-if="errors.slug">{{ errors.slug }}</span>
      <button type="submit">Save</button>
    </Form>

    <ul>
      <li v-for="member in members" :key="member.id">
        {{ member.name }} ({{ member.email }})
        <Form
          v-if="can.manageMembers"
          v-bind="setMember.form({ desk: desk.id, user: member.id })"
        >
          <select name="role" :value="member.role">
            <option v-for="role in roles" :key="role" :value="role">
              {{ role }}
            </option>
          </select>
          <button type="submit">Change role</button>
        </Form>
        <span v-else>{{ member.role }}</span>
        <Form
          v-if="can.manageMembers"
          v-bind="removeMember.form({ desk: desk.id, user: member.id })"
        >
          <button type="submit">Remove</button>
        </Form>
      </li>
    </ul>

    <form
      v-if="can.manageMembers && candidates.length"
      @submit.prevent="addMember"
    >
      <select v-model="newMember.user" required>
        <option
          v-for="candidate in candidates"
          :key="candidate.id"
          :value="candidate.id"
        >
          {{ candidate.name }}
        </option>
      </select>
      <select v-model="newMember.role">
        <option v-for="role in roles" :key="role" :value="role">
          {{ role }}
        </option>
      </select>
      <button type="submit" :disabled="newMember.processing">Add member</button>
      <span v-if="newMember.errors.user">{{ newMember.errors.user }}</span>
    </form>

    <Form v-if="can.archive" v-bind="destroy.form(desk.id)" v-slot="{ errors }">
      <button type="submit">Archive desk</button>
      <span v-if="errors.desk">{{ errors.desk }}</span>
    </Form>
  </div>
</template>
