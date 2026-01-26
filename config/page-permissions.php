<?php

return [
    // Main dashboard
    '/dashboard' => ['view_students'], // Main dashboard - requires at least view_students
    
    // People section
    '/dashboard/students' => ['view_students'],
    '/dashboard/students/[id]' => ['view_students'], // Dynamic route for student profile
    '/dashboard/families' => ['view_students'],
    '/dashboard/leads' => ['view_students'],
    '/dashboard/teachers' => ['view_teachers'],
    '/dashboard/teachers/[id]' => ['view_teachers'], // Dynamic route for teacher profile
    
    // Learning section
    '/dashboard/courses' => ['view_courses'],
    '/dashboard/courses/[id]' => ['view_courses'], // Dynamic route for course detail
    '/dashboard/teacher-schedule' => ['view_timetables'],
    '/dashboard/trial-classes' => ['view_trials'],
    
    // Academic section
    '/dashboard/calendy' => ['view_timetables'],
    '/dashboard/classes' => ['view_timetables'],
    '/dashboard/timetables' => ['view_timetables'],
    '/dashboard/packages' => ['view_packages'],
    '/dashboard/packages/finished' => ['view_packages'],
    '/dashboard/notifications' => ['view_students'],
    '/dashboard/billing' => ['view_billing'],
    '/dashboard/duties' => ['view_duties'],
    '/dashboard/reports' => ['view_reports'],
    '/dashboard/reports/courses' => ['view_reports'],
    '/dashboard/reports/students' => ['view_reports'],
    '/dashboard/financials' => ['view_financials'],
    '/dashboard/salaries' => ['view_teachers'],
    '/dashboard/activity' => ['view_students'],
    '/dashboard/student-activity' => ['view_students'],
    
    // System section
    '/dashboard/users' => ['manage_users'],
    '/dashboard/roles' => ['manage_roles'],
    '/dashboard/settings' => ['view_settings'],
    
    // Teacher routes (role-based, not permission-based)
    '/dashboard/teacher' => ['view_timetables'], // Teacher dashboard
    '/dashboard/teacher/classes' => ['view_timetables'],
    '/dashboard/teacher/students' => ['view_timetables'],
    '/dashboard/teacher/trials' => ['view_timetables'],
    '/dashboard/teacher/availability' => ['view_timetables'],
    '/dashboard/teacher/calendar' => ['view_timetables'],
];
