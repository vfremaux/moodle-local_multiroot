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
 * @package    local_multiroot
 * @category   local
 * @author     Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright  2010 onwards Valery Fremaux <valery.fremaux@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

if (!defined('MOODLE_EARLY_INTERNAL')) {
    defined('MOODLE_INTERNAL') || die();
}

if (!empty($CFG->multiroot) && !defined('CLI_SCRIPT')) {

    if (!empty($CFG->allowmultirootdomains)) {

        // Avoid collision with VMoodle hosts.
        if ((@$CFG->mainwwwroot == $CFG->wwwroot) || !isset($CFG->mainwwwroot)) {
            $domains = explode(',', $CFG->allowmultirootdomains);
            if (!in_array($_SERVER['HTTP_HOST'], $domains)) {
                echo '<span class="color:red;font-size:1.3em">Unauthorized multiroot host</span>';
            } else {
                $CFG->wwwroot = 'http://'.$_SERVER['HTTP_HOST']; // Multi host routing.
            }
        }
    }
}

function multiroot_theme_override_hook() {
    global $CFG;

    if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
        return;
    }

    $hostname = $_SERVER['HTTP_HOST'];

    $switchtheme = isset($CFG->hosts_themes) && array_key_exists($hostname, $CFG->hosts_themes);

    if ($switchtheme) {
        if (!is_dir($CFG->dirroot.'/theme/'.$CFG->hosts_themes[$hostname])) {
            print_error('badthemename', 'local_multiroot');
        }
        $CFG->theme = $CFG->hosts_themes[$hostname];
    }
}