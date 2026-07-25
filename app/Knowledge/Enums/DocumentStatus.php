<?php

namespace App\Knowledge\Enums;

enum DocumentStatus: string
{
    case Discovered = 'discovered';
    case Parsed = 'parsed';
    case Chunked = 'chunked';
    case Indexed = 'indexed';
    case Error = 'error';
}
