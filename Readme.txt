This is the documentation of the Moodle multiroot tweak, allowing a moodle to be operated
through several domain identities

Installation
======================

Add this package to the "local" directory of your Moodle installation.

Add following keys to your config.php 

$CFG->multiroot = true;
$CFG->allowmultirootdomains = ''; // comma separated list of possible domain wwwroots
$CFG->originalwwwroot   = ''; // the original domain name 

At the end of your configuration file, manage the local/multiroot/lib.php file be loaded properly and add the following call : 

#include_once $CFG->dirroot.'/local/multiroot/lib.php';
multiroot_theme_override_hook();

Multiroot at the moment does not support HTTPS, but changing keeps being simple.