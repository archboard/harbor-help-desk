<?php

use App\Data\Users\UserData;
use App\Models\Automation;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tickets.{ticket}', fn (User $user, Ticket $ticket): bool => $user->can('view', $ticket));

Broadcast::channel('desks.{desk}', fn (User $user, Desk $desk): bool => $user->can('view', $desk));

Broadcast::channel('automations.{automation}', fn (User $user, Automation $automation): bool => $user->can('view', $automation));

Broadcast::channel('users.{user}', fn (User $user, User $model): bool => $user->is($model));

Broadcast::channel('tickets.{ticket}.presence', fn (User $user, Ticket $ticket): UserData|false => $user->can('view', $ticket) ? UserData::from($user) : false);
