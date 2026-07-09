<?php

namespace App\Planning\Enums;

enum QueryIntent: string
{
    case Keyword = 'keyword';
    case Structured = 'structured';
    case Semantic = 'semantic';
    case Hybrid = 'hybrid';
}
