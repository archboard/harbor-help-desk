/** The props of the full ticket view: the ticket page and the quick view's `preview` shared prop. */
export interface TicketPageProps {
  ticket: App.Data.Tickets.TicketData;
  comments: App.Data.Tickets.TicketCommentData[];
  events: App.Data.Tickets.TicketEventData[];
  attachments: App.Data.Tickets.AttachmentData[];
  reminders: App.Data.Tickets.TicketReminderData[];
  deskMembers: App.Data.Desks.DeskMemberData[];
  desks: App.Data.Desks.DeskData[];
  tags: App.Data.Tags.TagData[];
  macros?: App.Data.Macros.MacroData[];
  statuses: Record<string, string>;
  priorities: Record<string, string>;
  can: { update: boolean; delete: boolean; restore: boolean };
}
