<?php
// This file is part of Moodle - http://moodle.org/
namespace local_contentexport;

defined('MOODLE_INTERNAL') || die();

class export_service {

    /**
     * Clean HTML content
     * - removes OnlyOffice docData class attributes.
     *
     * @param string $content HTML content to clean
     * @return string Cleaned HTML content
     */
    private function clean_html_content($content) {
        if (empty($content)) {
            return $content;
        }

        // Remove class attributes that start with "docData;" (OnlyOffice formatting data)
        // This regex matches class="docData;..." with any content until the closing quote
        $content = preg_replace('/\s*class\s*=\s*"docData;[^"]*"/i', '', $content);

        return $content;
    }

    /**
     * Export course structure and content
     */
    public function export_course($course) {
        global $DB;

        // Get course category name
        $category = $DB->get_record('course_categories', ['id' => $course->category]);
        $categoryName = $category ? $category->name : 'Unknown';

        // Get course sections
        $sections = $this->get_course_sections($course->id);

        return [
            'course' => [
                'id' => (int)$course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'description' => $course->summary,
                'category' => $categoryName,
                'course_url' => $this->generate_course_url($course->id),
                'sections' => $sections
            ],
            'exported_at' => date('c') // ISO 8601 format
        ];
    }

    /**
     * Get course sections with activities
     */
    private function get_course_sections($courseid) {
        global $DB;

        $sections = [];
        $courseSections = $DB->get_records('course_sections', 
            ['course' => $courseid], 
            'section ASC'
        );

        foreach ($courseSections as $section) {
            $activities = $this->get_section_activities($section);
            
            $sections[] = [
                'id' => (int)$section->id,
                'name' => $section->name ?: "Section {$section->section}",
                'summary' => $this->clean_html_content($section->summary ?: ''),
                'section_number' => (int)$section->section,
                'section_url' => $this->generate_section_url($courseid, $section->section),
                'activities' => $activities
            ];
        }

        return $sections;
    }

    /**
     * Get activities in a section
     */
    private function get_section_activities($section) {
        global $DB;

        $activities = [];
        
        if (empty($section->sequence)) {
            return $activities;
        }

        $moduleIds = explode(',', $section->sequence);
        
        foreach ($moduleIds as $moduleId) {
            if (empty($moduleId)) {
                continue;
            }

            $courseModule = $DB->get_record('course_modules', ['id' => $moduleId]);
            if (!$courseModule) {
                continue;
            }

            $module = $DB->get_record('modules', ['id' => $courseModule->module]);
            if (!$module) {
                continue;
            }

            // Get the actual activity record
            $activity = $DB->get_record($module->name, ['id' => $courseModule->instance]);
            if (!$activity) {
                continue;
            }

            $activityData = [
                'id' => (int)$courseModule->id,
                'name' => $activity->name,
                'type' => $module->name,
                'description' => $this->clean_html_content(isset($activity->intro) ? $activity->intro : ''),
                'activity_url' => $this->generate_activity_url($courseModule->id, $module->name),
                'files' => $this->get_activity_files($courseModule, $module->name),
                'urls' => $this->get_activity_urls($courseModule, $module->name, $activity),
                'content_data' => $this->get_activity_content($courseModule, $module->name, $activity)
            ];

            $activities[] = $activityData;
        }

        return $activities;
    }

    /**
     * Generate course URL
     */
    private function generate_course_url($courseid) {
        global $CFG;
        return $CFG->wwwroot . '/course/view.php?id=' . $courseid;
    }

    /**
     * Generate section URL (course URL with section anchor)
     */
    private function generate_section_url($courseid, $sectionnumber) {
        global $CFG;
        $courseurl = $CFG->wwwroot . '/course/view.php?id=' . $courseid;
        
        // Add section anchor for non-zero sections
        if ($sectionnumber > 0) {
            $courseurl .= '#section-' . $sectionnumber;
        }
        
        return $courseurl;
    }

