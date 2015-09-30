<?php

    require_once("{$CFG->libdir}/completionlib.php");

    class itk_completion_lib extends completion_info{

        public function get_tracked_users($where = '', $whereparams = array(), $groupid = 0,
                                          $sort = '', $limitfrom = '', $limitnum = '', context $extracontext = null) {
            global $DB;

            list($enrolledsql, $params) = get_enrolled_sql(
                context_course::instance($this->course_id),
                'moodle/course:isincompletionreports', $groupid, true);

            $allusernames = get_all_user_name_fields(true, 'u');
            $sql = 'SELECT u.id, u.idnumber, u.institution, ' . $allusernames;
            if ($extracontext) {
                $sql .= get_extra_user_fields_sql($extracontext, 'u', '', array('idnumber'));
            }
            $sql .= ' FROM (' . $enrolledsql . ') eu JOIN {user} u ON u.id = eu.id';

            if ($where) {
                $sql .= " AND $where";
                $params = array_merge($params, $whereparams);
            }

            if ($sort) {
                $sql .= " ORDER BY $sort";
            }

            return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        }
    }
