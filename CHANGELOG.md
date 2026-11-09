# Changelog

All notable changes are listed here, in the [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format. Versions follow [Semantic Versioning](https://semver.org/) and releases are git tags (`vX.Y.Z`).

Each release upgrades cleanly from the previous minor version. Anything you must do by hand while upgrading (a changed `.env` value, a new service, a re-run of `install-vm.sh`) is listed under **Upgrade notes** in that release's section. If a release has no such heading, upgrading is just a pull and restart; see [docs/operations.md](docs/operations.md#upgrades).

## [Unreleased]

First release. It summarises phases 1 to 8 of the roadmap.

### Upgrade notes

None yet; this is the first release.

### Added

#### Tickets and contacts

- Workspaces, desks (with members and roles), contacts, tickets with public replies and internal notes, attachments, tags, reminders and custom fields on tickets and desks.
- Ticket list with search, multi-value filters, saved views, bulk actions, a column picker, keyboard navigation, a quick-view slide-out and a Cmd+K command palette.
- Create tickets from the UI, paginated ticket threads, magic-link sign-in, timezone support.
- Macros applied from the reply composer, collision warnings when someone else replied while you write, and who-is-viewing and typing indicators.
- Automatic ticket assignment per desk (rotation, "Get next ticket" claiming) and a workspace Away toggle.

#### Email

- Inbound email as tickets and replies, from hosted Postmark addresses or connected IMAP, Google and Microsoft 365 mailboxes (polling, IMAP IDLE watching and Gmail and Graph push).
- Agent replies sent by email to requesters, bounce handling, and app email sent through each workspace's notification mailbox.

#### Notifications and realtime

- In-app inbox and email notifications for new, assigned, updated and mentioned tickets and due reminders, with per-workspace and per-user preferences.
- Reverb realtime updates: tickets reload live when they change.

#### SLAs and automations

- Business schedules, SLA policies per desk, per-ticket targets with warnings and breaches.
- Automations with a condition builder, delays, paths and actions (set fields, tags, assign, move desk, reminders, notes, replies, macros, notifications, webhooks), a webhook trigger, scheduled sweeps, test runs, run history and templates.

#### Customer portal

- Contacts sign in by magic link to see their tickets, reply and open new ones.
- Satisfaction ratings through a survey email when a ticket is solved.

#### Reports and compliance

- Reports for overview, responses, resolution, agents, SLA and satisfaction, on the web and in the API.
- Audit log of admin and ticket activity.
- Contact data exports with signed downloads, irreversible contact erasure, ticket retention settings, and full workspace export and import.

#### API and MCP

- Versioned JSON API at `/api/v1` with OAuth (Passport) tokens scoped to workspaces, and an OpenAPI spec at `/api/v1/openapi.json`.
- MCP server with OAuth discovery, read and write tools, prompts and a connect-an-AI-assistant panel.
- Outbound webhooks with signed deliveries and automatic disabling of failing endpoints.

#### Workspaces and installation

- Single database multi-tenancy for the cloud (`HARBOR_MULTITENANT=true`) and a single-workspace self-hosted mode (`HARBOR_MULTITENANT=false`).
- First-run `/install` page with a service checklist, and a platform API to create, update and delete workspaces.
- Workspace settings for notification mailbox, attachment limits, portal, retention and inbound rate limits, managed in the UI rather than `.env`.

#### Operations and packaging

- Health checks: `/up` fails when Postgres or Redis is down, a scheduler heartbeat, and a System status card on Workspace → General.
- Operator alerts for failed jobs (once per job class per hour) and long Horizon waits.
- FrankenPHP Docker image and Compose stack (app, Horizon, scheduler, Reverb, PostgreSQL, Redis) published to `ghcr.io/archboard/harbor-help`.
- `setup-docker.sh`, `install-vm.sh` and `update-vm.sh` install and update Harbor Help with HTTPS.
- Installation and operations guides: backups, restores and upgrades.

### Security

- Rate limits on the API, password reset, webhooks, platform API and portal.
- Security headers, trusted proxies (`TRUSTED_PROXIES`) and dependency audits in CI.
- A route sweep and body-id tests proving one workspace cannot read or change another's data.

### Performance

- Indexes for the ticket list and contact search, cursor-paginated threads, lazy-loading prevention and a volume seeder for realistic data sizes.
