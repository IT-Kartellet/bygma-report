<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains functions used by the progress report
 *
 * @package    report
 * @subpackage progress
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_bygma_extend_navigation_course($navigation, $course, $context) {
    global $CFG, $OUTPUT;

    require_once($CFG->libdir.'/completionlib.php');

    $showonnavigation = has_capability('report/bygma:view', $context);
    $group = groups_get_course_group($course,true); // Supposed to verify group
    if($group===0 && $course->groupmode==SEPARATEGROUPS) {
        $showonnavigation = ($showonnavigation && has_capability('moodle/site:accessallgroups', $context));
    }

    $completion = new completion_info($course);
    $showonnavigation = ($showonnavigation && $completion->is_enabled() && $completion->has_activities());
    if ($showonnavigation) {
        $url = new moodle_url('/report/bygma/index.php', array('course'=>$course->id));
        $navigation->add(get_string('pluginname','report_bygma'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function report_bygmapage_type_list($pagetype, $parentcontext, $currentcontext) {
    $array = array(
        '*'                     => get_string('page-x', 'pagetype'),
        'report-*'              => get_string('page-report-x', 'pagetype'),
        'report-bygma-*'        => get_string('page-report-progress-x',  'report_bygma'),
        'report-bygma-index'    => get_string('page-report-progress-index',  'report_bygma'),
    );
    return $array;
}

/**
 * Returns percentage of course completion (float)
 * @param $activities - all modules with enabled completion tracking for specific course
 * @param $general_completion_info - general info about each module completion
 * @param $detailed_completion_info - detailed info about module completions - especially quiz since we need to know exact percentage
 */
function resolve_completion_percentage($userid, $activities, $general_completion_info, $detailed_completion_info){
    $passed = true;
    $complete = 0;
    $incomplete = count($activities);

    foreach($activities as $activitie){
        if(array_key_exists($activitie->modname, $detailed_completion_info) &&
            array_key_exists($activitie->instance, $detailed_completion_info[$activitie->modname]) &&
            !empty($detailed_completion_info[$activitie->modname][$activitie->instance]['items']) &&
            array_key_exists($userid, $detailed_completion_info[$activitie->modname][$activitie->instance]['items'][0])){
            $grade_info = $detailed_completion_info[$activitie->modname][$activitie->instance]['items'][0][$userid];
            if(!empty($grade_info->grade)){
                $passed = $passed && ($grade_info->grade >= $detailed_completion_info[$activitie->modname][$activitie->instance]['items'][0]->gradepass);
                if($passed){
                    $complete++;
                }
            }
            else{
                $passed = false;
            }
        }

        else{
            // Get progress information and state
            if (array_key_exists($activitie->id, $general_completion_info)) {
                $thisprogress = $general_completion_info->progress[$activitie->id];
                $state = $thisprogress->completionstate;
                $date = userdate($thisprogress->timemodified);
            } else {
                $state = COMPLETION_INCOMPLETE;
                $date = '';
            }
        }
    }
}

function calculate_average_grade($modules_info, $user_count){
    $grade_count = 0;
    $activity_count = 0;

    foreach($modules_info as $module_type){
        foreach($module_type as $module_instance){
            if(!empty($module_instance->items)){
                $activity_count++;
                $grade_max = floatval($module_instance->items[0]->grademax - $module_instance->items[0]->grademin);
                foreach($module_instance->items[0]->grades as $grade_info){
                    if(!is_null($grade_info->grade)){
                        $grade_perc = $grade_max / $grade_info->grade * 100;
                        $grade_count += floatval($grade_perc);
                    }
                }
            }
        }
    }
    return ($grade_count / ($user_count * $activity_count));
}