    /**
     * Generate activity URL
     */
    private function generate_activity_url($coursemoduleid, $modulename) {
        global $CFG;
        
        // Different modules may have different URL patterns
        switch ($modulename) {
            case 'url':
                return $CFG->wwwroot . '/mod/url/view.php?id=' . $coursemoduleid;
            case 'resource':
                return $CFG->wwwroot . '/mod/resource/view.php?id=' . $coursemoduleid;
            case 'folder':
                return $CFG->wwwroot . '/mod/folder/view.php?id=' . $coursemoduleid;
            case 'book':
                return $CFG->wwwroot . '/mod/book/view.php?id=' . $coursemoduleid;
            case 'page':
                return $CFG->wwwroot . '/mod/page/view.php?id=' . $coursemoduleid;
            case 'glossary':
                return $CFG->wwwroot . '/mod/glossary/view.php?id=' . $coursemoduleid;
            case 'assign':
                return $CFG->wwwroot . '/mod/assign/view.php?id=' . $coursemoduleid;
            case 'scorm':
                return $CFG->wwwroot . '/mod/scorm/view.php?id=' . $coursemoduleid;
            case 'quiz':
                return $CFG->wwwroot . '/mod/quiz/view.php?id=' . $coursemoduleid;
            default:
                return $CFG->wwwroot . '/mod/' . $modulename . '/view.php?id=' . $coursemoduleid;
        }
    }

    /**
     * Get files associated with an activity
     */
    private function get_activity_files($courseModule, $moduleName) {
        $files = [];

        // Handle different module types that contain files
        switch ($moduleName) {
            case 'resource':
                $files = $this->get_resource_files($courseModule);
                break;
            case 'folder':
                $files = $this->get_folder_files($courseModule);
                break;
            case 'assign':
                $files = $this->get_assignment_files($courseModule);
                break;
            case 'scorm':
                $files = $this->get_scorm_files($courseModule);
                break;
            case 'book':
                $files = $this->get_book_files($courseModule);
                break;
            case 'glossary':
                $files = $this->get_glossary_files($courseModule);
                break;
            case 'quiz':
                $files = $this->get_quiz_files($courseModule);
                break;
        }

        return $files;
    }

    /**
     * Get URLs associated with an activity
     */
    private function get_activity_urls($courseModule, $moduleName, $activity) {
        $urls = [];

        // Handle URL-specific modules
        switch ($moduleName) {
            case 'url':
                $urls = $this->get_url_activity_data($activity);
                break;
            default:
                // Extract URLs from activity descriptions/content
                $urls = $this->extract_urls_from_content($activity);
                break;
        }

        return $urls;
    }

    /**
     * Get data from URL activity module
     */
    private function get_url_activity_data($activity) {
        $urls = [];

        if (isset($activity->externalurl) && !empty($activity->externalurl)) {
            $urls[] = [
                'url' => $activity->externalurl,
                'display_type' => $activity->display ?? 0,
                'display_options' => $activity->displayoptions ?? '',
                'parameters' => $activity->parameters ?? '',
                'is_primary_url' => true,
                'found_in' => 'activity_url'
            ];
        }

        return $urls;
    }

