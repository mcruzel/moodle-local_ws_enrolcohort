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
 * Classy locallib but not locallib for local_ws_enrolcohort.
 *
 * @package     local_ws_enrolcohort
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2018 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ws_enrolcohort;

// No direct access.
defined('MOODLE_INTERNAL') || die();

/**
 * Helper class with shortcuts to this plugins language strings and settings.
 *
 * @package     local_ws_enrolcohort
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2018 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tools {
    /**
     * The plugins component name.
     */
    const COMPONENT_NAME = 'local_ws_enrolcohort';

    /**
     * Gets a language string of this plugin.
     *
     * @param string $identifier The language string identifier.
     * @param mixed $a Optional placeholder value/s for the language string.
     * @return string The language string.
     * @throws \coding_exception
     */
    public static function get_string($identifier = '', $a = null) {
        return get_string($identifier, self::COMPONENT_NAME, $a);
    }

    /**
     * Access this plugins settings.
     *
     * @param null $settingname     Omitting this parameter returns all plugin settings.
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_config($settingname = null) {
        return get_config(self::COMPONENT_NAME, $settingname);
    }
}