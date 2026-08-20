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
 * External function local_ws_enrolcohort_get_instances.
 *
 * @package     local_ws_enrolcohort
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2018 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ws_enrolcohort\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ws_enrolcohort\responses;
use local_ws_enrolcohort\tools;
use local_ws_enrolcohort\exceptions\course_is_site_exception;
use local_ws_enrolcohort\exceptions\course_not_found_exception;

/**
 * Gets the cohort enrolment instances for a course or the entire site.
 *
 * @package     local_ws_enrolcohort
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2018 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_instances extends base {

    /**
     * Returns description of the execute() function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            self::QUERYSTRING_COURSE => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The id of a course to get enrolment instances for.', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Returns description of the execute() function return value.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::webservice_function_enrolment_instance_returns();
    }

    /**
     * Gets the cohort enrolment instances for a course or the entire site.
     *
     * @param array $course The details of the course to get the cohort enrolment instances for.
     *                      An id of -1 gets the instances of all courses.
     * @return array The webservice response.
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    public static function execute(array $course): array {
        global $DB, $SITE;

        // Check the parameters.
        $params = self::validate_parameters(self::execute_parameters(), [self::QUERYSTRING_COURSE => $course]);

        $extradata = [];

        // Get the courseid aka id.
        $courseid = $params[self::QUERYSTRING_COURSE]['id'];

        // Validate the courseid.
        if ($courseid == $SITE->id) {
            throw new course_is_site_exception();
        } else if ($courseid != self::GET_INSTANCES_COURSEID_ALL && !$DB->record_exists('course', ['id' => $courseid])) {
            throw new course_not_found_exception($courseid);
        }

        // Validate the context and check the users capabilities to ensure that they can do this.
        if ($courseid == self::GET_INSTANCES_COURSEID_ALL) {
            // Getting the instances of all courses requires the capabilities at system level.
            $context = \context_system::instance();
        } else {
            $context = \context_course::instance($courseid);
        }

        self::validate_context($context);
        require_capability('moodle/course:enrolconfig', $context);
        require_capability('enrol/cohort:config', $context);

        // The id of the site course. Its enrolment instances are never included.
        $siteid = get_site()->id;

        // Get the cohort enrolment instances without loading the whole course table.
        $sql = "SELECT e.id, e.name, e.status, e.roleid, e.courseid, e.customint1, e.customint2
                  FROM {enrol} e
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol AND e.courseid <> :siteid";
        $sqlparams = ['enrol' => 'cohort', 'siteid' => $siteid];

        if ($courseid != self::GET_INSTANCES_COURSEID_ALL) {
            $sql .= " AND e.courseid = :courseid";
            $sqlparams['courseid'] = $courseid;
        }

        $numberofenrolmentmethods = 0;

        $enrolmentinstances = $DB->get_recordset_sql($sql, $sqlparams);

        foreach ($enrolmentinstances as $enrolmentinstance) {
            // Make some info about the enrolment instance.
            $ei = new responses\enrol(
                $enrolmentinstance->id,
                'enrol',
                $enrolmentinstance->name,
                $enrolmentinstance->status,
                $enrolmentinstance->roleid,
                $enrolmentinstance->courseid,
                $enrolmentinstance->{self::FIELD_COHORT},
                $enrolmentinstance->{self::FIELD_GROUP}
            );

            // Get other details about the enrolment instance.
            $ei->set_course((new responses\course($enrolmentinstance->courseid))->to_array());
            $ei->set_cohort((new responses\cohort($enrolmentinstance->{self::FIELD_COHORT}))->to_array());
            $ei->set_role((new responses\role($enrolmentinstance->roleid))->to_array());

            // Made an object for this so it doesn't exceed 132 characters.
            $groupresponse = new responses\group(
                $enrolmentinstance->{self::FIELD_GROUP}, 'group', $enrolmentinstance->courseid
            );

            $ei->set_group($groupresponse->to_array());

            $extradata[] = $ei->to_array();

            $numberofenrolmentmethods += 1;
        }

        $enrolmentinstances->close();

        // Count things and make langstring placeholders. In "all courses" mode the number of courses
        // keeps the historical semantics: the number of courses examined, excluding the site course.
        if ($courseid == self::GET_INSTANCES_COURSEID_ALL) {
            $numberofcourses = $DB->count_records_select('course', 'id <> ?', [$siteid]);
        } else {
            $numberofcourses = 1;
        }

        $a = [
            'courseid'                      => $courseid,
            'numberofenrolmentinstances'    => $numberofenrolmentmethods,
            'numberofcourses'               => $numberofcourses,
        ];

        if ($courseid == self::GET_INSTANCES_COURSEID_ALL) {
            $message = tools::get_string('getinstances:200', $a);
        } else {
            $message = tools::get_string('getinstance:200', $a);
        }

        // Prepare the response.
        $response = [
            'id'        => $courseid,
            'code'      => 200,
            'message'   => $message,
            'data'      => $extradata,
        ];

        return $response;
    }
}
