<?php

$permissions = [
    'students' => [
        'manage_students',
        'view_students',
    ],
    'teachers' => [
        'manage_teachers',
        'view_teachers',
    ],
    'timetables' => [
        'manage_timetables',
        'view_timetables',
    ],
    'billing' => [
        'manage_billing',
        'view_billing',
        'approve_payments',
    ],
    'financials' => [
        'view_financials',
        'manage_expenses',
    ],
    'whatsapp' => [
        'send_whatsapp',
    ],
    'reports' => [
        'view_reports',
        'generate_reports',
    ],
    'courses' => [
        'manage_courses',
        'view_courses',
    ],
    'packages' => [
        'manage_packages',
        'view_packages',
    ],
    'trials' => [
        'manage_trials',
        'view_trials',
    ],
    'duties' => [
        'manage_duties',
        'view_duties',
    ],
    'settings' => [
        'manage_settings',
        'view_settings',
    ],
    'users' => [
        'manage_users',
        'view_users',
    ],
    'roles' => [
        'manage_roles',
        'view_roles',
    ],
];

// Page to permission mapping for role configuration
$pagePermissions = [
    '/dashboard/students' => ['view_students'],
    '/dashboard/families' => ['view_students'],
    '/dashboard/leads' => ['view_students'],
    '/dashboard/teachers' => ['view_teachers'],
    '/dashboard/courses' => ['view_courses'],
    '/dashboard/timetables' => ['view_timetables'],
    '/dashboard/classes' => ['view_timetables'],
    '/dashboard/trial-classes' => ['view_timetables'],
    '/dashboard/packages' => ['view_students'],
    '/dashboard/billing' => ['view_billing'],
    '/dashboard/financials' => ['view_financials'],
    '/dashboard/reports' => ['view_reports'],
    '/dashboard/duties' => ['view_students'],
    '/dashboard/activity' => ['view_students'],
    '/dashboard/student-activity' => ['view_students'],
    '/dashboard/salaries' => ['view_teachers'],
    '/dashboard/notifications' => ['view_students'],
    '/dashboard/users' => ['manage_users'],
    '/dashboard/roles' => ['manage_roles'],
    '/dashboard/settings' => ['view_settings'],
];

return $permissions;
