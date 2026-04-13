<?php

namespace LaravelWhatsApp\Enums;

enum TemplateParameterFormat: string
{
    case POSITIONAL = 'POSITIONAL';
    case NAMED = 'NAMED';
}
