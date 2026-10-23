import type { Component } from 'vue';
import AddNoteConfig from './AddNoteConfig.vue';
import ApplyMacroConfig from './ApplyMacroConfig.vue';
import AssignConfig from './AssignConfig.vue';
import MoveDeskConfig from './MoveDeskConfig.vue';
import NotifyConfig from './NotifyConfig.vue';
import SendReplyConfig from './SendReplyConfig.vue';
import SendWebhookConfig from './SendWebhookConfig.vue';
import SetFieldConfig from './SetFieldConfig.vue';
import SetReminderConfig from './SetReminderConfig.vue';
import SetSlaPolicyConfig from './SetSlaPolicyConfig.vue';
import TagsConfig from './TagsConfig.vue';

/**
 * Editors for the actions, by action key. An action without one uses the generic `ConfigForm`.
 * Each takes `v-model` (the config) and the props of `ActionEditorProps`.
 */
export const actionEditors: Record<string, Component> = {
  add_note: AddNoteConfig,
  apply_macro: ApplyMacroConfig,
  assign: AssignConfig,
  move_desk: MoveDeskConfig,
  notify: NotifyConfig,
  send_reply: SendReplyConfig,
  send_webhook: SendWebhookConfig,
  set_field: SetFieldConfig,
  set_reminder: SetReminderConfig,
  set_sla_policy: SetSlaPolicyConfig,
  tags: TagsConfig,
};
