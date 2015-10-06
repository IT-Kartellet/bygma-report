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
 * Bygma reports
 *
 * @package    report
 * @subpackage bygma
 */

require('../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once("{$CFG->libdir}/gradelib.php");
require_once("{$CFG->dirroot}/report/bygma/lib/itk_completionlib.php");

define('COMPLETION_REPORT_PAGE', 25);

// Get course
$id = required_param('course',PARAM_INT);
$course = $DB->get_record('course',array('id'=>$id));
if (!$course) {
    print_error('invalidcourseid');
}
$context = context_course::instance($course->id);

// Sort (default lastname, optionally firstname)
$sort = optional_param('sort','',PARAM_ALPHA);
$firstnamesort = $sort == 'firstname';

// CSV format
$format = optional_param('format','',PARAM_ALPHA);
$excel = $format == 'excelcsv';
$csv = $format == 'csv' || $excel;

// Paging
$start   = optional_param('start', 0, PARAM_INT);
$sifirst = optional_param('sifirst', 'all', PARAM_NOTAGS);
$silast  = optional_param('silast', 'all', PARAM_NOTAGS);
$start   = optional_param('start', 0, PARAM_INT);

// Whether to show extra user identity information
$extrafields = get_extra_user_fields($context);
$leftcols = 1 + count($extrafields);

function csv_quote($value) {
    global $excel;
    if ($excel) {
        return core_text::convert('"'.str_replace('"',"'",$value).'"','UTF-8','UTF-16LE');
    } else {
        return '"'.str_replace('"',"'",$value).'"';
    }
}

$url = new moodle_url('/report/bygma/index.php', array('course'=>$id));
if ($sort !== '') {
    $url->param('sort', $sort);
}
if ($format !== '') {
    $url->param('format', $format);
}
if ($start !== 0) {
    $url->param('start', $start);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');

require_login($course);

// Check basic permission
require_capability('report/bygma:view',$context);

// Get group mode
$group = groups_get_course_group($course,true); // Supposed to verify group
if ($group===0 && $course->groupmode==SEPARATEGROUPS) {
    require_capability('moodle/site:accessallgroups',$context);
}

// Get data on activities and progress of all users, and give error if we've
// nothing to display (no users or no activities)
$reportsurl = $CFG->wwwroot.'/course/report.php?id='.$course->id;
$completion = new itk_completion_lib($course);
$activities = $completion->get_activities();

// Generate where clause
$where = array();
$where_params = array();

if ($sifirst !== 'all') {
    $where[] = $DB->sql_like('u.firstname', ':sifirst', false);
    $where_params['sifirst'] = $sifirst.'%';
}

if ($silast !== 'all') {
    $where[] = $DB->sql_like('u.lastname', ':silast', false);
    $where_params['silast'] = $silast.'%';
}

// Get user match count
$total = $completion->get_num_tracked_users(implode(' AND ', $where), $where_params, $group);

// Total user count
$grandtotal = $completion->get_num_tracked_users('', array(), $group);

// Get user data
$progress = array();

if ($total) {
    $progress = $completion->get_progress_all(
        implode(' AND ', $where),
        $where_params,
        $group,
        $firstnamesort ? 'u.firstname ASC' : 'u.lastname ASC',
        $csv ? 0 : COMPLETION_REPORT_PAGE,
        $csv ? 0 : $start,
        $context
    );
}

if ($csv && $grandtotal && count($activities)>0) { // Only show CSV if there are some users/actvs

    $shortname = format_string($course->shortname, true, array('context' => $context));
    header('Content-Disposition: attachment; filename=progress.'.
        preg_replace('/[^a-z0-9-]/','_',core_text::strtolower(strip_tags($shortname))).'.csv');
    // Unicode byte-order mark for Excel
    if ($excel) {
        header('Content-Type: text/csv; charset=UTF-16LE');
        print chr(0xFF).chr(0xFE);
        $sep="\t".chr(0);
        $line="\n".chr(0);
    } else {
        header('Content-Type: text/csv; charset=UTF-8');
        $sep=",";
        $line="\n";
    }
} else {

    // Navigation and header
    $strreports = get_string("reports");
    $strcompletion = get_string('activitycompletion', 'completion');

    $PAGE->set_title($strcompletion);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    $PAGE->requires->js('/report/bygma/textrotate.js');
    $PAGE->requires->js_function_call('textrotate_init', null, true);

    // Handle groups (if enabled)
    groups_print_course_menu($course,$CFG->wwwroot.'/report/bygma/?course='.$course->id);
}

if (count($activities)==0) {
    echo $OUTPUT->container(get_string('err_noactivities', 'completion'), 'errorbox errorboxcontent');
    echo $OUTPUT->footer();
    exit;
}

// If no users in this course what-so-ever
if (!$grandtotal) {
    echo $OUTPUT->container(get_string('err_nousers', 'completion'), 'errorbox errorboxcontent');
    echo $OUTPUT->footer();
    exit;
}

// Build link for paging
$link = $CFG->wwwroot.'/report/bygma/?course='.$course->id;
if (strlen($sort)) {
    $link .= '&amp;sort='.$sort;
}
$link .= '&amp;start=';

$pagingbar = '';

// Do we need a paging bar?
if ($total > COMPLETION_REPORT_PAGE) {

    // Paging bar
    $pagingbar .= '<div class="paging">';
    $pagingbar .= get_string('page').': ';

    $sistrings = array();
    if ($sifirst != 'all') {
        $sistrings[] =  "sifirst={$sifirst}";
    }
    if ($silast != 'all') {
        $sistrings[] =  "silast={$silast}";
    }
    $sistring = !empty($sistrings) ? '&amp;'.implode('&amp;', $sistrings) : '';

    // Display previous link
    if ($start > 0) {
        $pstart = max($start - COMPLETION_REPORT_PAGE, 0);
        $pagingbar .= "(<a class=\"previous\" href=\"{$link}{$pstart}{$sistring}\">".get_string('previous').'</a>)&nbsp;';
    }

    // Create page links
    $curstart = 0;
    $curpage = 0;
    while ($curstart < $total) {
        $curpage++;

        if ($curstart == $start) {
            $pagingbar .= '&nbsp;'.$curpage.'&nbsp;';
        } else {
            $pagingbar .= "&nbsp;<a href=\"{$link}{$curstart}{$sistring}\">$curpage</a>&nbsp;";
        }

        $curstart += COMPLETION_REPORT_PAGE;
    }

    // Display next link
    $nstart = $start + COMPLETION_REPORT_PAGE;
    if ($nstart < $total) {
        $pagingbar .= "&nbsp;(<a class=\"next\" href=\"{$link}{$nstart}{$sistring}\">".get_string('next').'</a>)';
    }

    $pagingbar .= '</div>';
}

// Okay, let's draw the table of progress info,

// Start of table
if (!$csv) {
    print '<br class="clearer"/>'; // ugh

    print $pagingbar;

    if (!$total) {
        echo $OUTPUT->heading(get_string('nothingtodisplay'));
        echo $OUTPUT->footer();
        exit;
    }

    print '<div id="completion-progress-wrapper" class="no-overflow">';
    print '<table id="completion-progress" class="generaltable flexible boxaligncenter" style="text-align:left"><thead><tr style="vertical-align:top">';

    //User initials header
    print html_writer::tag('th', get_string('table_header_initials', 'report_bygma'), array('class' => 'completion-identifyfield', 'scope' => 'col'));

    // User heading / sort option
    print '<th scope="col" class="completion-sortchoice">';

    $sistring = "&amp;silast={$silast}&amp;sifirst={$sifirst}";

    if ($firstnamesort) {
        print
            get_string('firstname')." / <a href=\"./?course={$course->id}{$sistring}\">".
            get_string('lastname').'</a>';
    } else {
        print "<a href=\"./?course={$course->id}&amp;sort=firstname{$sistring}\">".
            get_string('firstname').'</a> / '.
            get_string('lastname');
    }
    print '</th>';

    //Department
    print html_writer::tag('th', get_string('table_header_department', 'report_bygma'), array('class' => 'completion-identifyfield', 'scope' => 'col'));
} else {
    foreach ($extrafields as $field) {
        echo $sep . csv_quote(get_user_field_name($field));
    }
}

//Passed Header
print html_writer::tag('th', get_string('table_header_passed', 'report_bygma'), array('class' => 'completion-identifyfield', 'scope' => 'col'));

//Grade Header
print html_writer::tag('th', get_string('table_aggregation_passed_percentage', 'report_bygma'), array('class' => 'completion-identifyfield', 'scope' => 'col'));

if ($csv) {
    print $line;
} else {
    print '</tr></thead><tbody>';
}

$modules_info = array();

foreach($activities as $activity){
    $grading_info = grade_get_grades($course->id, 'mod', $activity->modname, $activity->instance, array_keys($progress));
    if(!array_key_exists($activity->modname, $modules_info)){
        $modules_info[$activity->modname] = array();
    }
    if(!array_key_exists($activity->instance, $modules_info[$activity->modname])){
        $modules_info[$activity->modname][$activity->instance] = $grading_info;
    }
}

//Total number of users who completed the whole course
$total_users_completed = 0;

// Row for each user
foreach($progress as $user) {

    print html_writer::start_tag('tr');

    //User initials - have to be extracted from the email field since Bygma's ADFS does not hold initials elsewhere
    $mail_split = explode('@', $user->email);
    print html_writer::tag('td', $mail_split[0], array('class' => 'completion-progresscell'));

    // User name
    if ($csv) {
        print csv_quote(fullname($user));
        foreach ($extrafields as $field) {
            echo $sep . csv_quote($user->{$field});
        }
    } else {
        print '<th scope="row"><a href="'.$CFG->wwwroot.'/user/view.php?id='.
            $user->id.'&amp;course='.$course->id.'">'.fullname($user).'</a></th>';
    }

    //Department - They prefix every department with 'Bygma' - therefore strip it
    $department_split = explode(' ', $user->institution);
    print html_writer::tag('td', count($department_split) == 2 ? $department_split[1] : $user->institution, array('class' => 'completion-progresscell'));

    $count_completed = 0;
    $count_total = count($activities);

    // Progress for each activity
    foreach($activities as $activity) {
        // Get progress information and state
        if (array_key_exists($activity->id, $user->progress)) {
            $thisprogress = $user->progress[$activity->id];
            if ($thisprogress->completionstate == COMPLETION_COMPLETE || $thisprogress->completionstate == COMPLETION_COMPLETE_PASS) {
                $count_completed++;
            }
        }
    }

    if($count_completed == $count_total){
        $total_users_completed++;
    }

    //Passed - whether user passed or not
    print html_writer::tag('td', $count_completed == $count_total ? get_string('yes') : get_string('no'), array('completion-progresscell'));

    //Score - proportion between incomplete and completed modules
    print html_writer::tag('td', (round(($count_completed / $count_total) * 100) . '%'), array('completion-progresscell'));

    if ($csv) {
        print $line;
    } else {
        print '</tr>';
    }
}

if ($csv) {
    exit;
}
print '</tbody></table>';
print '</div>';
print $pagingbar;

print html_writer::start_tag('table', array('class' => 'table-aggregation generaltable flexible boxaligncenter'));

print html_writer::start_tag('tr');
print html_writer::tag('th', get_string('table_aggregation_total_users', 'report_bygma'), array('class' => 'completion-identifyfield'));
print html_writer::tag('td', $grandtotal);
print html_writer::end_tag('tr');

print html_writer::start_tag('tr');
print html_writer::tag('th', get_string('table_aggregation_users_passed', 'report_bygma'), array('class' => 'completion-identifyfield'));
print html_writer::tag('td', $total_users_completed, array('class' => 'completion-progresscell'));
print html_writer::end_tag('tr');

print html_writer::start_tag('tr');
print html_writer::tag('th', get_string('table_aggregation_passed_percentage', 'report_bygma'), array('class' => 'completion-identifyfield'));
print html_writer::tag('td', (round($total_users_completed / $grandtotal * 100)) . '%', array('class' => 'completion-progresscell'));
print html_writer::end_tag('tr');

print html_writer::start_tag('tr');
print html_writer::tag('th', get_string('table_aggregation_average_grade', 'report_bygma'), array('class' => 'completion-identifyfield'));
print html_writer::tag('td', (calculate_average_grade($modules_info, $grandtotal) . '%'), array('class' => 'completion-progresscell'));
print html_writer::end_tag('tr');

print html_writer::end_tag('table');

echo $OUTPUT->footer();

