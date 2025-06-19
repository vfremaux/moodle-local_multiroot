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
 * This script allows to do backup.
 *
 * @package    core
 * @subpackage cli
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CLI_VMOODLE_PRECHECK;

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
$CLI_VMOODLE_PRECHECK = true; // force first config to be minimal

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/lib/clilib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array(
    'host' => false,
    'simulate' => false,
    'output' => false,
    'checkmail' => false,
    'remotedb' => false,
    'remoteprefix' => false,
    'help' => false,
    ), 
    array(
        'h' => 'help',
        's' => 'simulate',
        'O' => 'output',
        'm' => 'checkmail',
        'd' => 'remotedb',
        'p' => 'remoteprefix',
        'H' => 'host')
    );

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("{$unrecognized} are unrecognized options\n");
}

if (!empty($options['help'])) {
    $help = "
Perform backup of the given course.

Options:
    -H, --host=URL              Host to proceeed for.
    -s, --simulate              Tells what will be done.
    -O, --output                Output to file.
    -m, --checkmail             If set, checks mail identity and stops on mail mismatch error (by username).
    -d, --remotedb              Name of the remote database where to import users from.
    -p, --remoteprefix          Table prefix of the remote db where to import users from.
    -h, --help                  Print out this help.

Example:
\$sudo -u www-data /usr/bin/php local/multiroot/cli/transport_users.php [--simulate] [--output=/tmp/output.log] [--host=mymoodle.moodledomain.com]\n
";

    echo $help;
    die;
}

if (!empty($options['host'])) {
    // Arms the vmoodle switching.
    echo('Arming for '.$options['host']."\n"); // mtrace not yet available.
    define('CLI_VMOODLE_OVERRIDE', $options['host']);
}

// Replay full config whenever. If vmoodle switch is armed, will switch now config.

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
echo('Config check : playing for '.$CFG->wwwroot."\n");

/**
 * Basic rules for users transportation.
 *
 * Users from CNCEF override E users
 * Match on firstname/lastname/email will : 
 * change login, copy password value
 * turn on copied or confirmed users to MNET
 *
 */

global $edb;
global $eprefix;
$edb = 'moodle36_sumatra_cncef_qualif';
if (!empty($options['remotedb'])) {
    $edb = $options['remotedb'];
}
$eprefix = 'cncef_';
if (!empty($options['remoteprefix'])) {
    $eprefix = $options['remoteprefix'];
}

$eusersql = "
    SELECT
        *
    FROM
        `{$edb}`.{$eprefix}user
    WHERE
        deleted = 0 AND
        suspended = 0 AND
        username <> 'admin' AND
        username <> 'guest' AND
        auth = 'email' AND
        id >= 7
";

$euserfieldssql = "
    SELECT
        CONCAT(uif.name, '-', uid.userid) as pkey,
        uif.name,
        uid.userid,
        uid.data
    FROM
        `{$edb}`.{$eprefix}user_info_field uif,
        `{$edb}`.{$eprefix}user_info_data uid
    WHERE
        uid.fieldid = uif.id
";

// maps remote ids to local ids
$useridmapping = [];

$allremoteusers = $DB->get_records_sql($eusersql);

if (empty($allremoteusers)) {
    die("No users found in remote source");
}

global $LOG;
$LOG = null;
if (!empty($options['output'])) {
    if (!$LOG = fopen($options['output'], 'w')) {
        die('Could not open output file at '.$options['output']."\n");
    }
}

$simulating = '';
if (!empty($options['simulate'])) {
    $simulating = "SIMUL ";
}

$remoteuserfields = $DB->get_records_sql($euserfieldssql);
$report = '';

$remoteusercount = count($allremoteusers);
print_out("Starting user match on {$remoteusercount}\n");

