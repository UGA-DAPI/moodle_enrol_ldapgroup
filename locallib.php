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
 * Auxiliary manual user enrolment lib, the main purpose is to lower memory requirements...
 *
 * @package    enrol_manual
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');


/**
 * Enrol candidates.
 */
class enrol_ldapgroup_bygroup_participant extends user_selector_base {
    protected $enrolid;

    public function __construct($name, $options) {
        $this->enrolid  = $options['enrolid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['enrolid'] = $this->enrolid;

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
            LEFT JOIN {user_enrolments} ue ON (ue.userid = u.id AND ue.enrolid = :enrolid)
                WHERE $wherecondition
                      AND ue.id IS NULL";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }


        if ($search) {
            $groupname = get_string('enrolcandidatesmatching', 'enrol', $search);
        } else {
            $groupname = get_string('enrolcandidates', 'enrol');
        }

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['enrolid'] = $this->enrolid;
        $options['file']    = 'enrol/manual/locallib.php';
        return $options;
    }
}

/**
 * Enrolled users.
 */
class enrol_ldapgroup_bymember_participant extends user_selector_base {
    protected $courseid;
    protected $enrolid;

    public function __construct($name, $options) {
        $this->enrolid  = $options['enrolid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['enrolid'] = $this->enrolid;

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {user_enrolments} ue ON (ue.userid = u.id AND ue.enrolid = :enrolid)
                WHERE $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }


        if ($search) {
            $groupname = get_string('enrolledusersmatching', 'enrol', $search);
        } else {
            $groupname = get_string('enrolledusers', 'enrol');
        }

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['enrolid'] = $this->enrolid;
        $options['file']    = 'enrol/manual/locallib.php';
        return $options;
    }
}

/**
 * Migrates all enrolments of the given plugin to enrol_manual plugin,
 * this is used for example during plugin uninstallation.
 *
 * NOTE: this function does not trigger role and enrolment related events.
 *
 * @param string $enrol  The enrolment method.
 */
function enrol_ldapgroup_migrate_plugin_enrolments($enrol) {
    global $DB;

    if ($enrol === 'manual') {
        // We can not migrate to self.
        return;
    }

    $manualplugin = enrol_get_plugin('ldapgroup');

    $params = array('enrol'=>$enrol);
    $sql = "SELECT e.id, e.courseid, e.status, MIN(me.id) AS mid, COUNT(ue.id) AS cu
              FROM {enrol} e
              JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
              JOIN {course} c ON (c.id = e.courseid)
         LEFT JOIN {enrol} me ON (me.courseid = e.courseid AND me.enrol='ldapgroup')
             WHERE e.enrol = :enrol
          GROUP BY e.id, e.courseid, e.status
          ORDER BY e.id";
    $rs = $DB->get_recordset_sql($sql, $params);

    foreach($rs as $e) {
        $minstance = false;
        if (!$e->mid) {
            // Manual instance does not exist yet, add a new one.
            $course = $DB->get_record('course', array('id'=>$e->courseid), '*', MUST_EXIST);
            if ($minstance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'manual'))) {
                // Already created by previous iteration.
                $e->mid = $minstance->id;
            } else if ($e->mid = $manualplugin->add_default_instance($course)) {
                $minstance = $DB->get_record('enrol', array('id'=>$e->mid));
                if ($e->status != ENROL_INSTANCE_ENABLED) {
                    $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, array('id'=>$e->mid));
                    $minstance->status = ENROL_INSTANCE_DISABLED;
                }
            }
        } else {
            $minstance = $DB->get_record('enrol', array('id'=>$e->mid));
        }

        if (!$minstance) {
            // This should never happen unless adding of default instance fails unexpectedly.
            debugging('Failed to find manual enrolment instance', DEBUG_DEVELOPER);
            continue;
        }

        // First delete potential role duplicates.
        $params = array('id'=>$e->id, 'component'=>'enrol_'.$enrol, 'empty'=>'');
        $sql = "SELECT ra.id
                  FROM {role_assignments} ra
                  JOIN {role_assignments} mra ON (mra.contextid = ra.contextid AND mra.userid = ra.userid AND mra.roleid = ra.roleid AND mra.component = :empty AND mra.itemid = 0)
                 WHERE ra.component = :component AND ra.itemid = :id";
        $ras = $DB->get_records_sql($sql, $params);
        $ras = array_keys($ras);
        $DB->delete_records_list('role_assignments', 'id', $ras);
        unset($ras);

        // Migrate roles.
        $sql = "UPDATE {role_assignments}
                   SET itemid = 0, component = :empty
                 WHERE itemid = :id AND component = :component";
        $params = array('empty'=>'', 'id'=>$e->id, 'component'=>'enrol_'.$enrol);
        $DB->execute($sql, $params);

        // Delete potential enrol duplicates.
        $params = array('id'=>$e->id, 'mid'=>$e->mid);
        $sql = "SELECT ue.id
                  FROM {user_enrolments} ue
                  JOIN {user_enrolments} mue ON (mue.userid = ue.userid AND mue.enrolid = :mid)
                 WHERE ue.enrolid = :id";
        $ues = $DB->get_records_sql($sql, $params);
        $ues = array_keys($ues);
        $DB->delete_records_list('user_enrolments', 'id', $ues);
        unset($ues);

        // Migrate to manual enrol instance.
        $params = array('id'=>$e->id, 'mid'=>$e->mid);
        if ($e->status != ENROL_INSTANCE_ENABLED and $minstance->status == ENROL_INSTANCE_ENABLED) {
            $status = ", status = :disabled";
            $params['disabled'] = ENROL_USER_SUSPENDED;
        } else {
            $status = "";
        }
        $sql = "UPDATE {user_enrolments}
                   SET enrolid = :mid $status
                 WHERE enrolid = :id";
        $DB->execute($sql, $params);
    }
    $rs->close();
}