    /**
     * Extract URLs from activity content
     */
    private function extract_urls_from_content($activity) {
        $urls = [];
        $content = '';

        // Collect text content from various fields
        if (isset($activity->intro)) {
            $content .= $activity->intro . ' ';
        }
        if (isset($activity->content)) {
            $content .= $activity->content . ' ';
        }
        if (isset($activity->description)) {
            $content .= $activity->description . ' ';
        }

        // Decode HTML entities first to handle &lt; &gt; etc.
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Extract URLs using regex
        $urlPattern = '/https?:\/\/[a-zA-Z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]+/i';
        preg_match_all($urlPattern, $content, $matches);

        foreach ($matches[0] as $url) {
            // Clean up the URL (remove trailing punctuation)
            $cleanUrl = rtrim($url, '.,;:!?)\'">');

            // Validate the URL is actually valid
            if (filter_var($cleanUrl, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $urls[] = [
                'url' => $cleanUrl,
                'display_type' => null,
                'display_options' => '',
                'parameters' => '',
                'is_primary_url' => false,
                'found_in' => 'content_text'
            ];
        }

        // Remove duplicates
        $urls = array_values(array_unique($urls, SORT_REGULAR));

        return $urls;
    }

    /**
     * Get content-specific data for activities (books, glossaries, etc.)
     */
    private function get_activity_content($courseModule, $moduleName, $activity) {
        $contentData = [];

        switch ($moduleName) {
            case 'book':
                $contentData = $this->get_book_content($activity);
                break;
            case 'glossary':
                $contentData = $this->get_glossary_content($activity);
                break;
            case 'page':
                $contentData = $this->get_page_content($activity);
                break;
            case 'quiz':
                $contentData = $this->get_quiz_content($activity, $courseModule);
                break;
        }

        return $contentData;
    }

    /**
     * Get quiz structure and results
     */
    private function get_quiz_content($quiz, $courseModule) {
        global $DB, $USER;

        $context = \context_module::instance($courseModule->id);
        
        // Determine if user can view all attempts or just their own
        $canViewAllAttempts = has_capability('mod/quiz:viewreports', $context);
        
        $quizData = [
            'type' => 'quiz',
            'quiz_id' => (int)$quiz->id,
            'time_limit' => $quiz->timelimit ? (int)$quiz->timelimit : null,
            'attempts_allowed' => (int)$quiz->attempts,
            'grading_method' => (int)$quiz->grademethod,
            'grade_to_pass' => isset($quiz->gradepass) ? (float)$quiz->gradepass : null,
            'questions' => $this->get_quiz_questions($quiz->id),
            'attempts' => $this->get_quiz_attempts($quiz->id, $canViewAllAttempts ? null : $USER->id)
        ];

        return $quizData;
    }

    /**
     * Get quiz questions structure
     */
    private function get_quiz_questions($quizid) {
        global $DB;

        $questions = [];
        
        // Get quiz slots (questions in the quiz)
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quizid], 'slot ASC');
        
        foreach ($slots as $slot) {
            $question = $DB->get_record('question', ['id' => $slot->questionid]);
            
            if ($question) {
                $questions[] = [
                    'slot' => (int)$slot->slot,
                    'question_id' => (int)$question->id,
                    'name' => $question->name,
                    'question_text' => $this->clean_html_content($question->questiontext),
                    'question_type' => $question->qtype,
                    'default_mark' => (float)$question->defaultmark,
                    'max_mark' => (float)$slot->maxmark
                ];
            }
        }

        return $questions;
    }

