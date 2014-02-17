<?php

// This file is NOT part of Moodle - http://moodle.org/
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
if (!empty($CFG->multiroot) && !defined('CLI_SCRIPT')){

	if (!empty($CFG->allowmultirootdomains)){
		$domains = explode(',', $CFG->allowmultirootdomains);
		if (!in_array($_SERVER['HTTP_HOST'], $domains)){
			echo '<span class="color:red;font-size:1.3em">Unauthorized multiroot host</span>';
		}
	}

	$CFG->wwwroot   = 'http://'.$_SERVER['HTTP_HOST']; // Multi host routing
}

function multiroot_theme_override_hook(){
	global $CFG, $HOSTS_THEMES;
	
	$hostname = $_SERVER['HTTP_HOST'];
		
	if (isset($HOSTS_THEMES) && array_key_exists($hostname, $HOSTS_THEMES)){
		if (!is_dir($CFG->dirroot.'/theme/'.$HOSTS_THEMES[$hostname])){
			print_error('badthemename', 'local_multiroot');
		}
		$CFG->theme = $HOSTS_THEMES[$hostname]; 
		
		// $CFG->theme = $HOSTS_THEMES[$hostname];
	}
}