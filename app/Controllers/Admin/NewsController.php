<?php
declare(strict_types=1);

namespace Controllers\Admin;

class NewsController extends PostControllerBase
{
    protected string $type = 'news';
    protected string $basePath = '/admin/news';
    protected string $labelSingular = 'News-Beitrag';
    protected string $labelPlural = 'News';
}