    /**
     * Get quiz attempts with detailed results
     */
    private function get_quiz_attempts($quizid, $userid = null) {
        global $DB;

        $params = ['quizid' => $quizid];
        $userFilter = '';
        
        if ($userid !== null) {
            $userFilter = ' AND quiza.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT quiza.id, quiza.userid, quiza.attempt, quiza.state, 
                       quiza.timestart, quiza.timefinish, quiza.timemodified,
                       quiza.sumgrades, quiza.uniqueid, qu.preferredbehaviour
                FROM {quiz_attempts} quiza
                JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                WHERE quiza.quiz = :quizid" . $userFilter . "
                ORDER BY quiza.userid, quiza.attempt";

        $attempts = $DB->get_records_sql($sql, $params);
        
        $attemptsData = [];
        foreach ($attempts as $attempt) {
            $user = $DB->get_record('user', ['id' => $attempt->userid], 'id, firstname, lastname, email');
            
            $attemptsData[] = [
                'attempt_id' => (int)$attempt->id,
                'user_id' => (int)$attempt->userid,
                'user_name' => $user ? fullname($user) : 'Unknown',
                'user_email' => $user ? $user->email : '',
                'attempt_number' => (int)$attempt->attempt,
                'state' => $attempt->state,
                'time_start' => $attempt->timestart ? (int)$attempt->timestart : null,
                'time_finish' => $attempt->timefinish ? (int)$attempt->timefinish : null,
                'time_modified' => (int)$attempt->timemodified,
                'grade' => $attempt->sumgrades !== null ? (float)$attempt->sumgrades : null,
                'behaviour' => $attempt->preferredbehaviour,
                'questions' => $this->get_attempt_questions($attempt->uniqueid)
            ];
        }

        return $attemptsData;
    }

    /**
     * Get detailed question attempts for a quiz attempt
     */
    private function get_attempt_questions($questionusageid) {
        global $DB;

        $questions = [];
        
        // First get all question attempts
        $questionAttempts = $DB->get_records('question_attempts', 
            ['questionusageid' => $questionusageid], 
            'slot ASC'
        );

        foreach ($questionAttempts as $qa) {
            $questionData = [
                'attempt_id' => (int)$qa->id,
                'slot' => (int)$qa->slot,
                'question_id' => (int)$qa->questionid,
                'variant' => (int)$qa->variant,
                'max_mark' => (float)$qa->maxmark,
                'min_fraction' => (float)$qa->minfraction,
                'flagged' => (bool)$qa->flagged,
                'question_summary' => $qa->questionsummary,
                'right_answer' => $qa->rightanswer,
                'response_summary' => $qa->responsesummary,
                'behaviour' => $qa->behaviour,
                'steps' => []
            ];

            // Get steps for this question attempt
            $steps = $DB->get_records('question_attempt_steps', 
                ['questionattemptid' => $qa->id], 
                'sequencenumber ASC'
            );

            foreach ($steps as $step) {
                // Get step data
                $stepData = $DB->get_records('question_attempt_step_data', 
                    ['attemptstepid' => $step->id]
                );
                
                $stepDataArray = [];
                foreach ($stepData as $data) {
                    $stepDataArray[$data->name] = $data->value;
                }

                $questionData['steps'][] = [
                    'sequence' => (int)$step->sequencenumber,
                    'state' => $step->state,
                    'fraction' => $step->fraction !== null ? (float)$step->fraction : null,
                    'time_created' => (int)$step->timecreated,
                    'user_id' => $step->userid ? (int)$step->userid : null,
                    'data' => !empty($stepDataArray) ? json_encode($stepDataArray) : null
                ];
            }

            $questions[] = $questionData;
        }

        return $questions;
    }

    /**
     * Get book chapters and content
     */
    private function get_book_content($activity) {
        global $DB;

        $chapters = $DB->get_records('book_chapters', 
            ['bookid' => $activity->id], 
            'pagenum ASC'
        );

        $bookData = [
            'type' => 'book',
            'chapters' => []
        ];

        foreach ($chapters as $chapter) {
            $bookData['chapters'][] = [
                'id' => (int)$chapter->id,
                'title' => $chapter->title,
                'content' => $this->clean_html_content($chapter->content),
                'pagenum' => (int)$chapter->pagenum,
                'subchapter' => (bool)$chapter->subchapter,
                'hidden' => (bool)$chapter->hidden
            ];
        }

        return $bookData;
    }

    /**
     * Get glossary entries and definitions
     */
    private function get_glossary_content($activity) {
        global $DB;

        $entries = $DB->get_records('glossary_entries', 
            ['glossaryid' => $activity->id, 'approved' => 1], 
            'concept ASC'
        );

        $glossaryData = [
            'type' => 'glossary',
            'entries' => []
        ];

        foreach ($entries as $entry) {
            $glossaryData['entries'][] = [
                'id' => (int)$entry->id,
                'concept' => $entry->concept,
                'definition' => $this->clean_html_content($entry->definition),
                'author' => $entry->userid,
                'timecreated' => $entry->timecreated,
                'timemodified' => $entry->timemodified
            ];
        }

        return $glossaryData;
    }

    /**
     * Get page content
     */
    private function get_page_content($activity) {
        return [
            'type' => 'page',
            'content' => $this->clean_html_content($activity->content ?? '')
        ];
    }

    /**
     * Get files from quiz module
     */
    private function get_quiz_files($courseModule) {
        $context = \context_module::instance($courseModule->id);
        return $this->extract_files_from_context($context, 'mod_quiz', 'intro');
    }

    /**
     * Get files from book module
     */
    private function get_book_files($courseModule) {
        $context = \context_module::instance($courseModule->id);
        return $this->extract_files_from_context($context, 'mod_book', 'chapter');
    }

    /**
     * Get files from glossary module  
     */
    private function get_glossary_files($courseModule) {
        $context = \context_module::instance($courseModule->id);
        $introFiles = $this->extract_files_from_context($context, 'mod_glossary', 'intro');
        $entryFiles = $this->extract_files_from_context($context, 'mod_glossary', 'attachment');
        
        return array_merge($introFiles, $entryFiles);
    }

    /**
     * Get files from resource module
     */
    private function get_resource_files($courseModule) {
        $context = \context_module::instance($courseModule->id);
        return $this->extract_files_from_context($context, 'mod_resource', 'content');
    }

    /**
     * Get files from folder module
     */
    private function get_folder_files($courseModule) {
        $context = \context_module::instance($courseModule->id);
        return $this->extract_files_from_context($context, 'mod_folder', 'content');
    }

    /**
     * Get files from assignment module
     */
    private function get_assignment_files($courseModule) {
        $context = \context_module::instance($courseModule->id);
        return $this->extract_files_from_context($context, 'mod_assign', 'intro');
    }

    /**
     * Get files from SCORM module
     */
    private function get_scorm_files($courseModule) {
        global $DB;
        
        $context = \context_module::instance($courseModule->id);
        $files = $this->extract_files_from_context($context, 'mod_scorm', 'package');
        
        // Add SCORM-specific metadata
        $scorm = $DB->get_record('scorm', ['id' => $courseModule->instance]);
        if ($scorm) {
            foreach ($files as &$file) {
                $file['scorm_type'] = $scorm->scormtype ?? 'local';
                $file['scorm_version'] = $scorm->version ?? 'unknown';
                $file['scorm_reference'] = $scorm->reference ?? '';
                $file['is_scorm_package'] = true;
            }
        }
        
        return $files;
    }

    /**
     * Extract files from a given context and component
     */
    private function extract_files_from_context($context, $component, $filearea) {
        global $CFG;
        
        $fs = get_file_storage();
        $files = [];

        $contextFiles = $fs->get_area_files($context->id, $component, $filearea, false, 'filename', false);

        foreach ($contextFiles as $file) {
            if ($file->is_directory()) {
                continue;
            }

            // Generate web service download URL
            $downloadUrl = $this->generate_webservice_file_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );

            $files[] = [
                'id' => $file->get_id(),
                'filename' => $file->get_filename(),
                'filepath' => $file->get_filepath(),
                'filesize' => $file->get_filesize(),
                'mimetype' => $file->get_mimetype(),
                'timecreated' => $file->get_timecreated(),
                'timemodified' => $file->get_timemodified(),
                'download_url' => $downloadUrl,
                'hash' => $file->get_contenthash()
            ];
        }

        return $files;
    }

    /**
     * Generate web service file download URL
     * 
     * @param int $contextid File context ID
     * @param string $component Component name (e.g., 'mod_resource')
     * @param string $filearea File area (e.g., 'content')
     * @param int $itemid Item ID
     * @param string $filepath File path
     * @param string $filename File name
     * @return string Web service compatible URL
     */
    private function generate_webservice_file_url($contextid, $component, $filearea, $itemid, $filepath, $filename) {
        global $CFG;
        
        // Build the file path arguments
        $pathargs = [
            $contextid,
            $component,
            $filearea
        ];
        
        if ($itemid !== null) {
            $pathargs[] = $itemid;
        }
        
        // Clean and add filepath
        $filepath = ltrim($filepath, '/');
        if (!empty($filepath)) {
            $pathargs[] = $filepath;
        }
        
        // URL encode the filename to handle spaces and special characters
        $pathargs[] = rawurlencode($filename);
        
        // Join path components
        $relativepath = implode('/', $pathargs);
        
        // Create web service pluginfile URL
        return $CFG->wwwroot . '/webservice/pluginfile.php/' . $relativepath;
    }
}