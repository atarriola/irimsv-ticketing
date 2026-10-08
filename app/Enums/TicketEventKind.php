<?php

namespace App\Enums;

enum TicketEventKind: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case PriorityChanged = 'priority_changed';
    case Edited = 'edited';
    case Rated = 'rated';
    case ReleaseLinked = 'release_linked';
    case Deleted = 'deleted';
    case Restored = 'restored';
}
