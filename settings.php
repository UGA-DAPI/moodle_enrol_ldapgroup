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
 * LDAPgroup enrolment plugin settings and presets.
 *
 * @package    enrol_ldapgroup
 * @author     Fabrice Menard
 * @copyright  2014 Fabrice Menard <fabrice.menard@upmf-grenoble.fr> - 2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- heading ---
    $settings->add(new admin_setting_heading('enrol_ldapgroup_settings', '', get_string('pluginname_desc', 'enrol_ldapgroup')));

    if (!function_exists('ldap_connect')) {
        $settings->add(new admin_setting_heading('enrol_phpldap_noextension', '', get_string('phpldap_noextension', 'enrol_ldapgroup')));
    } else {
        require_once($CFG->dirroot.'/enrol/ldap/settingslib.php');
        require_once($CFG->libdir.'/ldaplib.php');

        $yesno = array(get_string('no'), get_string('yes'));

        //--- general settings ---
        $settings->add(new admin_setting_heading('enrol_ldapgroup_general_settings', get_string('general_settings', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/login_sync', get_string('login_sync_key', 'enrol_ldapgroup'), get_string('login_sync', 'enrol_ldapgroup'), 1,$yesno));
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/cron_enabled', get_string('cron_enabled_key', 'enrol_ldapgroup'), get_string('cron_enabled', 'enrol_ldapgroup'), 1, $yesno));
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/email_report_enabled', get_string('email_report_enabled_key', 'enrol_ldapgroup'), get_string('email_report_enabled', 'enrol_ldapgroup'), 1, $yesno));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/email_report', get_string('email_report_key', 'enrol_ldapgroup'), get_string('email_report', 'enrol_ldapgroup'), '', true));
        $settings->add(new admin_setting_configcheckbox('enrol_ldapgroup/debug_mode', get_string('debug_mode_key', 'enrol_ldapgroup'), get_string('debug_mode', 'enrol_ldapgroup'), false));


        //--- connection settings ---
        $settings->add(new admin_setting_heading('enrol_ldapgroup_server_settings', get_string('server_settings', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/host_url', get_string('host_url_key', 'enrol_ldapgroup'), get_string('host_url', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/start_tls', get_string('start_tls_key', 'auth_ldap'), get_string('start_tls', 'auth_ldap'), 0, $yesno));
        // Set LDAPv3 as the default. Nowadays all the servers support it and it gives us some real benefits.
        $options = array(3=>'3', 2=>'2');
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/ldap_version', get_string('version_key', 'enrol_ldapgroup'), get_string('version', 'enrol_ldapgroup'), 3, $options));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/ldapencoding', get_string('ldap_encoding_key', 'enrol_ldapgroup'), get_string('ldap_encoding', 'enrol_ldapgroup'), 'utf-8'));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/pagesize', get_string('pagesize_key', 'auth_ldap'), get_string('pagesize', 'auth_ldap'), LDAP_DEFAULT_PAGESIZE, true));

        //--- binding settings ---
        $settings->add(new admin_setting_heading('enrol_ldapgroup_bind_settings', get_string('bind_settings', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/bind_dn', get_string('bind_dn_key', 'enrol_ldapgroup'), get_string('bind_dn', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configpasswordunmask('enrol_ldapgroup/bind_pw', get_string('bind_pw_key', 'enrol_ldapgroup'), get_string('bind_pw', 'enrol_ldapgroup'), ''));

        //--- group lookup settings
        $settings->add(new admin_setting_heading('enrol_ldapgroup_group_settings', get_string('group_settings', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/group_objectclass', get_string('objectclass_key', 'enrol_ldapgroup'), get_string('objectclass', 'enrol_ldapgroup'), 'posixGroup'));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/group_filter', get_string('filter_key', 'enrol_ldapgroup'), get_string('filter', 'enrol_ldapgroup'), '(cn=*)'));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/group_contexts', get_string('group_contexts_key', 'enrol_ldapgroup'), get_string('group_contexts', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/group_search_sub', get_string('search_subcontexts_key', 'enrol_ldapgroup'), get_string('group_search_sub', 'enrol_ldapgroup'), key($yesno), $yesno));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/member_attribute', get_string('member_attribute_key', 'enrol_ldapgroup'), get_string('member_attribute', 'enrol_ldapgroup'), 'member', false));
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/member_attribute_isdn', get_string('memberattribute_isdn_key', 'enrol_ldapgroup'), get_string('memberattribute_isdn', 'enrol_ldapgroup'), 0, $yesno));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/group_attribute', get_string('group_attribute_key', 'enrol_ldapgroup'), get_string('group_attribute', 'enrol_ldapgroup'), 'cn', true));

        //--- user lookup settings
        $settings->add(new admin_setting_heading('enrol_ldapgroup_user_settings', get_string('user_settings', 'enrol_ldapgroup'), ''));
        $usertypes = ldap_supported_usertypes();
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/user_type', get_string('user_type_key', 'enrol_ldapgroup'), get_string('user_type', 'enrol_ldapgroup'), end($usertypes), $usertypes));
        $opt_deref = array();
        $opt_deref[LDAP_DEREF_NEVER] = get_string('no');
        $opt_deref[LDAP_DEREF_ALWAYS] = get_string('yes');
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/opt_deref', get_string('opt_deref_key', 'enrol_ldapgroup'), get_string('opt_deref', 'enrol_ldapgroup'), key($opt_deref), $opt_deref));
        $settings->add(new admin_setting_configtext('enrol_ldapgroup/user_contexts', get_string('user_contexts_key', 'enrol_ldapgroup'), get_string('user_contexts', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/search_subcontexts', get_string('search_subcontexts_key', 'enrol_ldapgroup'), get_string('search_subcontexts', 'enrol_ldapgroup'), key($yesno), $yesno));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/user_attribute', get_string('user_attribute_key', 'enrol_ldapgroup'), get_string('user_attribute', 'enrol_ldapgroup'), '', true, true));
        $settings->add(new admin_setting_configtext_trim_lower('enrol_ldapgroup/memberof_attribute', get_string('memberof_attribute_key', 'enrol_ldapgroup'), get_string('memberof_attribute', 'enrol_ldapgroup'), 'memberUid', false));
        $settings->add(new admin_setting_configtext('enrol_ldapgroup/memberofattribute_isdn', get_string('memberofattribute_isdn_key', 'enrol_ldapgroup'), get_string('memberofattribute_isdn', 'enrol_ldapgroup'),0,$yesno ));
        $settings->add(new admin_setting_configtext('enrol_ldapgroup/user_objectclass', get_string('objectclass_key', 'enrol_ldapgroup'), get_string('user_objectclass', 'enrol_ldapgroup'), ''));
        $settings->add(new admin_setting_configcheckbox('enrol_ldapgroup/autocreate_users', get_string('autocreate_users_key', 'enrol_ldapgroup'), get_string('autocreate_users', 'enrol_ldapgroup'), false));
        $settings->add(new admin_setting_configcheckbox('enrol_ldapgroup/ignorehiddencourses', get_string('ignorehiddencourses', 'enrol_database'), get_string('ignorehiddencourses_desc', 'enrol_database'), 0));
        $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                         ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                         ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                         ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));


        //--- nested groups settings ---
        $settings->add(new admin_setting_heading('enrol_ldapgroup_nested_groups_settings', get_string('nested_groups_settings', 'enrol_ldapgroup'), ''));
        $options = $yesno;
        $settings->add(new admin_setting_configselect('enrol_ldapgroup/nested_groups', get_string('nested_groups_key', 'enrol_ldapgroup'), get_string('nested_groups', 'enrol_ldapgroup'), 0, $options));
    }
}
