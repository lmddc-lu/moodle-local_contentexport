<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_contentexport_export_course' => [
        'classname'   => 'local_contentexport_external',
        'methodname'  => 'export_course',
        'classpath'   => 'local/contentexport/externallib.php',
        'description' => 'Export course structure and basic content',
        'type'        => 'read',
        'capabilities' => 'moodle/course:view',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ],
    'local_contentexport_export_all_courses' => [
        'classname'   => 'local_contentexport_external',
        'methodname'  => 'export_all_courses',
        'classpath'   => 'local/contentexport/externallib.php',
        'description' => 'Export all accessible courses. Use include_non_enrolled parameter to export courses user is not enrolled in (requires moodle/course:viewhiddencourses or moodle/site:config capability)',
        'type'        => 'read',
        'capabilities' => 'moodle/course:view',
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE]
    ]
];

$services = [
    'Content Export Service' => [
        'functions' => [
            'local_contentexport_export_course',
            'local_contentexport_export_all_courses'
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'contentexport'
    ]
];