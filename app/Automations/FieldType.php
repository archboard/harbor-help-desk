<?php

namespace App\Automations;

/**
 * What kind of value a field resolves to, which decides the operators and value input it gets.
 * A `duration` is elapsed minutes, optionally in a business schedule's time.
 */
enum FieldType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case ENUM = 'enum';
    case MULTI_ENUM = 'multi_enum';
    case USER = 'user';
    case USERS = 'users';
    case DESK = 'desk';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case DATETIME = 'datetime';
    case DURATION = 'duration';
}
