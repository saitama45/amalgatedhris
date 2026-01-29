<?php

echo '--- Users ---' . PHP_EOL;
\App\Models\User::all(['id', 'name', 'email'])->each(function($u) {
    print_r($u->toArray());
});

echo '--- Employees ---' . PHP_EOL;
\App\Models\Employee::all(['id', 'user_id', 'employee_code'])->each(function($e) {
    print_r($e->toArray());
});

echo '--- Employment Records ---' . PHP_EOL;
\App\Models\EmploymentRecord::all(['id', 'employee_id', 'is_active', 'employment_status'])->each(function($r) {
    print_r($r->toArray());
});
