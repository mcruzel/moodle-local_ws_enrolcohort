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
 * Version file for local_ws_enrolcohort.
 *
 * @package     local_ws_enrolcohort
 * @author      Donald Barrett <donald.barrett@learningworks.co.nz>
 * @copyright   2018 onwards, LearningWorks ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No direct access.
defined('MOODLE_INTERNAL') || die();

// This plugin requires Moodle 4.5 LTS.
$plugin->requires = 2024100700;

// The branches this plugin has been tested against: Moodle 4.5 up to Moodle 5.2.
$plugin->supported = [405, 502];

// Plugin details.
$plugin->component  = 'local_ws_enrolcohort';
$plugin->version    = 2026082000;   // Plugin updated August 20, 2026.
$plugin->release    = 'v4.0.0';

// Plugin status details.
$plugin->maturity = MATURITY_STABLE;   // ALPHA, BETA, RC, STABLE.
