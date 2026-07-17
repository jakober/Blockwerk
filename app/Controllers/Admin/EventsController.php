<?php
declare(strict_types=1);

namespace Controllers\Admin;

class EventsController extends PostControllerBase
{
    protected string $type = 'event';
    protected string $basePath = '/admin/events';
    protected string $labelSingular = 'Event';
    protected string $labelPlural = 'Events';
}
