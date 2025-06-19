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

/**
 * This function is not implemented in this plugin, but is needed to mark
 * the vf documentation custom volume availability.
 */
function local_multiroot_supports_feature() {
    assert(1);
}

/**
 * Boots an alternate wwwroot related to the multiroot accepted domains.
 */
function multiroot_boot_hook() {
    global $CFG, $ME;

    if (!empty($CFG->multiroot) &&
            !defined('CLI_SCRIPT') &&
                    !empty($CFG->allowmultirootdomains)) {

        // echo "We are multirooting ";
        $domains = explode(',', $CFG->allowmultirootdomains);

        if (count($domains) > 1 ) {
            // Note : at this pint we are before setup and most Moodle globals are not working.
            // We liberalize access to font resources when in multiroot. This may help caches to provide
            // font even when comming from another alias.
            if (preg_match($_SERVER['SCRIPT_FILENAME'], $ME)) {
                header('Access-Control-Allow-Origin: *');
            }
        }

        $protocol = ($_SERVER['SERVER_PORT'] == '443') ? 'https' : 'http';

        // host that is required in query.
        $host = $_SERVER['HTTP_HOST'];

        if (!in_array($host, $domains)) {
            // If required host NOT in allowed multiroot list.
            // Moodle standard libs are not yet loaded. print_error not accessible.
            echo "<span class=\"color:red;font-size:1.3em\">Unauthorized multiroot host {$host}</span>";
            die;
        }

        // adapts the visible wwwroot.
        $CFG->wwwroot = $protocol.'://'.$host;

        if (!empty($CFG->multiroot_mainhost_prefixes)) {
            if (array_key_exists($host, $CFG->multiroot_mainhost_prefixes)) {
                $CFG->mainhostprefix = $CFG->multiroot_mainhost_prefixes[$host];
            }
        }

        // Force some core configs for the domain.
        if (!empty($CFG->multiroot_config_overrides)) {
            if (array_key_exists($host, $CFG->multiroot_config_overrides)) {
                foreach ($CFG->multiroot_config_overrides[$host] as $cfg => $value) {
                    $CFG->$cfg = $value;
                }
            }
        }

        // Force some plugins configs for the domain.
        if (!empty($CFG->multiroot_plugins_config_overrides)) {
            if (array_key_exists($host, $CFG->multiroot_plugins_config_overrides)) {
                foreach ($CFG->multiroot_plugins_config_overrides[$host] as $plugin => $pconfig) {
                    foreach ($pconfig as $cfg => $value) {
                        $CFG->forced_plugin_settings[$plugin][$cfg] = $value;
                    }
                }
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