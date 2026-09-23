<?php

declare(strict_types=1);

use App\Kernel;

date_default_timezone_set('Europe/Vienna');

require_once dirname(__DIR__, 2).'/aesculapp-server/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
