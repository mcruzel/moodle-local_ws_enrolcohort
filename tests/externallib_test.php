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
 * Cohort enrolment webservice tests for local_ws_enrolcohort.
 *
 * @package     local_ws_enrolcohort
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2018 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ws_enrolcohort;

use local_ws_enrolcohort\external\add_instance;
use local_ws_enrolcohort\external\base;
use local_ws_enrolcohort\external\delete_instance;
use local_ws_enrolcohort\external\get_instances;
use local_ws_enrolcohort\external\update_instance;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/webservice/tests/helpers.php');

/**
 * Tests for the external functions of local_ws_enrolcohort.
 *
 * @package     local_ws_enrolcohort
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2018 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class externallib_test extends \externallib_advanced_testcase {

    /**
     * Creates a bunch of cohorts.
     *
     * @param int $numberofcohorts The number of cohorts to create.
     * @return void
     */
    private function create_cohorts(int $numberofcohorts = 100): void {
        for ($i = 0; $i < $numberofcohorts; $i++) {
            self::getDataGenerator()->create_cohort();
        }
    }

    /**
     * Test getting cohort enrolment instances for invalid courses, single courses and all courses.
     *
     * @covers \local_ws_enrolcohort\external\get_instances::execute
     * @return void
     */
    public function test_get_instances(): void {
        global $SITE;

        $this->resetAfterTest(true);

        // Test getting enrolment instances for the site course.
        try {
            get_instances::execute(['id' => $SITE->id]);
            $this->fail('Expected a course_is_site_exception to be thrown.');
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\course_is_site_exception::class, $exception);
            $this->assertEquals('invalidcourse', $exception->errorcode);
        }

        // Test getting enrolment instances for a course that doesn't exist.
        try {
            get_instances::execute(['id' => 999]);
            $this->fail('Expected a course_not_found_exception to be thrown.');
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\course_not_found_exception::class, $exception);
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        // Make courses and stuff for adding enrolment instances to.

        // The number of courses, and cohort enrolment instances to make.
        $numberofcoursestomake = 5;

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        // Storage for courseids.
        $courseids = [];

        $this->setAdminUser();

        for ($i = 0; $i < $numberofcoursestomake; $i++) {
            $course = self::getDataGenerator()->create_course();
            $cohort = self::getDataGenerator()->create_cohort();

            $roleid = self::getDataGenerator()->create_role();

            $courseid   = $course->id;
            $cohortid   = $cohort->id;

            $courseids[] = $courseid;

            // Add a cohort enrolment instance to a course.
            try {
                $wsenrolmentinstance = add_instance::execute([
                    'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
                ]);
            } catch (\moodle_exception $exception) {
                // This should never happen.
                $this->fail('An exception was caught ('.get_class($exception).').');
            }

            // The first call to add an enrolment instance should never be null.
            $this->assertNotNull($wsenrolmentinstance);

            // Validate and then get the enrol instance id.
            $this->assertArrayHasKey('id', $wsenrolmentinstance);
            $this->assertGreaterThan(0, $wsenrolmentinstance['id']);
        }

        // Add the id of the course to get all enrolment methods.
        $courseids[] = base::GET_INSTANCES_COURSEID_ALL;

        foreach ($courseids as $key => $courseid) {
            try {
                get_instances::execute(['id' => $courseid]);
            } catch (\moodle_exception $exception) {
                $this->fail('An unexpected exception was caught ('.get_class($exception).').');
            }
        }
    }

    /**
     * Test deleting missing and existing cohort enrolment instances.
     *
     * @covers \local_ws_enrolcohort\external\delete_instance::execute
     * @return void
     */
    public function test_delete_instance(): void {
        $this->resetAfterTest(true);

        $wsenrolmentinstance = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        // Add a cohort enrolment instance to a course.
        try {
            $wsenrolmentinstance = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // The first call to add an enrolment instance should never be null.
        $this->assertNotNull($wsenrolmentinstance);

        // Validate and then get the enrol instance id.
        $this->assertArrayHasKey('id', $wsenrolmentinstance);
        $this->assertGreaterThan(0, $wsenrolmentinstance['id']);

        $enrolmentinstanceid = $wsenrolmentinstance['id'];

        $wsdeleteinstanceresponse = null;

        // Delete an enrolment instance that doesn't exist.
        try {
            delete_instance::execute(['id' => 118]);
            $this->fail('Expected a cohort_enrol_instance_not_found exception to be thrown.');
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(
                \local_ws_enrolcohort\exceptions\cohort_enrol_instance_not_found_exception::class, $exception
            );
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        // Delete an enrolment instance that does exist.
        try {
            $wsdeleteinstanceresponse = delete_instance::execute(['id' => $enrolmentinstanceid]);
        } catch (\moodle_exception $exception) {
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        $enrolmentinstances = enrol_get_instances($course->id, false);
        $this->assertArrayNotHasKey($enrolmentinstanceid, $enrolmentinstances);

        $this->assertArrayHasKey('id', $wsdeleteinstanceresponse);
        $this->assertEquals($enrolmentinstanceid, $wsdeleteinstanceresponse['id']);

        $this->assertArrayHasKey('code', $wsdeleteinstanceresponse);
        $this->assertEquals(200, $wsdeleteinstanceresponse['code']);

        $this->assertArrayHasKey('message', $wsdeleteinstanceresponse);
        $this->assertEquals(tools::get_string('deleteinstance:200'), $wsdeleteinstanceresponse['message']);
    }

    /**
     * Test updating a cohort enrolment instance that doesn't exist.
     *
     * @covers \local_ws_enrolcohort\external\update_instance::execute
     * @return void
     */
    public function test_update_instance_enrol_instance_id_not_found(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        try {
            $response = update_instance::execute(['id' => 1]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(
                \local_ws_enrolcohort\exceptions\cohort_enrol_instance_not_found_exception::class, $exception
            );
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test updating a cohort enrolment instance with an invalid status.
     *
     * @covers \local_ws_enrolcohort\external\update_instance::execute
     * @return void
     */
    public function test_update_instance_invalid_status(): void {
        $this->resetAfterTest(true);

        $enrolmentinstance  = null;
        $response           = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;

        // Add a cohort enrolment instance to a course.
        try {
            $enrolmentinstance = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // The first call to add an enrolment instance should never be null.
        $this->assertNotNull($enrolmentinstance);

        // Validate and then get the enrol instance id.
        $this->assertArrayHasKey('id', $enrolmentinstance);
        $this->assertGreaterThan(0, $enrolmentinstance['id']);
        $enrolmentinstanceid = $enrolmentinstance['id'];

        try {
            // A status of 1000 is not a valid status.
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid, 'status' => 1000,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\invalid_status_exception::class, $exception);
            $this->assertEquals('invalidstatus', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test updating a cohort enrolment instance with a role that doesn't exist.
     *
     * @covers \local_ws_enrolcohort\external\update_instance::execute
     * @return void
     */
    public function test_update_instance_role_not_found(): void {
        $this->resetAfterTest(true);

        $enrolmentinstance  = null;
        $response           = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;

        // Add a cohort enrolment instance to a course.
        try {
            $enrolmentinstance = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // The first call to add an enrolment instance should never be null.
        $this->assertNotNull($enrolmentinstance);

        // Validate and then get the enrol instance id.
        $this->assertArrayHasKey('id', $enrolmentinstance);
        $this->assertGreaterThan(0, $enrolmentinstance['id']);
        $enrolmentinstanceid = $enrolmentinstance['id'];

        try {
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid, 'roleid' => 999,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\role_not_found_exception::class, $exception);
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test updating a cohort enrolment instance with a role that is not assignable at the course context.
     *
     * @covers \local_ws_enrolcohort\external\update_instance::execute
     * @return void
     */
    public function test_update_instance_role_not_assignable(): void {
        $this->resetAfterTest(true);

        $enrolmentinstance  = null;
        $response           = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;

        // Add a cohort enrolment instance to a course.
        try {
            $enrolmentinstance = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // The first call to add an enrolment instance should never be null.
        $this->assertNotNull($enrolmentinstance);

        // Validate and then get the enrol instance id.
        $this->assertArrayHasKey('id', $enrolmentinstance);
        $this->assertGreaterThan(0, $enrolmentinstance['id']);
        $enrolmentinstanceid = $enrolmentinstance['id'];

        $unassignableroleid = self::getDataGenerator()->create_role(['archetype' => 'frontpage']);

        try {
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid, 'roleid' => $unassignableroleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(
                \local_ws_enrolcohort\exceptions\role_not_assignable_at_context_exception::class, $exception
            );
            $this->assertEquals('unavailableatcontext', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test updating a cohort enrolment instance with a group that doesn't exist.
     *
     * @covers \local_ws_enrolcohort\external\update_instance::execute
     * @return void
     */
    public function test_update_instance_group_not_found(): void {
        $this->resetAfterTest(true);

        $enrolmentinstance  = null;
        $response           = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;

        // Add a cohort enrolment instance to a course.
        try {
            $enrolmentinstance = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // The first call to add an enrolment instance should never be null.
        $this->assertNotNull($enrolmentinstance);

        // Validate and then get the enrol instance id.
        $this->assertArrayHasKey('id', $enrolmentinstance);
        $this->assertGreaterThan(0, $enrolmentinstance['id']);
        $enrolmentinstanceid = $enrolmentinstance['id'];

        try {
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid, 'groupid' => 1234,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\group_not_found_exception::class, $exception);
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test updating a cohort enrolment instance without giving anything to update.
     *
     * @covers \local_ws_enrolcohort\external\update_instance::execute
     * @return void
     */
    public function test_update_instance_nochange(): void {
        $this->resetAfterTest(true);

        $enrolmentinstance  = null;
        $response           = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;

        // Add a cohort enrolment instance to a course.
        try {
            $enrolmentinstance = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // The first call to add an enrolment instance should never be null.
        $this->assertNotNull($enrolmentinstance);

        // Validate and then get the enrol instance id.
        $this->assertArrayHasKey('id', $enrolmentinstance);
        $this->assertGreaterThan(0, $enrolmentinstance['id']);
        $enrolmentinstanceid = $enrolmentinstance['id'];

        try {
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($enrolmentinstanceid, $response['id']);

        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(200, $response['code']);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals(tools::get_string('updateinstance:nochange'), $response['message']);

        $this->assertArrayHasKey('data', $response);
        $this->assertEquals(1, count($response['data']));
    }

    /**
     * Test updating the role, group, name and status of a cohort enrolment instance.
     *
     * @covers \local_ws_enrolcohort\external\update_instance::execute
     * @return void
     */
    public function test_update_instance(): void {
        global $DB;

        $this->resetAfterTest(true);

        $enrolmentinstance  = null;
        $response           = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;

        // Add a cohort enrolment instance to a course.
        try {
            $enrolmentinstance = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // The first call to add an enrolment instance should never be null.
        $this->assertNotNull($enrolmentinstance);

        // Validate and then get the enrol instance id.
        $this->assertArrayHasKey('id', $enrolmentinstance);
        $this->assertGreaterThan(0, $enrolmentinstance['id']);
        $enrolmentinstanceid = $enrolmentinstance['id'];

        // Change the role.
        $newroleid = self::getDataGenerator()->create_role();

        try {
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid, 'roleid' => $newroleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($enrolmentinstanceid, $response['id']);

        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(200, $response['code']);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals(tools::get_string('updateinstance:200'), $response['message']);

        // Check roleid.
        $this->assertEquals($DB->get_field('enrol', 'roleid', ['id' => $enrolmentinstanceid]), $newroleid);

        // Change the group.
        $newgroup = self::getDataGenerator()->create_group(['courseid' => $courseid]);

        try {
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid, 'groupid' => $newgroup->id,
            ]);
        } catch (\moodle_exception $exception) {
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($enrolmentinstanceid, $response['id']);

        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(200, $response['code']);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals(tools::get_string('updateinstance:200'), $response['message']);

        // Check groupid.
        $currentgroupid = $DB->get_field('enrol', base::FIELD_GROUP, ['id' => $enrolmentinstanceid]);
        $this->assertEquals($currentgroupid, $newgroup->id);

        // Change the name.
        $newname = 'This is a brand new name';

        try {
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid, 'name' => $newname,
            ]);
        } catch (\moodle_exception $exception) {
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($enrolmentinstanceid, $response['id']);

        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(200, $response['code']);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals(tools::get_string('updateinstance:200'), $response['message']);

        // Check name updation.
        $this->assertEquals($DB->get_field('enrol', 'name', ['id' => $enrolmentinstanceid]), $newname);

        // Change the status.
        $newstatus = ENROL_INSTANCE_DISABLED;

        try {
            $response = update_instance::execute([
                'id' => $enrolmentinstanceid, 'status' => $newstatus,
            ]);
        } catch (\moodle_exception $exception) {
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($enrolmentinstanceid, $response['id']);

        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(200, $response['code']);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals(tools::get_string('updateinstance:200'), $response['message']);

        // Check status.
        $this->assertEquals($DB->get_field('enrol', 'status', ['id' => $enrolmentinstanceid]), $newstatus);
    }

    /**
     * Test adding a cohort enrolment instance without a group.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_success_without_group(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid = $course->id;
        $cohortid = $cohort->id;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // Validate the response code.
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(201, $response['code']);

        // Validate the response message.
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals(tools::get_string('addinstance:201'), $response['message']);

        // Validate the response data.
        $responsedatahas = [];

        foreach ($response['data'] as $key => $responsedata) {
            $this->assertArrayHasKey('object', $responsedata);

            if (!$responsedata['object'] == 'data') {
                $this->assertArrayHasKey('id', $responsedata);
            }

            $responsedatahas[$responsedata['object']] = $key;
        }

        $this->assertArrayHasKey('course', $responsedatahas);
        $this->assertArrayHasKey('cohort', $responsedatahas);
        $this->assertArrayHasKey('role', $responsedatahas);
        $this->assertArrayHasKey('data', $responsedatahas);
        $this->assertArrayHasKey('group', $responsedatahas);
        $this->assertEquals(0, $response['data'][$responsedatahas['group']]['id']);
        $this->assertArrayHasKey('enrol', $responsedatahas);
        $this->assertEquals(6, count($response['data']));

        // Validate the response id.
        $this->assertArrayHasKey('id', $response);
        $this->assertNotEquals(-1, $response['id']);
    }

    /**
     * Test adding a cohort enrolment instance that creates a new group.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_success_with_group_new(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        $course     = self::getDataGenerator()->create_course();
        $cohort     = self::getDataGenerator()->create_cohort();
        $roleid     = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;
        $groupid    = base::COHORT_GROUP_CREATE_NEW;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid, 'groupid' => $groupid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // Validate the response code.
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(201, $response['code']);

        // Validate the response message.
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals(tools::get_string('addinstance:201'), $response['message']);

        // Validate the response data.
        $this->assertArrayHasKey('data', $response);

        $responsedatahas = [];

        foreach ($response['data'] as $key => $responsedata) {
            $this->assertArrayHasKey('object', $responsedata);

            if (!$responsedata['object'] == 'data') {
                $this->assertArrayHasKey('id', $responsedata);
            }

            $responsedatahas[$responsedata['object']] = $key;
        }

        $this->assertArrayHasKey('course', $responsedatahas);
        $this->assertArrayHasKey('cohort', $responsedatahas);
        $this->assertArrayHasKey('role', $responsedatahas);
        $this->assertArrayHasKey('data', $responsedatahas);
        $this->assertArrayHasKey('group', $responsedatahas);
        $this->assertGreaterThan(0, $response['data'][$responsedatahas['group']]['id']);
        $this->assertArrayHasKey('enrol', $responsedatahas);
        $this->assertEquals(6, count($response['data']));

        // Validate the response id.
        $this->assertArrayHasKey('id', $response);
        $this->assertNotEquals(-1, $response['id']);
    }

    /**
     * Test adding a cohort enrolment instance that uses an existing group.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_success_with_group_existing(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();
        $group  = self::getDataGenerator()->create_group(['courseid' => $course->id]);

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;
        $groupid    = $group->id;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid, 'groupid' => $groupid,
            ]);
        } catch (\moodle_exception $exception) {
            // This should never happen.
            $this->fail('An unexpected exception was caught ('.get_class($exception).').');
        }

        // Validate the response code.
        $this->assertArrayHasKey('code', $response);
        $this->assertEquals(201, $response['code']);

        // Validate the response message.
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals(tools::get_string('addinstance:201'), $response['message']);

        // Validate the response data.
        $this->assertArrayHasKey('data', $response);

        $responsedatahas = [];

        foreach ($response['data'] as $key => $responsedata) {
            $this->assertArrayHasKey('object', $responsedata);

            if (!$responsedata['object'] == 'data') {
                $this->assertArrayHasKey('id', $responsedata);
            }

            $responsedatahas[$responsedata['object']] = $key;
        }

        $this->assertArrayHasKey('course', $responsedatahas);
        $this->assertArrayHasKey('cohort', $responsedatahas);
        $this->assertArrayHasKey('role', $responsedatahas);
        $this->assertArrayHasKey('data', $responsedatahas);
        $this->assertArrayHasKey('group', $responsedatahas);
        $this->assertEquals($groupid, $response['data'][$responsedatahas['group']]['id']);
        $this->assertArrayHasKey('enrol', $responsedatahas);
        $this->assertEquals(6, count($response['data']));

        // Validate the response id.
        $this->assertArrayHasKey('id', $response);
        $this->assertNotEquals(-1, $response['id']);
    }

    /**
     * Test adding a cohort enrolment instance with a group that belongs to another course.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_success_with_group_existing_invalid_course(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        $differentcourse = self::getDataGenerator()->create_course();
        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();
        $group  = self::getDataGenerator()->create_group(['courseid' => $differentcourse->id]);

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;
        $groupid    = $group->id;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid, 'groupid' => $groupid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\group_not_found_exception::class, $exception);
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance where the cohort is already synced with the role.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_enrol_instance_already_synced_with_role(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid = $course->id;
        $cohortid = $cohort->id;

        try {
            // Simulate an instance where a cohort enrolment instance would be added to a course that already has one.
            add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);

            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(
                \local_ws_enrolcohort\exceptions\cohort_enrol_instance_already_synced_with_role_exception::class, $exception
            );
            $this->assertEquals('enrolcohortalreadysyncedwithrole', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance with an invalid status.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_invalid_status(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        // Make a course category so we have a context.
        $coursecategory = self::getDataGenerator()->create_category();

        $course = self::getDataGenerator()->create_course(['category' => $coursecategory->id]);
        $cohort = self::getDataGenerator()->create_cohort(['contextid' => $coursecategory->get_context()->id]);
        $roleid = self::getDataGenerator()->create_role();
        $status = 55;

        $courseid = $course->id;
        $cohortid = $cohort->id;

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid, 'status' => $status,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\invalid_status_exception::class, $exception);
            $this->assertEquals('invalidstatus', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test calling the add_instance webservice function missing the required parameters.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_missing_required_params(): void {
        // The invalid params to test with.
        $courseid   = 0;
        $roleid     = 0;
        $cohortid   = 0;

        $response = null;

        try {
            $response = add_instance::execute(['courseid' => $courseid]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $exception);
            $this->assertEquals('invalidparameter', $exception->errorcode);
        }

        $this->assertNull($response);

        try {
            $response = add_instance::execute(['cohortid' => $cohortid]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $exception);
            $this->assertEquals('invalidparameter', $exception->errorcode);
        }

        $this->assertNull($response);

        try {
            $response = add_instance::execute(['roleid' => $roleid]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $exception);
            $this->assertEquals('invalidparameter', $exception->errorcode);
        }

        $this->assertNull($response);

        try {
            $response = add_instance::execute(['courseid' => $courseid, 'cohortid' => $cohortid]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $exception);
            $this->assertEquals('invalidparameter', $exception->errorcode);
        }

        $this->assertNull($response);

        try {
            $response = add_instance::execute(['courseid' => $courseid, 'roleid' => $roleid]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $exception);
            $this->assertEquals('invalidparameter', $exception->errorcode);
        }

        $this->assertNull($response);

        try {
            $response = add_instance::execute(['cohortid' => $cohortid, 'roleid' => $roleid]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $exception);
            $this->assertEquals('invalidparameter', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance to a course that doesn't exist.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_course_not_found(): void {
        $courseid = $cohortid = $roleid = 0;

        $response = null;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\course_not_found_exception::class, $exception);
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance with a cohort that doesn't exist.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_cohort_not_found(): void {
        $this->resetAfterTest();

        $response = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();

        $courseid   = $course->id;
        $cohortid   = 0;
        $roleid     = 0;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\cohort_not_found_exception::class, $exception);
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance with a role that doesn't exist.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_role_not_found(): void {
        $this->resetAfterTest();

        $response = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;
        $roleid     = 0;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\role_not_found_exception::class, $exception);
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance with a group that doesn't exist.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_group_not_found(): void {
        global $DB;

        $this->resetAfterTest();

        $response = null;

        $this->setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();
        $role   = $DB->get_record('role', ['id' => $roleid]);

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid   = $course->id;
        $cohortid   = $cohort->id;
        $roleid     = $role->id;
        $groupid    = 999;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid, 'groupid' => $groupid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\group_not_found_exception::class, $exception);
            $this->assertEquals('objectnotfound', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance to the site course.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_site_course(): void {
        global $SITE;

        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        $cohort = self::getDataGenerator()->create_cohort();
        $roleid = self::getDataGenerator()->create_role();

        $courseid = $SITE->id;
        $cohortid = $cohort->id;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(\local_ws_enrolcohort\exceptions\invalid_course_exception::class, $exception);
            $this->assertEquals('invalidcourse', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance with a cohort that isn't available at the course context.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_cohort_unavailable(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        // Make a course category so we have a context.
        $coursecategory = self::getDataGenerator()->create_category();

        $course = self::getDataGenerator()->create_course();
        $cohort = self::getDataGenerator()->create_cohort(['contextid' => $coursecategory->get_context()->id]);
        $roleid = self::getDataGenerator()->create_role();

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid = $course->id;
        $cohortid = $cohort->id;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(
                \local_ws_enrolcohort\exceptions\cohort_not_available_at_context_exception::class, $exception
            );
            $this->assertEquals('unavailableatcontext', $exception->errorcode);
        }

        $this->assertNull($response);
    }

    /**
     * Test adding a cohort enrolment instance with a role that is not assignable at the course context.
     *
     * @covers \local_ws_enrolcohort\external\add_instance::execute
     * @return void
     */
    public function test_add_instance_role_unassignable(): void {
        $this->resetAfterTest(true);

        $response = null;

        $this->setAdminUser();

        // Make a course category so we have a context.
        $coursecategory = self::getDataGenerator()->create_category();

        $course = self::getDataGenerator()->create_course(['category' => $coursecategory->id]);
        $cohort = self::getDataGenerator()->create_cohort(['contextid' => $coursecategory->get_context()->id]);
        $roleid = self::getDataGenerator()->create_role(['archetype' => 'frontpage']);

        // Ensure lots of cohorts exist to test the cohort_get_available_cohorts limit is working.
        $this->create_cohorts();

        $courseid = $course->id;
        $cohortid = $cohort->id;

        try {
            $response = add_instance::execute([
                'courseid' => $courseid, 'cohortid' => $cohortid, 'roleid' => $roleid,
            ]);
        } catch (\moodle_exception $exception) {
            $this->assertInstanceOf(
                \local_ws_enrolcohort\exceptions\role_not_assignable_at_context_exception::class, $exception
            );
            $this->assertEquals('unavailableatcontext', $exception->errorcode);
        }

        $this->assertNull($response);
    }
}
