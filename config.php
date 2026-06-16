<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'root';
$CFG->dbpass    = '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

$CFG->wwwroot   = 'http://localhost/moodle';
$CFG->dataroot  = 'C:\\laragon\\moodledata';
$CFG->admin     = 'admin';

// Local development email settings.
$CFG->noreplyaddress = 'noreply@example.test';
$CFG->supportemail = 'support@example.test';
$CFG->supportname = 'Moodle Local';
$CFG->noemailever = true;

$CFG->directorypermissions = 0777;

// PHPUnit test configuration.
$CFG->phpunit_prefix   = 'phpu_';
$CFG->phpunit_dataroot = 'C:\\laragon\\moodledata_phpunit';

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