$counters = [];
foreach ($allremoteusers as $ru) {

    print_out("Processing user {$ru->username} ... ");

    // Find potential match by username
    $params = ['username' => $ru->username];
    $usernamematch = $DB->get_record('user', $params);

    if (!empty($usernamematch)) {
        print_out("matched locally by username ... ");

        if ($usernamematch->email != $ru->email && !empty($options['checkmail'])) {
            print_out("Email mismatch exception. Username matches but not email : local {$usernamematch->email}, remote :  {$ru->email}\n");
            die();
        }

        $usernamematch->password = $ru->password;
        $usernamematch->idnumber = 'CNCEF'.$ru->id;
        $usernamematch->institution = 'CNCEF';
        $usernamematch->email = $ru->email;
        $counters['u'] = @$counters['u'] + 1;

        if (empty($simulating)) {
            $DB->update_record('user', $usernamematch);
            print_out("{$simulating}Username matches. Just update password, and idnumber\n");
            change_remote_auth($ru->id);
        }
        $useridmapping[$ru->id] = $usernamematch->id;

    } else {
        // Find potential match by name
        $params = ['firstname' => $ru->firstname, 'lastname' => $ru->lastname, 'email' => $ru->email];
        $match = $DB->get_record('user', $params);

        if (!empty($match)) {
            print_out("matched locally by name ... ");
            // We have a match. Check username for reporting.
            if ($match->username == $ru->username) {
                print_out("{$simulating}Name matchs, Username matchs. Just update password\n");
            } else {
                print_out("{$simulating}Name matchs, Username did not match (local : $match->username, remote : $ru->username). Changing username\n");
                $match->username = $ru->username;
            }
            $match->password = $ru->password;
            $match->idnumber = 'CNCEF'.$ru->id;
            $match->institution = 'CNCEF';
            $counters['u'] = @$counters['u'] + 1;

            if (empty($simulating)) {
                $DB->update_record('user', $match);
                change_remote_auth($ru->id);
            }
            $useridmapping[$ru->id] = $match->id;
        } else {
            print_out("NOT matched locally ... try by mail ");
            // Not a match but check email if is used by another account
            $params = ['email' => $ru->email];
            $mailmatch = $DB->get_record('user', $params);
            if (!empty($mailmatch)) {
                // We have a mail collision with another firstname. WE may NOT integrate.
                print_out("{$simulating}Other user shares email (local : $mailmatch->username, remote : $ru->username). Skipping\n");
                $useridmapping[$ru->id] = $mailmatch->id;

                $mailmatch->email = $ru->email;
                $mailmatch->idnumber = 'CNCEF'.$ru->id;
                $mailmatch->password = $ru->password;
                $mailmatch->institution = 'CNCEF';
                $counters['u'] = @$counters['u'] + 1;

                if (empty($simulating)) {
                    $DB->update_record('user', $mailmatch);
                    change_remote_auth($ru->id);
                }
                $useridmapping[$ru->id] = $mailmatch->id;

            } else {
                // Recheck for a firstname/lastname match.
                print_out("NOT matched locally ... try by first/last name ");
                $params = ['firstname' => $ru->firstname, 'lastname' => $ru->lastname];
                $namematch = $DB->get_record('user', $params);
                if (!empty($namematch)) {
                    // We have a mail collision with another firstname. WE may NOT integrate.
                    print_out("{$simulating}Other user with this name other email (local : $namematch->username, remote : $ru->username). Changing username and email\n");

                    $namematch->username = $ru->username;
                    $namematch->email = $ru->email;
                    $namematch->idnumber = 'CNCEF'.$ru->id;
                    $namematch->password = $ru->password;
                    $namematch->institution = 'CNCEF';
                    $counters['u'] = @$counters['u'] + 1;

                    if (empty($simulating)) {
                        $DB->update_record('user', $namematch);
                        change_remote_auth($ru->id);
                    }
                    $useridmapping[$ru->id] = $namematch->id;
                } else {
                    print_out("NOT matched by any mean. Just creating new user.");
                    if (!empty($ru)) {
                        $counters['c'] = @$counters['c'] + 1;
                        if (empty($simulating)) {
                            $u = clone($ru);
                            $u->idnumber = 'CNCEF'.$ru->id;
                            $u->institution = 'CNCEF';
                            unset($u->id);
                            $u->id = $DB->insert_record('user', $u);
                            change_remote_auth($ru->id);
                            $useridmapping[$ru->id] = $u->id;
                        }
                    }
                }
            }
        }
    }
}

// Final step : resync all registered old ids with profile field values.
$localfields = $DB->get_records('user_info_field', []);

if (!empty($useridmapping)) {
    print_out("\nAdjusting profile fields\n\n");
    foreach ($useridmapping as $oldid => $newid) {
        print_out("Adjusting oldid $oldid => newid => $newid \n");
        foreach ($localfields as $f) {
            $fieldkey = $f->name.'-'.$oldid;
            if (array_key_exists($fieldkey, $remoteuserfields)) {
                $params = ['fieldid' => $f->id, 'userid' => $newid];
                if ($fieldvalue = $DB->get_record('user_info_data', $params)) {
                    $targetdata = $remoteuserfields[$fieldkey]->data;
                    $targetdata = preg_replace("/\\r?\\n/", '', $targetdata); // Remove end of lines
                    print_out("\t{$simulating}Adjusting field $f->name to {$targetdata} \n");
                    $fieldvalue->data = $remoteuserfields[$fieldkey]->data;
                    if (empty($simulating)) {
                        $DB->update_record('user_info_data', $fieldvalue);
                    }
                }
            }
        }
    }
}

$allprocessed = 0 + @$counters['u'] + @$counters['c'];
print_out("Processed : ". $allprocessed."\n");
print_out("Updated : ".@$counters['u']." Created : ".@$counters['c']."\n");
print_out("All done !\n");

function change_remote_auth($remoteid) {
    global $DB;
    static $echangauthsql;
    global $edb;
    global $eprefix;

    if (empty($echangauthsql)) {
        $echangauthsql = "
    UPDATE
        `{$edb}`.{$eprefix}user
    SET
        auth = 'mnet'
    WHERE
        id = ?
";
    }

    $DB->execute($echangauthsql, [$remoteid]);
}

function print_out($msg) {
    global $LOG;

    echo $msg;
    debug_trace($msg);
    if (!is_null($LOG)) {
        fputs($LOG, $msg);
    }
}