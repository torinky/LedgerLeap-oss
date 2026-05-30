<?php

return array_merge(
    require __DIR__.'/ledger/workflow.php',
    require __DIR__.'/ledger/notifications.php',
    require __DIR__.'/ledger/columns.php',
    require __DIR__.'/ledger/folders.php',
    require __DIR__.'/ledger/access.php',
    require __DIR__.'/ledger/mcp.php',
    require __DIR__.'/ledger/statistics.php',
    require __DIR__.'/ledger/forms.php',
    require __DIR__.'/ledger/related.php',
    require __DIR__.'/ledger/file_inspector.php',
    require __DIR__.'/ledger/history.php',
    require __DIR__.'/ledger/misc_components.php',
    require __DIR__.'/ledger/ui.php',
    require __DIR__.'/ledger/diff.php',
    require __DIR__.'/ledger/confidentiality.php',
);
