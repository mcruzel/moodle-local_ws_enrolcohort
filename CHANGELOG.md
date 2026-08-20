# Moodle Webservices for Cohort Enrolment CHANGELOG.

Version history:

v3.3.2 (20180600)

* Added additional check in php unit test delete instance to ensure that the cohort enrolment instance was removed.
* Implemented privacy API.

v3.3.3 (2019042900) for Moodle 3.3, 3.4, 3.5, and 3.6. Released Monday, 29 April 2019

* Fixed issue that was preventing a cohort enrolment instance being added to a course.
    * This was happening for sites that had more than 25 cohorts available at a context. This limit is a default value when calling cohort_get_available_cohorts().
    * Thanks to Thomas (thoschi) for this fix.
* Updated unit tests to create 100 cohorts before calling the webservice add_instance() function.
* Tagged this release.

v4.0.0 (2026082000) for Moodle 4.5 LTS up to Moodle 5.2. Released Thursday, 20 August 2026

* Migrated the external functions from the legacy lib/externallib.php API to the \core_external API (required for Moodle 5.x).
    * The functions now live in the classes `\local_ws_enrolcohort\external\add_instance`, `update_instance`, `delete_instance` and `get_instances` (method `execute`); the legacy externallib.php file and its single external class were removed.
    * The external function names, parameters and return structures are unchanged, so existing webservice clients keep working.
* Security hardening: added `validate_context()` and capability checks (`moodle/course:enrolconfig` and `enrol/cohort:config`) to update_instance, delete_instance and get_instances (at course level, or system level when getting the instances of all courses).
* Bug fixes:
    * add_instance no longer silently overwrites a valid provided `status` with the default value; an invalid status now always raises an error.
    * update_instance no longer references an undefined "create group" constant of enrol_cohort (latent fatal error when creating a new group during an update); it now uses the plugin's own constant.
    * update_instance can now reset the instance name by passing an empty `name` (an absent name still keeps the current one).
* Performance: get_instances no longer loads the whole course table; it queries the cohort enrolment instances directly using a recordset.
* CLI upgrade script updated for Moodle 5.x (removed the include of the cron library file deleted in Moodle 5.0, moved to the namespaced progress trace classes, dropped the error reporting constant deprecated in PHP 8.4).
* Dropped support for Moodle versions below 4.5 (plugin now requires Moodle 4.5 LTS, supported up to Moodle 5.2).