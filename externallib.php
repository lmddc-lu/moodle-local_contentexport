<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/local/contentexport/classes/export_service.php");

class local_contentexport_external extends external_api {

    /**
     * Returns description of method parameters
     */
    public static function export_course_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID to export')
        ]);
    }

    /**
     * Export course content
     */
    public static function export_course($courseid) {
        global $DB, $USER;

        // Validate parameters
        $params = self::validate_parameters(self::export_course_parameters(), [
            'courseid' => $courseid
        ]);

        // Validate context and permissions
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        // Use our export service to get the data
        $exportService = new \local_contentexport\export_service();
        $courseData = $exportService->export_course($course);

        return $courseData;
    }

    /**
     * Returns description of method return value
     */
    public static function export_course_returns() {
        return new external_single_structure([
            'course' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                'description' => new external_value(PARAM_RAW, 'Course description'),
                'category' => new external_value(PARAM_TEXT, 'Course category name'),
                'course_url' => new external_value(PARAM_URL, 'Direct link to course in Moodle'),
                'sections' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Section ID'),
                        'name' => new external_value(PARAM_TEXT, 'Section name'),
                        'summary' => new external_value(PARAM_RAW, 'Section summary'),
                        'section_number' => new external_value(PARAM_INT, 'Section number (0 for general section)'),
                        'section_url' => new external_value(PARAM_URL, 'Direct link to section in Moodle'),
                        'activities' => new external_multiple_structure(
                            new external_single_structure([
                                'id' => new external_value(PARAM_INT, 'Activity ID'),
                                'name' => new external_value(PARAM_TEXT, 'Activity name'),
                                'type' => new external_value(PARAM_TEXT, 'Activity type'),
                                'description' => new external_value(PARAM_RAW, 'Activity description'),
                                'activity_url' => new external_value(PARAM_URL, 'Direct link to activity in Moodle'),
                                'files' => new external_multiple_structure(
                                    new external_single_structure([
                                        'id' => new external_value(PARAM_INT, 'File ID'),
                                        'filename' => new external_value(PARAM_TEXT, 'File name'),
                                        'filepath' => new external_value(PARAM_TEXT, 'File path'),
                                        'filesize' => new external_value(PARAM_INT, 'File size in bytes'),
                                        'mimetype' => new external_value(PARAM_TEXT, 'File MIME type'),
                                        'timecreated' => new external_value(PARAM_INT, 'File creation timestamp'),
                                        'timemodified' => new external_value(PARAM_INT, 'File modification timestamp'),
                                        'download_url' => new external_value(PARAM_URL, 'File download URL'),
                                        'hash' => new external_value(PARAM_TEXT, 'File content hash'),
                                        'scorm_type' => new external_value(PARAM_TEXT, 'SCORM type (for SCORM files)', VALUE_OPTIONAL),
                                        'scorm_version' => new external_value(PARAM_TEXT, 'SCORM version (for SCORM files)', VALUE_OPTIONAL),
                                        'scorm_reference' => new external_value(PARAM_TEXT, 'SCORM reference (for SCORM files)', VALUE_OPTIONAL),
                                    ]), 'Files associated with this activity'
                                ),
                                'urls' => new external_multiple_structure(
                                    new external_single_structure([
                                        'url' => new external_value(PARAM_URL, 'The URL'),
                                        'display_type' => new external_value(PARAM_INT, 'Display type for URL activities', VALUE_OPTIONAL),
                                        'display_options' => new external_value(PARAM_TEXT, 'Display options', VALUE_OPTIONAL),
                                        'parameters' => new external_value(PARAM_TEXT, 'URL parameters', VALUE_OPTIONAL),
                                        'is_primary_url' => new external_value(PARAM_BOOL, 'Is this the primary URL for the activity'),
                                        'found_in' => new external_value(PARAM_TEXT, 'Where this URL was found')
                                    ]), 'URLs associated with this activity'
                                ),
                                'content_data' => self::get_content_data_structure()
                            ])
                        )
                    ])
                )
            ]),
            'exported_at' => new external_value(PARAM_TEXT, 'Export timestamp')
        ]);
    }

    /**
     * Helper method to define content_data structure (reusable across functions)
     */
    private static function get_content_data_structure() {
        return new external_single_structure([
            'type' => new external_value(PARAM_TEXT, 'Content type', VALUE_OPTIONAL),
            // Book content
            'chapters' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Chapter ID'),
                    'title' => new external_value(PARAM_TEXT, 'Chapter title'),
                    'content' => new external_value(PARAM_RAW, 'Chapter content (HTML)'),
                    'pagenum' => new external_value(PARAM_INT, 'Page number'),
                    'subchapter' => new external_value(PARAM_BOOL, 'Is subchapter'),
                    'hidden' => new external_value(PARAM_BOOL, 'Is hidden')
                ]), 'Book chapters', VALUE_OPTIONAL
            ),
            // Glossary content
            'entries' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Entry ID'),
                    'concept' => new external_value(PARAM_TEXT, 'Glossary concept'),
                    'definition' => new external_value(PARAM_RAW, 'Definition (HTML)'),
                    'author' => new external_value(PARAM_INT, 'Author user ID'),
                    'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
                    'timemodified' => new external_value(PARAM_INT, 'Modification timestamp')
                ]), 'Glossary entries', VALUE_OPTIONAL
            ),
            // Page content
            'content' => new external_value(PARAM_RAW, 'Page content (HTML)', VALUE_OPTIONAL),
            // Quiz content
            'quiz_id' => new external_value(PARAM_INT, 'Quiz ID', VALUE_OPTIONAL),
            'time_limit' => new external_value(PARAM_INT, 'Time limit in seconds', VALUE_OPTIONAL),
            'attempts_allowed' => new external_value(PARAM_INT, 'Number of attempts allowed', VALUE_OPTIONAL),
            'grading_method' => new external_value(PARAM_INT, 'Grading method', VALUE_OPTIONAL),
            'grade_to_pass' => new external_value(PARAM_FLOAT, 'Grade required to pass', VALUE_OPTIONAL),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'slot' => new external_value(PARAM_INT, 'Question slot number'),
                    'question_id' => new external_value(PARAM_INT, 'Question ID'),
                    'name' => new external_value(PARAM_TEXT, 'Question name'),
                    'question_text' => new external_value(PARAM_RAW, 'Question text'),
                    'question_type' => new external_value(PARAM_TEXT, 'Question type'),
                    'default_mark' => new external_value(PARAM_FLOAT, 'Default mark'),
                    'max_mark' => new external_value(PARAM_FLOAT, 'Maximum mark for this question in the quiz')
                ]), 'Quiz questions', VALUE_OPTIONAL
            ),
            'attempts' => new external_multiple_structure(
                new external_single_structure([
                    'attempt_id' => new external_value(PARAM_INT, 'Attempt ID'),
                    'user_id' => new external_value(PARAM_INT, 'User ID'),
                    'user_name' => new external_value(PARAM_TEXT, 'User full name'),
                    'user_email' => new external_value(PARAM_TEXT, 'User email'),
                    'attempt_number' => new external_value(PARAM_INT, 'Attempt number'),
                    'state' => new external_value(PARAM_TEXT, 'Attempt state (inprogress, finished, abandoned)'),
                    'time_start' => new external_value(PARAM_INT, 'Start timestamp', VALUE_OPTIONAL),
                    'time_finish' => new external_value(PARAM_INT, 'Finish timestamp', VALUE_OPTIONAL),
                    'time_modified' => new external_value(PARAM_INT, 'Last modified timestamp'),
                    'grade' => new external_value(PARAM_FLOAT, 'Final grade', VALUE_OPTIONAL),
                    'behaviour' => new external_value(PARAM_TEXT, 'Question behaviour'),
                    'questions' => new external_multiple_structure(
                        new external_single_structure([
                            'attempt_id' => new external_value(PARAM_INT, 'Question attempt ID'),
                            'slot' => new external_value(PARAM_INT, 'Question slot'),
                            'question_id' => new external_value(PARAM_INT, 'Question ID'),
                            'variant' => new external_value(PARAM_INT, 'Question variant'),
                            'max_mark' => new external_value(PARAM_FLOAT, 'Maximum mark'),
                            'min_fraction' => new external_value(PARAM_FLOAT, 'Minimum fraction'),
                            'flagged' => new external_value(PARAM_BOOL, 'Is flagged'),
                            'question_summary' => new external_value(PARAM_RAW, 'Question summary', VALUE_OPTIONAL),
                            'right_answer' => new external_value(PARAM_RAW, 'Right answer', VALUE_OPTIONAL),
                            'response_summary' => new external_value(PARAM_RAW, 'Response summary', VALUE_OPTIONAL),
                            'behaviour' => new external_value(PARAM_TEXT, 'Question behaviour'),
                            'steps' => new external_multiple_structure(
                                new external_single_structure([
                                    'sequence' => new external_value(PARAM_INT, 'Step sequence number'),
                                    'state' => new external_value(PARAM_TEXT, 'Question state'),
                                    'fraction' => new external_value(PARAM_FLOAT, 'Fraction (score)', VALUE_OPTIONAL),
                                    'time_created' => new external_value(PARAM_INT, 'Time created'),
                                    'user_id' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
                                    'data' => new external_value(PARAM_RAW, 'Step data as JSON', VALUE_OPTIONAL)
                                ]), 'Question attempt steps'
                            )
                        ]), 'Question attempts'
                    )
                ]), 'Quiz attempts', VALUE_OPTIONAL
            )
        ], 'Content-specific data for books, glossaries, quizzes, etc.', VALUE_OPTIONAL);
    }

    /**
     * Returns description of bulk export parameters
     */
    public static function export_all_courses_parameters() {
        return new external_function_parameters([
            'include_hidden' => new external_value(PARAM_BOOL, 'Include hidden courses', VALUE_DEFAULT, false),
            'category_id' => new external_value(PARAM_INT, 'Limit to specific category (0 = all)', VALUE_DEFAULT, 0),
            'offset' => new external_value(PARAM_INT, 'Starting position for pagination (0-based)', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum number of courses to return (0 = no limit)', VALUE_DEFAULT, 50),
            'include_non_enrolled' => new external_value(PARAM_BOOL, 'Include courses user is not enrolled in (requires special permissions)', VALUE_DEFAULT, false)
        ]);
    }

    /**
     * Export all accessible courses with pagination
     */
    public static function export_all_courses($include_hidden = false, $category_id = 0, $offset = 0, $limit = 50, $include_non_enrolled = false) {
        global $DB, $USER;

        // Validate parameters
        $params = self::validate_parameters(self::export_all_courses_parameters(), [
            'include_hidden' => $include_hidden,
            'category_id' => $category_id,
            'offset' => $offset,
            'limit' => $limit,
            'include_non_enrolled' => $include_non_enrolled
        ]);

        // Check permissions for non-enrolled courses
        if ($params['include_non_enrolled']) {
            $systemcontext = context_system::instance();
            if (!has_capability('moodle/course:viewhiddencourses', $systemcontext) && 
                !has_capability('moodle/site:config', $systemcontext)) {
                throw new moodle_exception('nopermissions', 'error', '', 'view non-enrolled courses');
            }
        }

        if ($params['include_non_enrolled']) {
            // Get all courses (enrolled and non-enrolled)
            $sql = "SELECT DISTINCT c.id, c.fullname, c.shortname, c.summary, c.category, c.visible
                    FROM {course} c
                    WHERE c.id != :siteid";
            
            $sqlparams = ['siteid' => SITEID];
        } else {
            // Get only enrolled courses (original behavior)
            $sql = "SELECT c.id, c.fullname, c.shortname, c.summary, c.category, c.visible
                    FROM {course} c
                    JOIN {enrol} e ON e.courseid = c.id
                    JOIN {user_enrolments} ue ON ue.enrolid = e.id
                    WHERE ue.userid = :userid
                    AND c.id != :siteid";
            
            $sqlparams = [
                'userid' => $USER->id,
                'siteid' => SITEID
            ];
        }

        // Add visibility filter
        if (!$params['include_hidden']) {
            $sql .= " AND c.visible = 1";
        }

        // Add category filter
        if ($params['category_id'] > 0) {
            $sql .= " AND c.category = :categoryid";
            $sqlparams['categoryid'] = $params['category_id'];
        }

        // Get total count for pagination metadata
        $countSql = "SELECT COUNT(DISTINCT c.id) " . substr($sql, strpos($sql, 'FROM'));
        $totalCourses = (int)$DB->count_records_sql($countSql, $sqlparams);

        // Add ordering and pagination
        if (!$params['include_non_enrolled']) {
            $sql .= " GROUP BY c.id, c.fullname, c.shortname, c.summary, c.category, c.visible";
        }
        $sql .= " ORDER BY c.fullname ASC";

        // Apply pagination limits
        if ($params['limit'] > 0) {
            $courses = $DB->get_records_sql($sql, $sqlparams, $params['offset'], $params['limit']);
        } else {
            $courses = $DB->get_records_sql($sql, $sqlparams);
        }

        // Export each course
        $exportService = new \local_contentexport\export_service();
        $coursesData = [];

        foreach ($courses as $course) {
            try {
                // Validate context for each course
                $context = context_course::instance($course->id);
                
                // Check if user can view this course
                if (has_capability('moodle/course:view', $context) || 
                    ($params['include_non_enrolled'] && has_capability('moodle/course:viewhiddencourses', context_system::instance()))) {
                    
                    $courseData = $exportService->export_course($course);
                    $coursesData[] = $courseData['course'];
                }
            } catch (Exception $e) {
                // Skip courses that can't be accessed
                continue;
            }
        }

        // Calculate pagination metadata
        $hasMore = ($params['offset'] + count($coursesData)) < $totalCourses;

        $pagination = [
            'total_courses' => (int)$totalCourses,
            'returned_courses' => (int)count($coursesData),
            'offset' => (int)$params['offset'],
            'limit' => (int)$params['limit'],
            'has_more' => (bool)$hasMore
        ];

        // Only include next_offset if there are more results
        if ($hasMore) {
            $pagination['next_offset'] = (int)($params['offset'] + $params['limit']);
        }

        return [
            'courses' => $coursesData,
            'pagination' => $pagination,
            'exported_at' => date('c')
        ];
    }

    /**
     * Returns description of bulk export return value
     */
    public static function export_all_courses_returns() {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Course ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
                    'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
                    'description' => new external_value(PARAM_RAW, 'Course description'),
                    'category' => new external_value(PARAM_TEXT, 'Course category name'),
                    'course_url' => new external_value(PARAM_URL, 'Direct link to course in Moodle'),
                    'sections' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Section ID'),
                            'name' => new external_value(PARAM_TEXT, 'Section name'),
                            'summary' => new external_value(PARAM_RAW, 'Section summary'),
                            'section_number' => new external_value(PARAM_INT, 'Section number (0 for general section)'),
                            'section_url' => new external_value(PARAM_URL, 'Direct link to section in Moodle'),
                            'activities' => new external_multiple_structure(
                                new external_single_structure([
                                    'id' => new external_value(PARAM_INT, 'Activity ID'),
                                    'name' => new external_value(PARAM_TEXT, 'Activity name'),
                                    'type' => new external_value(PARAM_TEXT, 'Activity type'),
                                    'description' => new external_value(PARAM_RAW, 'Activity description'),
                                    'activity_url' => new external_value(PARAM_URL, 'Direct link to activity in Moodle'),
                                    'files' => new external_multiple_structure(
                                        new external_single_structure([
                                            'id' => new external_value(PARAM_INT, 'File ID'),
                                            'filename' => new external_value(PARAM_TEXT, 'File name'),
                                            'filepath' => new external_value(PARAM_TEXT, 'File path'),
                                            'filesize' => new external_value(PARAM_INT, 'File size in bytes'),
                                            'mimetype' => new external_value(PARAM_TEXT, 'File MIME type'),
                                            'timecreated' => new external_value(PARAM_INT, 'File creation timestamp'),
                                            'timemodified' => new external_value(PARAM_INT, 'File modification timestamp'),
                                            'download_url' => new external_value(PARAM_URL, 'File download URL'),
                                            'hash' => new external_value(PARAM_TEXT, 'File content hash'),
                                            'scorm_type' => new external_value(PARAM_TEXT, 'SCORM type (for SCORM files)', VALUE_OPTIONAL),
                                            'scorm_version' => new external_value(PARAM_TEXT, 'SCORM version (for SCORM files)', VALUE_OPTIONAL),
                                            'scorm_reference' => new external_value(PARAM_TEXT, 'SCORM reference (for SCORM files)', VALUE_OPTIONAL),
                                            'is_scorm_package' => new external_value(PARAM_BOOL, 'Is this a SCORM package', VALUE_OPTIONAL)
                                        ]), 'Files associated with this activity'
                                    ),
                                    'urls' => new external_multiple_structure(
                                        new external_single_structure([
                                            'url' => new external_value(PARAM_URL, 'The URL'),
                                            'display_type' => new external_value(PARAM_INT, 'Display type for URL activities', VALUE_OPTIONAL),
                                            'display_options' => new external_value(PARAM_TEXT, 'Display options', VALUE_OPTIONAL),
                                            'parameters' => new external_value(PARAM_TEXT, 'URL parameters', VALUE_OPTIONAL),
                                            'is_primary_url' => new external_value(PARAM_BOOL, 'Is this the primary URL for the activity'),
                                            'found_in' => new external_value(PARAM_TEXT, 'Where this URL was found')
                                        ]), 'URLs associated with this activity'
                                    ),
                                    'content_data' => self::get_content_data_structure()
                                ])
                            )
                        ])
                    )
                ])
            ),
            'pagination' => new external_single_structure([
                'total_courses' => new external_value(PARAM_INT, 'Total number of courses available'),
                'returned_courses' => new external_value(PARAM_INT, 'Number of courses in this response'),
                'offset' => new external_value(PARAM_INT, 'Starting offset for this page'),
                'limit' => new external_value(PARAM_INT, 'Maximum courses per page'),
                'has_more' => new external_value(PARAM_BOOL, 'Whether there are more courses available'),
                'next_offset' => new external_value(PARAM_INT, 'Offset for next page (null if no more)', VALUE_OPTIONAL)
            ]),
            'exported_at' => new external_value(PARAM_TEXT, 'Export timestamp')
        ]);
    }
}