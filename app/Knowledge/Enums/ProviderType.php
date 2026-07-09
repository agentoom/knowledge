<?php

namespace App\Knowledge\Enums;

enum ProviderType: string
{
    case Filesystem = 'filesystem';
    case Sql = 'sql';
    case Yaml = 'yaml';
    case Json = 'json';
    case Rest = 'rest';
    case Mcp = 'mcp';
    case Website = 'website';
}
