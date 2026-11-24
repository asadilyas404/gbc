<?php

return [
    /*
    | Off hides every call-center screen, route, and job.
    | Live and each branch that should receive requests set this true.
    */
    'enabled' => filter_var(env('CALL_CENTER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'preparation_minutes' => 20,
    'handover_minutes' => 5,
];
