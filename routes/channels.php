<?php

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tickets.{ticket}', fn (User $user, Ticket $ticket): bool => $user->can('view', $ticket));

Broadcast::channel('desks.{desk}', fn (User $user, Desk $desk): bool => $user->can('view', $desk));

Broadcast::channel('users.{user}', fn (User $user, User $model): bool => $user->is($model));
