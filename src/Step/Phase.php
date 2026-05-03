<?php

declare(strict_types=1);

namespace Drops\Step;

enum Phase: string
{
    case EXPORT = 'export';
    case IMPORT = 'import';
    case BOTH = 'both';

    /**
     * Whether this phase applies during an export operation.
     */
    public function appliesToExport(): bool
    {
        return $this === self::EXPORT || $this === self::BOTH;
    }

    /**
     * Whether this phase applies during an import operation.
     */
    public function appliesToImport(): bool
    {
        return $this === self::IMPORT || $this === self::BOTH;
    }
}
