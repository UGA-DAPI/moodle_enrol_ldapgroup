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
 * LDAP enrolment plugin implementation.
 *
 * This plugin synchronises enrolment and roles with a LDAP server.
 *
 * @package    enrol_ldapgroup
 * @author     Iñaki Arenaza - based on code by Martin Dougiamas, Martin Langhoff and others
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @copyright  2014 Fabrice Menard <fabrice.menard@upmf-grenoble.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_ldapgroup_plugin extends enrol_plugin {
    protected $enrol_localcoursefield = 'idnumber';
    protected $enroltype = 'enrol_ldapgroup';
    protected $errorlogtag = '[ENROL LDAPGROUP] ';
    protected $userfields;
    /**
     * Constructor for the plugin. In addition to calling the parent
     * constructor, we define and 'fix' some settings depending on the
     * real settings the admin defined.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->libdir.'/ldaplib.php');

        if (is_enabled_auth('cas')) {
            $this->authtype = 'cas';
            $this->roleauth = 'auth_cas';

        } else if (is_enabled_auth('ldap')){
            $this->authtype = 'ldap';
            $this->roleauth = 'auth_ldap';

        } else {
            error_log('[SYNCH COHORTS] ' . get_string('pluginnotenabled', 'auth_ldap'));
            die;
        }
        // Do our own stuff to fix the config (it's easier to do it
        // here than using the admin settings infrastructure). We
        // don't call $this->set_config() for any of the 'fixups'
        // (except the objectclass, as it's critical) because the user
        // didn't specify any values and relied on the default values
        // defined for the user type she chose.
        $this->auth=get_auth_plugin($this->authtype);
        $this->userfields=$this->auth->ldap_attributes();
        $this->load_config();

        // Make sure we get sane defaults for critical values.
        $this->config->ldapencoding = $this->get_config('ldapencoding', 'utf-8');
        $this->config->user_type = $this->get_config('user_type', 'default');

        $ldap_usertypes = ldap_supported_usertypes();
        $this->config->user_type_name = $ldap_usertypes[$this->config->user_type];
        unset($ldap_usertypes);

        $default = ldap_getdefaults();
        // Remove the objectclass default, as the values specified there are for
        // users, and we are dealing with groups here.
        unset($default['objectclass']);

        // Use defaults if values not given. Dont use this->get_config()
        // here to be able to check for 0 and false values too.
        foreach ($default as $key => $value) {
            // Watch out - 0, false are correct values too, so we can't use $this->get_config()
            if (!isset($this->config->{$key}) or $this->config->{$key} == '') {
                $this->config->{$key} = $value[$this->config->user_type];
            }
        }

        $objectclass=array('group_objectclass','user_objectclass');
        foreach ($objectclass  as $object){
            if (empty($this->config->{$object})) {
            // Can't send empty filter. Fix it for now and future occasions
                $this->set_config($object, '(objectClass=*)');
            } else if (stripos($this->config->{$object}, 'objectClass=') === 0) {
            // Value is 'objectClass=some-string-here', so just add ()
            // around the value (filter _must_ have them).
            // Fix it for now and future occasions
                $this->set_config($object, '('.$this->config->{$object}.')');
            } else if (stripos($this->config->{$object}, '(') !== 0) {
            // Value is 'some-string-not-starting-with-left-parentheses',
            // which is assumed to be the objectClass matching value.
            // So build a valid filter with it.
                $this->set_config($object, '(objectClass='.$this->config->{$object}.')');
            } else {
            // There is an additional possible value
            // '(some-string-here)', that can be used to specify any
            // valid filter string, to select subsets of users based
            // on any criteria. For example, we could select the users
            // whose objectClass is 'user' and have the
            // 'enabledMoodleUser' attribute, with something like:
            //
            //   (&(objectClass=user)(enabledMoodleUser=1))
            //
            // In this particular case we don't need to do anything,
            // so leave $this->config->objectclass as is.
            }
        }
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {

        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/ldapgroup:config', $context)) {
            return NULL;
        }
        // Multiple instances supported - multiple parent courses linked.
        return new moodle_url('/enrol/ldapgroup/edit.php', array('courseid'=>$courseid));
    }



    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'ldapgroup') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/ldapgroup:config', $context)) {
            $editlink = new moodle_url("/enrol/ldapgroup/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    array('class' => 'iconsmall')));
        }

        return $icons;
    }




    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function instance_deleteable($instance) {
        if (!enrol_is_enabled('ldapgroup')) {
            return true;
        }

        if (!$this->get_config('ldap_host') or !$this->get_config('group_objectclass') or !$this->get_config('idnumber_attribute')) {
            return true;
        }

        // TODO: connect to external system and make sure no users are to be enrolled in this course
        return false;
    }

    /**
     * Forces synchronisation of user enrolments with LDAP server.
     * It creates courses if the plugin is configured to do so.
     *
     * @param object $user user record
     * @return void
     */
    public function sync_user_enrolments($user) {
        global $DB;

        if ($this->config->login_sync) {
        // Do not try to print anything to the output because this method is called during interactive login.
        $trace = new error_log_progress_trace($this->errorlogtag);

        if (!$this->ldap_connect($trace)) {
            $trace->finished();
            return;
        }

        if (!is_object($user) or !property_exists($user, 'id')) {
            throw new coding_exception('Invalid $user parameter in sync_user_enrolments()');
        }

        if (!property_exists($user, 'idnumber')) {
            debugging('Invalid $user parameter in sync_user_enrolments(), missing idnumber');
            $user = $DB->get_record('user', array('id'=>$user->id));
        }

        // We may need a lot of memory here
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);


        $enrolments = array();

            // Get external enrolments according to LDAP server
        $memberofgroups = $this->ldap_find($user->username,array($this->config->memberofattribute),$this->config->user_attribute);
        $memberofgroups=array_change_key_case($memberofgroups,CASE_LOWER);
        $memberofgroups = $memberofgroups[$this->config->memberofattribute];
            if ($this->config->nested_groups){
                $memberofgroups=$this->ldap_find_user_groups($memberofgroups,array($this->config->user_attribute));
            }
            $enrolments['ext'] =  $memberofgroups;

            // Get the list of current user enrolments that come from LDAP
            $sql= "SELECT e.customint1, ue.status, e.id as enrolid, e.courseid
                     FROM {user_enrolments} ue
                     JOIN {enrol} e ON (e.enrol='ldapgroup' AND e.id=ue.enrolid)
                     WHERE (ue.userid = :userid )";
            $params = array ('userid'=>$user->id);
            $enrolments['current'] = $DB->get_records_sql($sql, $params);


        $ignorehidden = $this->get_config('ignorehiddencourses');
        $groupattribute = $this->get_config('group_attribute');

            foreach ($enrolments['ext'] as $enrol) {
                $ldapgroupid = $enrol[$groupattribute][0];
                if (empty($ldapgroupid)) {
                    $trace->output(get_string('extcourseidinvalid', 'enrol_ldapgroup'));
                    continue; // Next; skip this one!
                }

                // Deal with enrolment in the moodle db
                // Add necessary enrol instance if not present yet;
                $sql = "SELECT c.id, c.visible, e.id as enrolid
                          FROM {course} c
                          JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'ldapgroup')
                         WHERE c.id = :courseid";
                $params = array('courseid'=>$course->id);
                if (!($enrol_instance = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE))) {

                }

                if (!$instance = $DB->get_record('enrol', array('id'=>$enrol_instance->enrolid))) {
                    continue; // Weird; skip this one.
                }

                if ($ignorehidden && !$enrol_instance->visible) {
                    continue;
                }

                if (empty($enrolments['current'][$enrol_instance->id])) {
                    // Enrol the user in the given course, with that role.
                    $this->enrol_user($instance, $user->id, $instance->roleid);
                    // Make sure we set the enrolment status to active. If the user wasn't
                    // previously enrolled to the course, enrol_user() sets it. But if we
                    // configured the plugin to suspend the user enrolments _AND_ remove
                    // the role assignments on external unenrol, then enrol_user() doesn't
                    // set it back to active on external re-enrolment. So set it
                    // unconditionnally to cover both cases.
                    $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$user->id));
                    $trace->output(get_string('enroluser', 'enrol_ldapgroup',
                        array('user_username'=> $user->username,
                              'course_id'=>$enrol_instance->id)));
                } else {
                    if ($enrolments['current'][$course->id]->status == ENROL_USER_SUSPENDED) {
                        // Reenable enrolment that was previously disabled. Enrolment refreshed
                        $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$user->id));
                        $trace->output(get_string('enroluserenable', 'enrol_ldapgroup',
                            array('user_username'=> $user->username,
                                  'course_id'=>$enrol_instance->id)));
                    }
                }

                // Remove this course from the current courses, to be able to detect
                // which current courses should be unenroled from when we finish processing
                // external enrolments.
                unset($enrolments['current'][$enrol_instance->id]);
            }

            // Deal with unenrolments.
            $transaction = $DB->start_delegated_transaction();
            foreach ($enrolments['current'] as $course) {
                $context = context_course::instance($course->courseid);
                $instance = $DB->get_record('enrol', array('id'=>$course->enrolid));
                switch ($this->get_config('unenrolaction')) {
                    case ENROL_EXT_REMOVED_UNENROL:
                        $this->unenrol_user($instance, $user->id);
                        $trace->output(get_string('extremovedunenrol', 'enrol_ldapgroup',
                            array('user_username'=> $user->username,
                                  'course_id'=>$course->courseid)));
                        break;
                    case ENROL_EXT_REMOVED_KEEP:
                        // Keep - only adding enrolments
                        break;
                    case ENROL_EXT_REMOVED_SUSPEND:
                        if ($course->status != ENROL_USER_SUSPENDED) {
                            $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, array('enrolid'=>$instance->id, 'userid'=>$user->id));
                            $trace->output(get_string('extremovedsuspend', 'enrol_ldapgroup',
                                array('user_username'=> $user->username,
                                      'course_id'=>$course->courseid)));
                        }
                        break;
                    case ENROL_EXT_REMOVED_SUSPENDNOROLES:
                        if ($course->status != ENROL_USER_SUSPENDED) {
                            $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, array('enrolid'=>$instance->id, 'userid'=>$user->id));
                        }
                        role_unassign_all(array('contextid'=>$context->id, 'userid'=>$user->id, 'component'=>'enrol_ldapgroup', 'itemid'=>$instance->id));
                        $trace->output(get_string('extremovedsuspendnoroles', 'enrol_ldapgroup',
                            array('user_username'=> $user->username,
                                  'course_id'=>$course->courseid)));
                        break;
                }
            }
            $transaction->allow_commit();


        $this->ldap_close();

        $trace->finished();
    }
}

    /**
     * Forces synchronisation of all enrolments with LDAP server.
     * It creates courses if the plugin is configured to do so.
     *
     * @param progress_trace $trace
     * @param int|null $onecourse limit sync to one course->id, null if all courses
     * @return void
     */
    public function sync_enrolments(progress_trace $trace, $onecourse = null) {
        global $CFG, $DB;

        if (!$this->ldap_connect($trace)) {
            $trace->finished();
            return;
        }

        $ldap_pagedresults = ldap_paged_results_supported($this->get_config('ldap_version'));

        // we may need a lot of memory here
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $oneidnumber = null;
        if ($onecourse) {
            $sql = "SELECT e.customint1 AS enrolgroup, e.id AS enrolid, e.courseid
                      FROM {enrol} e WHERE (e.courseid = :id AND e.enrol = 'ldapgroup')";

            if (!$enrol = $DB->get_record_sql($sql, array('id'=>$onecourse))) {
                // Course does not exist, nothing to sync.
                return 0;
            }


            // Feel free to unenrol everybody, no safety tricks here.
            $preventfullunenrol = false;
            // Course being restored are always hidden, we have to ignore the setting here.
            $ignorehidden = false;
            $oneidnumber = ldap_filter_addslashes(core_text::convert($enrol->enrolgroup, 'utf-8', $this->get_config('ldapencoding')));
        }

        // Get enrolments for each type of role.

        $enrolments = array();

            // Get all contexts
            $ldap_contexts = explode(';', $this->config->group_contexts);

            // Get all the fields we will want for the potential course creation
            // as they are light. Don't get membership -- potentially a lot of data.
            $ldap_fields_wanted = array('dn', $this->get_config('group_attribute','cn'),$this->get_config('group_memberofattribute', 'member'));



            // Define the search pattern
            $ldap_search_pattern = $this->config->group_objectclass;

            if ($oneidnumber !== null) {
                $ldap_search_pattern = "(&$ldap_search_pattern({$this->config->group_attribute}=$oneidnumber))";
            }else{
                 $sql = "SELECT e.customint1 AS enrolgroup, e.id AS enrolid, e.courseid
                      FROM {enrol} e WHERE (e.courseid = :id AND e.enrol = 'ldapgroup')";

                if (!$enrols = $DB->get_record_sql($sql)) {
                    // Course does not exist, nothing to sync.
                    return 0;
                }
                $filter='';
                foreach ($enrols as $enrol) {
                    $filter .= '(' . $this->config->group_attribute . '=' . $enrol->enrolgroup. ')';
                }
                 $ldap_search_pattern = "(&".$ldap_search_pattern.$filter.$this->config->group_filter.")";
            }

            $ldap_cookie = '';
            foreach ($ldap_contexts as $ldap_context) {
                $ldap_context = trim($ldap_context);
                if (empty($ldap_context)) {
                    continue; // Next;
                }

                $flat_records = array();
                do {
                    if ($ldap_pagedresults) {
                        ldap_control_paged_result($this->ldapconnection, $this->config->pagesize, true, $ldap_cookie);
                    }

                if ($this->config->group_search_sub) {
                        // Use ldap_search to find first user from subtree
                        $ldap_result = @ldap_search($this->ldapconnection,
                                                    $ldap_context,
                                                    $ldap_search_pattern,
                                                    $ldap_fields_wanted);
                    } else {
                        // Search only in this context
                        $ldap_result = @ldap_list($this->ldapconnection,
                                                  $ldap_context,
                                                  $ldap_search_pattern,
                                                  $ldap_fields_wanted);
                    }
                    if (!$ldap_result) {
                        continue; // Next
                    }

                    if ($ldap_pagedresults) {
                        ldap_control_paged_result_response($this->ldapconnection, $ldap_result, $ldap_cookie);
                    }

                    // Check and push results
                    $records = ldap_get_entries($this->ldapconnection, $ldap_result);

                    // LDAP libraries return an odd array, really. fix it:
                    for ($c = 0; $c < $records['count']; $c++) {
                        array_push($flat_records, $records[$c]);
                    }
                    // Free some mem
                    unset($records);
                } while ($ldap_pagedresults && !empty($ldap_cookie));

                // If LDAP paged results were used, the current connection must be completely
                // closed and a new one created, to work without paged results from here on.
                if ($ldap_pagedresults) {
                    $this->ldap_close();
                    $this->ldap_connect($trace);
                }

                if (count($flat_records)) {
                    $ignorehidden = $this->get_config('ignorehiddencourses');
                    foreach($flat_records as $ldapgroup) {
                        $ldapgroup = array_change_key_case($ldapgroup, CASE_LOWER);
                        $attribute = $ldapgroup{$this->config->group_attribute}[0];
                        //*$trace->output(get_string('synccourserole', 'enrol_ldapgroup', array('idnumber'=>$idnumber, 'role_shortname'=>$role->shortname)));

                        // Enrol & unenrol

                        // Pull the ldap membership into a nice array
                        // this is an odd array -- mix of hash and array --
                        $ldapmembers = array();


                            $ldapmembers = $ldapgroup[$this->config->memberattribute];
                            unset($ldapmembers['count']); // Remove oddity ;)

                            // If we have enabled nested groups, we need to expand
                            // the groups to get the real user list. We need to do
                            // this before dealing with 'memberattribute_isdn'.
                            if ($this->config->nested_groups) {
                                $users = array();
                                foreach ($ldapmembers as $ldapmember) {
                                    $grpusers = $this->ldap_explode_group($ldapmember,
                                                                          $this->config->memberattribute_role);

                                    $users = array_merge($users, $grpusers);
                                }
                                $ldapmembers = array_unique($users); // There might be duplicates.
                            }

                            // Deal with the case where the member attribute holds distinguished names,
                            // but only if the user attribute is not a distinguished name itself.
                            if ($this->config->memberattribute_isdn
                                && ($this->config->idnumber_attribute !== 'dn')
                                && ($this->config->idnumber_attribute !== 'distinguishedname')) {
                                // We need to retrieve the idnumber for all the users in $ldapmembers,
                                // as the idnumber does not match their dn and we get dn's from membership.
                                $memberidnumbers = array();
                                foreach ($ldapmembers as $ldapmember) {
                                    $result = ldap_read($this->ldapconnection, $ldapmember, '(objectClass=*)',
                                                        array($this->config->idnumber_attribute));
                                    $entry = ldap_first_entry($this->ldapconnection, $result);
                                    $values = ldap_get_values($this->ldapconnection, $entry, $this->config->idnumber_attribute);
                                    array_push($memberidnumbers, $values[0]);
                                }

                                $ldapmembers = $memberidnumbers;
                            }
                        }

                        // Prune old ldap enrolments
                        // hopefully they'll fit in the max buffer size for the RDBMS
                        $sql= "SELECT u.id as userid, u.username, ue.status,
                                      ra.contextid, ra.itemid as instanceid
                                 FROM {user} u
                                 JOIN {role_assignments} ra ON (ra.userid = u.id AND ra.component = 'enrol_ldapgroup' )
                                 JOIN {user_enrolments} ue ON (ue.userid = u.id AND ue.enrolid = ra.itemid)
                                 JOIN {enrol} e ON (e.id = ue.enrolid)
                                WHERE u.deleted = 0 AND e.courseid = :courseid ";
                        $params = array( 'courseid'=>$course_obj->id);
                        if (!empty($ldapmembers)) {
                            list($ldapml, $params2) = $DB->get_in_or_equal($ldapmembers, SQL_PARAMS_NAMED, 'm', false);
                            $sql .= "AND u.idnumber $ldapml";
                            $params = array_merge($params, $params2);
                            unset($params2);
                        } else {
                            $trace->output(get_string('emptyenrolment', 'enrol_ldapgroup'));
                        }
                        $todelete = $DB->get_records_sql($sql, $params);

                        if (!empty($todelete)) {
                            $transaction = $DB->start_delegated_transaction();
                            foreach ($todelete as $row) {
                                $instance = $DB->get_record('enrol', array('id'=>$row->instanceid));
                                switch ($this->get_config('unenrolaction')) {
                                case ENROL_EXT_REMOVED_UNENROL:
                                    $this->unenrol_user($instance, $row->userid);
                                    $trace->output(get_string('extremovedunenrol', 'enrol_ldapgroup',
                                        array('user_username'=> $row->username)));
                                    break;
                                case ENROL_EXT_REMOVED_KEEP:
                                    // Keep - only adding enrolments
                                    break;
                                case ENROL_EXT_REMOVED_SUSPEND:
                                    if ($row->status != ENROL_USER_SUSPENDED) {
                                        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, array('enrolid'=>$instance->id, 'userid'=>$row->userid));
                                        $trace->output(get_string('extremovedsuspend', 'enrol_ldapgroup',
                                            array('user_username'=> $row->username)));
                                    }
                                    break;
                                case ENROL_EXT_REMOVED_SUSPENDNOROLES:
                                    if ($row->status != ENROL_USER_SUSPENDED) {
                                        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, array('enrolid'=>$instance->id, 'userid'=>$row->userid));
                                    }
                                    role_unassign_all(array('contextid'=>$row->contextid, 'userid'=>$row->userid, 'component'=>'enrol_ldapgroup', 'itemid'=>$instance->id));
                                    $trace->output(get_string('extremovedsuspendnoroles', 'enrol_ldapgroup',
                                        array('user_username'=> $row->username,
                                              'course_shortname'=>$course_obj)));
                                    break;
                                }
                            }
                            $transaction->allow_commit();
                        }

                        // Insert current enrolments
                        // bad we can't do INSERT IGNORE with postgres...

                        // Add necessary enrol instance if not present yet;
                        $sql = "SELECT c.id, c.visible, e.id as enrolid
                                  FROM {course} c
                                  JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'ldapgroup')
                                 WHERE c.id = :courseid";
                        $params = array('courseid'=>$course_obj->id);
                        if (!($enrol_instance = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE))) {
                        }

                        if (!$instance = $DB->get_record('enrol', array('id'=>$enrol_instance->enrolid))) {
                            continue; // Weird; skip this one.
                        }

                        if ($ignorehidden && !$enrol_instance->visible) {
                            continue;
                        }

                        $transaction = $DB->start_delegated_transaction();
                        foreach ($ldapmembers as $ldapmember) {
                            $sql = 'SELECT id,username,1 FROM {user} WHERE idnumber = ? AND deleted = 0';
                            $member = $DB->get_record_sql($sql, array($ldapmember));
                            if(empty($member) || empty($member->id)){
                                $trace->output(get_string('couldnotfinduser', 'enrol_ldapgroup', $ldapmember));
                                continue;
                            }

                            $sql= "SELECT ue.status
                                     FROM {user_enrolments} ue
                                     JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'ldap')
                                    WHERE e.courseid = :courseid AND ue.userid = :userid";
                            $params = array('courseid'=>$course_obj->id, 'userid'=>$member->id);
                            $userenrolment = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);

                            if (empty($userenrolment)) {
                                $this->enrol_user($instance, $member->id, $role->id);
                                // Make sure we set the enrolment status to active. If the user wasn't
                                // previously enrolled to the course, enrol_user() sets it. But if we
                                // configured the plugin to suspend the user enrolments _AND_ remove
                                // the role assignments on external unenrol, then enrol_user() doesn't
                                // set it back to active on external re-enrolment. So set it
                                // unconditionally to cover both cases.
                                $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$member->id));
                                $trace->output(get_string('enroluser', 'enrol_ldapgroup',
                                    array('user_username'=> $member->username)));

                            } else {
                                if (!$DB->record_exists('role_assignments', array('roleid'=>$role->id, 'userid'=>$member->id, 'contextid'=>$context->id, 'component'=>'enrol_ldapgroup', 'itemid'=>$instance->id))) {
                                    // This happens when reviving users or when user has multiple roles in one course.
                                    $context = context_course::instance($course_obj->id);
                                    role_assign($role->id, $member->id, $context->id, 'enrol_ldapgroup', $instance->id);
                                    $trace->output("Assign role to user '$member->username' in course '$course_obj->shortname ($course_obj->id)'");
                                }
                                if ($userenrolment->status == ENROL_USER_SUSPENDED) {
                                    // Reenable enrolment that was previously disabled. Enrolment refreshed
                                    $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$member->id));
                                    $trace->output(get_string('enroluserenable', 'enrol_ldapgroup',
                                        array('user_username'=> $member->username,
                                              'course_shortname'=>$course_obj->shortname,
                                              'course_id'=>$course_obj->id)));
                                }
                            }
                        }
                        $transaction->allow_commit();
                    }
                }


        @$this->ldap_close();
        $trace->finished();
    }

     /**
     * Given a group name (either a RDN or a DN), get the list of users
     * belonging to that group. If the group has nested groups, expand all
     * the intermediate groups and return the full list of users that
     * directly or indirectly belong to the group.
     *
     *
     * @return array the list of users belonging to the group. If $group
     *         is not actually a group, returns array($group).
     */
    private function ldap_find_group_members($ldapmembers,$from) {
        $users = array();
        if (($this->config->nested_groups)||((!empty($this->config->memberattribute_is))&&($this->user_sync_field=='username'))){
        unset($ldapmembers['count']);
        foreach ($ldapmembers as $ldapmember) {
                if ($ldapmember=="cn=Agalan groups fake member"){continue;}
                $pos=strpos ($ldapmember,"ou=group");
                if ($pos!==false){
                    if ($this->config->nested_groups) {
                        $fields= array($this->config->group_memberattribute, $this->config->user_attribute);
                        $input=($this->config->memberattribute_isdn)?'dn':$this->config->user_attribute;
                        $group = $this->ldap_find($ldapmember,$fields ,$input,'group');
                        if ($group){
                            if (count($group[$this->config->group_memberattribute])){
                                if (!in_array( $group[$this->config->user_attribute][0],$from)){
                                    array_push($from, $group[$this->config->user_attribute][0]);
                                    $group_members=$this->ldap_find_group_members($group[$this->config->group_memberattribute],$from);
                                    $users = array_merge($users, $group_members);
                                }
                            }
                        }
                    }
                }else{
                    if (!$this->config->memberattribute_isdn){

                            $user = $this->ldap_find($ldapmember,array($this->config->user_attribute) ,'dn');
                            $user=$user?$user[$this->config->user_attribute][0]:$user;

                    }
                    if ($user){
                        array_push($users, $user);
                    }
                }
            }
            $ldapmembers=$users;
        }
    return $ldapmembers;
    }

 function ldap_find( $username, $search_attrib,$input_attrib,$type='user') {
        if ( empty($username) || empty($search_attrib)||empty($input_attrib)) {
            return false;
        }
        // Default return value
        $objectclass=$type.'_objectclass';
        $ldap_user = false;
        if ($input_attrib=='dn'){
            $ldap_result = @ldap_read($this->ldapconnection, $username, $this->config->{$objectclass}, $search_attrib);
        }else{
            $contexts=explode(';', $this->config->{$type.'_contexts'});
            // Get all contexts and look for first matching user
            foreach ($contexts as $context) {
                $context = trim($context);
                if (empty($context)) {
                    continue;
                }
                $pos=strpos($username,$input_attrib."=");
                if ($pos === false) {
                    $filter =$input_attrib.'='.$username;
                }else{
                    $filter= $username;
                }
                if ($this->config->{$type.'_search_sub'}) {
                    if (!$ldap_result = @ldap_search($this->ldapconnection, $context,
                                                   '(&'.$this->config->{$objectclass}.'('.$filter.'))',$search_attrib)) {
                        break; // Not found in this context.
                    }
                } else {
                    $ldap_result = ldap_list($this->ldapconnection, $context,
                                             '(&'.$this->config->{$objectclass}.'('.$filter.'))',$search_attrib);
                }
            }
        }
        if ($ldap_result){
        $entry = ldap_first_entry($this->ldapconnection, $ldap_result);
            if ($entry) {
                $ldap_user = ldap_get_attributes($this->ldapconnection, $entry);
                if (in_array('dn',$search_attrib)){ $ldap_user['dn']=ldap_get_dn($this->ldapconnection, $entry);}
            }
        }
        return $ldap_user;
    }


     /**
     * Given a user name (either a RDN or a DN), get the list of users
     * belonging to that group. If the group has nested groups, expand all
     * the intermediate groups and return the full list of users that
     * directly or indirectly belong to the group.
     *
     *
     * @return array the list of users belonging to the group. If $group
     *         is not actually a group, returns array($group).
     */
    private function ldap_find_user_groups($memberofgroups,$from) {
        $groups = array();
        if ($this->config->nested_groups){
        unset($memberofgroups['count']);
        foreach ($memberofgroups as $memberof) {
            if ($memberof=="cn=Agalan groups fake member"){continue;}
            $fields= array($this->config->memberofattribute,$this->config->group_attribute);
            $group = $this->ldap_find($memberof,$fields,$this->config->group_attribute,'group');
                if ($group){
                    if (in_array($this->config->memberofattribute,$group)){
                    if (count($group[$this->config->memberofattribute])){
                        if (!in_array( $group[$this->config->group_attribute][0],$from)){
                            array_push($from, $group[$this->config->group_attribute][0]);
                            $group_members=$this->ldap_find_user_groups($group[$this->config->memberofattribute],$from);
                            $groups = array_merge($groups, $group_members);
                        }
                    }
                    }
                    array_push($groups, $group[$this->config->group_attribute][0]);
                }
            }
        $memberofgroups=$groups;
        }
    return $memberofgroups;
    }
    /**
     * Connect to the LDAP server, using the plugin configured
     * settings. It's actually a wrapper around ldap_connect_moodle()
     *
     * @param progress_trace $trace
     * @return bool success
     */
    protected function ldap_connect(progress_trace $trace = null) {
        global $CFG;
        require_once($CFG->libdir.'/ldaplib.php');

        if (isset($this->ldapconnection)) {
            return true;
        }

        if ($ldapconnection = ldap_connect_moodle($this->get_config('host_url'), $this->get_config('ldap_version'),
                                                  $this->get_config('user_type'), $this->get_config('bind_dn'),
                                                  $this->get_config('bind_pw'), $this->get_config('opt_deref'),
                                                  $debuginfo, $this->get_config('start_tls'))) {
            $this->ldapconnection = $ldapconnection;
            return true;
        }

        if ($trace) {
            $trace->output($debuginfo);
        } else {
            error_log($this->errorlogtag.$debuginfo);
        }

        return false;
    }

    /**
     * Disconnects from a LDAP server
     *
     */
    protected function ldap_close() {
        if (isset($this->ldapconnection)) {
            @ldap_close($this->ldapconnection);
            $this->ldapconnection = null;
        }
        return;
    }

     public function is_cron_required()
    {
        $_enabled = intval($this->get_config('cron_enabled'));
        $is_time=parent::is_cron_required();
        $_enabled= $_enabled == 1 ? true : false;
        return $_enabled&&$is_time;
    }


    private function get_current_enrol($cohortid,$field) {
        global $DB;
        $sql = " SELECT u.id,u.".$field."
                          FROM {user} u
                         JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)
                        WHERE u.deleted=0";
        $params['cohortid'] = $cohortid;
        return $DB->get_records_sql_menu($sql, $params);
    }
    /**
     * Return multidimensional array with details of user courses (at
     * least dn and idnumber).
     *
     * @param string $memberuid user idnumber (without magic quotes).
     * @param object role is a record from the mdl_role table.
     * @return array
     */
    protected function find_ext_enrolments($memberuid) {
        global $CFG;
        require_once($CFG->libdir.'/ldaplib.php');

        if (empty($memberuid)) {
            // No "idnumber" stored for this user, so no LDAP enrolments
            return array();
        }

        $ldap_contexts = trim($this->get_config('group_contexts'));
        if (empty($ldap_contexts)) {
            // No role contexts, so no LDAP enrolments
            return array();
        }

        $extmemberuid = core_text::convert($memberuid, 'utf-8', $this->get_config('ldapencoding'));

        if($this->get_config('memberattribute_isdn')) {
            if (!($extmemberuid = $this->ldap_find_userdn($extmemberuid))) {
                return array();
            }
        }

        $ldap_search_pattern = '';
        if($this->get_config('nested_groups')) {
            $usergroups = $this->ldap_find_user_groups($extmemberuid);
            if(count($usergroups) > 0) {
                foreach ($usergroups as $group) {
                    $ldap_search_pattern .= '('.$this->get_config('memberattribute').'='.$group.')';
                }
            }
        }

        // Default return value
        $courses = array();

        // Get all the fields we will want for the potential course creation
        // as they are light. don't get membership -- potentially a lot of data.
        $ldap_fields_wanted = array('dn', $this->get_config('group_attribute'));


        // Define the search pattern
        if (empty($ldap_search_pattern)) {
            $ldap_search_pattern = '('.$this->get_config('memberattribute').'='.ldap_filter_addslashes($extmemberuid).')';
        } else {
            $ldap_search_pattern = '(|' . $ldap_search_pattern .
                                       '('.$this->get_config('memberattribute').'='.ldap_filter_addslashes($extmemberuid).')' .
                                   ')';
        }
        $ldap_search_pattern='(&'.$this->get_config('objectclass').$ldap_search_pattern.')';

        // Get all contexts and look for first matching user
        $ldap_contexts = explode(';', $ldap_contexts);
        $ldap_pagedresults = ldap_paged_results_supported($this->get_config('ldap_version'));
        foreach ($ldap_contexts as $context) {
            $context = trim($context);
            if (empty($context)) {
                continue;
            }

            $ldap_cookie = '';
            $flat_records = array();
            do {
                if ($ldap_pagedresults) {
                    ldap_control_paged_result($this->ldapconnection, $this->config->pagesize, true, $ldap_cookie);
                }

                if ($this->get_config('group_search_sub')) {
                    // Use ldap_search to find first user from subtree
                    $ldap_result = @ldap_search($this->ldapconnection,
                                                $context,
                                                $ldap_search_pattern,
                                                $ldap_fields_wanted);
                } else {
                    // Search only in this context
                    $ldap_result = @ldap_list($this->ldapconnection,
                                              $context,
                                              $ldap_search_pattern,
                                              $ldap_fields_wanted);
                }

                if (!$ldap_result) {
                    continue;
                }

                if ($ldap_pagedresults) {
                    ldap_control_paged_result_response($this->ldapconnection, $ldap_result, $ldap_cookie);
                }

                // Check and push results. ldap_get_entries() already
                // lowercases the attribute index, so there's no need to
                // use array_change_key_case() later.
                $records = ldap_get_entries($this->ldapconnection, $ldap_result);

                // LDAP libraries return an odd array, really. Fix it.
                for ($c = 0; $c < $records['count']; $c++) {
                    array_push($flat_records, $records[$c]);
                }
                // Free some mem
                unset($records);
            } while ($ldap_pagedresults && !empty($ldap_cookie));

            // If LDAP paged results were used, the current connection must be completely
            // closed and a new one created, to work without paged results from here on.
            if ($ldap_pagedresults) {
                $this->ldap_close();
                $this->ldap_connect();
            }

            if (count($flat_records)) {
                $ldapgroups = array_merge($ldapgroups, $flat_records);
            }
        }

        return $ldapgroups;
    }

    /**
     * Search specified contexts for the specified userid and return the
     * user dn like: cn=username,ou=suborg,o=org. It's actually a wrapper
     * around ldap_find_userdn().
     *
     * @param string $userid the userid to search for (in external LDAP encoding, no magic quotes).
     * @return mixed the user dn or false
     */
    protected function ldap_find_userdn($userid) {
        global $CFG;
        require_once($CFG->libdir.'/ldaplib.php');

        $ldap_contexts = explode(';', $this->get_config('user_contexts'));
        $ldap_defaults = ldap_getdefaults();

        return ldap_find_userdn($this->ldapconnection, $userid, $ldap_contexts,
                                '(objectClass='.$ldap_defaults['objectclass'][$this->get_config('user_type')].')',
                                $this->get_config('user_attribute'), $this->get_config('user_search_sub'));
    }


    /**
     * Given a group name (either a RDN or a DN), get the list of users
     * belonging to that group. If the group has nested groups, expand all
     * the intermediate groups and return the full list of users that
     * directly or indirectly belong to the group.
     *
     * @param string $group the group name to search
     * @param string $memberattibute the attribute that holds the members of the group
     * @return array the list of users belonging to the group. If $group
     *         is not actually a group, returns array($group).
     */
    protected function ldap_explode_group($group, $memberattribute) {
        switch ($this->get_config('user_type')) {
            case 'ad':
                // $group is already the distinguished name to search.
                $dn = $group;

                $result = ldap_read($this->ldapconnection, $dn, '(objectClass=*)', array('objectClass'));
                $entry = ldap_first_entry($this->ldapconnection, $result);
                $objectclass = ldap_get_values($this->ldapconnection, $entry, 'objectClass');

                if (!in_array('group', $objectclass)) {
                    // Not a group, so return immediately.
                    return array($group);
                }

                $result = ldap_read($this->ldapconnection, $dn, '(objectClass=*)', array($memberattribute));
                $entry = ldap_first_entry($this->ldapconnection, $result);
                $members = @ldap_get_values($this->ldapconnection, $entry, $memberattribute); // Can be empty and throws a warning
                if ($members['count'] == 0) {
                    // There are no members in this group, return nothing.
                    return array();
                }
                unset($members['count']);

                $users = array();
                foreach ($members as $member) {
                    $group_members = $this->ldap_explode_group($member, $memberattribute);
                    $users = array_merge($users, $group_members);
                }

                return ($users);
                break;
            default:
                error_log($this->errorlogtag.get_string('explodegroupusertypenotsupported', 'enrol_ldapgroup',
                                                        $this->get_config('user_type_name')));

                return array($group);
        }
    }


    /**
     *
     *
     *
     * @return
     */
    private function create_user($ldap_user)
    {
        global $CFG, $DB;
        $textlib =new textlib();
        $user = new stdClass();
        //$user->username = trim(textlib::strtolower($ldap_user['uid'][0]));
        foreach ($this->userfields as $key => $field){

            if (isset($ldap_user[$field])) {
                    if (is_array($ldap_user[$field])) {
                        $newval = $textlib->convert($ldap_user[$field][0], $this->config->ldapencoding, 'utf-8');
                    } else {
                        $newval = $textlib->convert($ldap_user[$field], $this->config->ldapencoding, 'utf-8');
                    }
                    if ($key=="username"){
                        $newval=trim(textlib::strtolower($newval));
                    }
                    $user->{$key} = $newval;
                }
        }


        // Prep a few params
        $user->timecreated =  $user->timemodified   = time();
        $user->confirmed  = 1;
        $user->auth       = $this->authtype;
        $user->mnethostid = $CFG->mnet_localhost_id;
        if (empty($user->lang)) {
            $user->lang = $CFG->lang;
        }

        return user_create_user($user);
    }

    /**
     * Automatic enrol sync executed during restore.
     * Useful for automatic sync by course->idnumber or course category.
     * @param stdClass $course course record
     */
    public function restore_sync_course($course) {
        // TODO: this can not work because restore always nukes the course->idnumber, do not ask me why (MDL-37312)
        // NOTE: for now restore does not do any real logging yet, let's do the same here...
        $trace = new error_log_progress_trace();
        $this->sync_enrolments($trace, $course->id);
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        // There is only 1 ldap enrol instance per course.
        if ($instances = $DB->get_records('enrol', array('courseid'=>$data->courseid, 'enrol'=>'ldapgroup'), 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        global $DB;

        if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.

        } else if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_KEEP) {
            if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
                $this->enrol_user($instance, $userid, null, 0, 0, $data->status);
            }

        } else {
            if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
                $this->enrol_user($instance, $userid, null, 0, 0, ENROL_USER_SUSPENDED);
            }
        }
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        global $DB;

        if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL or $this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Skip any roles restore, they should be already synced automatically.
            return;
        }

        // Just restore every role.
        if ($DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            role_assign($roleid, $userid, $contextid, 'enrol_'.$instance->enrol, $instance->id);
        }
    }
}
