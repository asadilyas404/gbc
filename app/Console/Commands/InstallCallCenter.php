<?php

namespace App\Console\Commands;

use App\Services\CallCenterSchema;
use Illuminate\Console\Command;

class InstallCallCenter extends Command
{
    protected $signature = 'call-center:install';

    protected $description = 'Create call center tables and default delivery-time settings';

    public function handle(CallCenterSchema $schema): int
    {
        $schema->install();
        $this->info('Call center tables are ready.');

        return 0;
    }
}
