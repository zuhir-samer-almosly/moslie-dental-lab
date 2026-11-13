<?php

use App\Mcp\Servers\DentalLabServer;
use Laravel\Mcp\Facades\Mcp;

// Local (stdio) only, started with `php artisan mcp:start dental-lab`. There
// is deliberately no `Mcp::web()` route: these tools write to the books, and
// whoever can run artisan in the container is already trusted to. Exposing
// them over HTTP would need its own authentication first.
Mcp::local('dental-lab', DentalLabServer::class);
