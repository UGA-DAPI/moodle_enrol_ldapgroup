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
 * Strings for component 'enrol_ldapgroup', language 'en'.
 *
 * @package    enrol_ldapgroup
 * @author     Fabrice Menard - based on code by Iñaki Arenaza Martin Dougiamas, Martin Langhoff and others
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @copyright  2014 Fabrice Menard <fabrice.menard@upmf-grenoble.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['assignrole']  = "Assigning role '{\$a->role_shortname}' to user '{\$a->user_username}' into course '{\$a->course_shortname}' (id {\$a->course_id})";
$string['assignrolefailed'] = "Failed to assign role '{\$a->role_shortname}' to user '{\$a->user_username}' into course '{\$a->course_shortname}' (id {\$a->course_id})\n";
$string['autocreate_users_key']='Autocreate missing users';
$string['autocreate_users']='if false will not create missing users ';

$string['bind_dn'] = 'If you want to use a bind user to search users, specify it here. Someting like \'cn=ldapuser,ou=public,o=org\'';
$string['bind_dn_key'] = 'Bind user distinguished name';
$string['bind_pw'] = 'Password for the bind user';
$string['bind_pw_key'] = 'Password';
$string['bind_settings'] = 'Bind settings';
$string['couldnotfinduser'] = "Could not find user '{\$a}', skipping\n";
$string['cron_enabled_key'] = 'Enable sync on crontab?';
$string['cron_enabled'] = 'If set to yes, the plugin will synchronize users and cohorts every time the moodle cron job is executed. Select No to disable this feature. You can also run the import process manually by clicking {$a}';

$string['debug_mode_key']='Verbose mode';
$string['debug_mode']='Turn on or off the verbose mode when running the script';

$string['editlock'] = 'Lock value';
$string['email_report'] = 'When a synchronization is completed, the system will send an email containing a report to this address';
$string['email_report_key'] = 'Email address for reports';
$string['email_report_enabled'] = 'Select "Yes" to enable email report messages. If you select "No", then the "Email address for reports" field will be ignored.';
$string['email_report_enabled_key'] = 'Enable email reports';
$string['emptyenrolment'] = "Empty enrolment for role '{\$a->role_shortname}' in course '{\$a->course_shortname}'\n";
$string['enrolname'] = 'LDAP Group';
$string['enroluser'] =  "Enrol user '{\$a->user_username}' into course '{\$a->course_shortname}' (id {\$a->course_id})";
$string['enroluserenable'] =  "Enabled enrolment for user '{\$a->user_username}' in course '{\$a->course_shortname}' (id {\$a->course_id})";
$string['err_create_user'] = 'Cannot create user with uid: {$a}';
$string['err_member_attribute'] = 'EMPTY MEMBER ATTRIBUTE FOR USER LOOKUP, PLEASE REVIEW SETTINGS';
$string['err_invalid_group_name'] = 'Empty LDAP group attribute (group name) : {$a}, skipping...';
$string['err_invalid_group_name'] = 'Empty LDAP group attribute (group id) : {$a}, skipping...';
$string['err_user_empty_uid'] = 'Empty uid in LDAP entry: {$a}';
$string['err_user_exists_in_group'] = 'User {$a->user} exists in group {$a->group}';

$string['explodegroupusertypenotsupported'] = "ldap_explode_group() does not support selected user type: {\$a}\n";
$string['extcourseidinvalid'] = 'The course external id is invalid!';
$string['extremovedsuspend'] =  "Disabled enrolment for user '{\$a->user_username}' in course '{\$a->course_shortname}' (id {\$a->course_id})";
$string['extremovedsuspendnoroles'] =  "Disabled enrolment and removed roles for user '{\$a->user_username}' in course '{\$a->course_shortname}' (id {\$a->course_id})";
$string['extremovedunenrol'] =  "Unenrol user '{\$a->user_username}' from course '{\$a->course_shortname}' (id {\$a->course_id})";
$string['failed'] = "Failed!\n";
$string['general_settings'] = 'General settings';

$string['group_contexts'] = 'List of contexts where groups are located. Separate different contexts with \';\'. For example: \'ou=users,o=org; ou=others,o=org\'';
$string['group_contexts_key'] = 'Group Contexts';
$string['group_created'] = 'group "{$a}" created';
$string['group_description'] = 'LDAP attribute to get the group description from';
$string['group_description_key'] = 'group description';
$string['group_existing'] = 'group "{$a}" already exists, skipping';
$string['group_filter'] = 'LDAP filter used to search groups. Usually \'cn=*\' or \'cn=*2013*\'';
$string['group_filter_key'] = 'Filter';
$string['group_found_users'] = 'Found {$a} users';
$string['group_lookup'] = 'group lookup settings';
$string['group_name'] = 'LDAP attribute to get the group name from';
$string['group_name_key'] = 'group name';
$string['group_no_users'] = 'no users found';
$string['memberofattribute'] = 'Name of the attribute that specifies which groups a given user or group belongs to (e.g., memberOf, groupMembership, etc.)';
$string['memberofattribute_key'] = '\'Member \' attribute';
$string['group_objectclass'] = 'objectClass used to search groups. Usually \'groupOfNames\' or \'posixGroup\'';
$string['group_objectclass_key'] = 'Object class';

$string['group_attribute'] = 'The attribute used to name/search groups. Usually \'cn\'.';
$string['group_attribute_key'] = 'Group attribute';
$string['group_search_sub'] = 'Search groups from subcontexts';
$string['group_sync_users'] = 'Synchronizing users...';
$string['group_synchronized_with_group']='group synchronized with LDAP group {$a}';
$string['group_synchronized_with_attribute']='group synchronized with LDAP attribute {$a}';


$string['host_url'] = 'Specify LDAP host in URL-form like \'ldap://ldap.myorg.com/\' or \'ldaps://ldap.myorg.com/\'';
$string['host_url_key'] = 'Host URL';
$string['user_attribute'] = 'If the group membership contains distinguised names, specify the same attribute you have used for the user \'ID Number\' mapping in the LDAP authentication settings';
$string['user_attribute_key'] = 'ID number attribute';
$string['ldap_encoding'] = 'Specify encoding used by LDAP server. Most probably utf-8, MS AD v2 uses default platform encoding such as cp1252, cp1250, etc.';
$string['ldap_encoding_key'] = 'LDAP encoding';
$string['ldap:manage'] = 'Manage LDAP enrol instances';
$string['login_sync_key']='Sync at login user';
$string['login_sync']='Enable syncing during interactive login ';

$string['memberattribute_isdn'] = 'If the group membership contains distinguised names, you need to specify it here. If it does, you also need to configure the remaining settings of this section';
$string['memberattribute_isdn_key'] = 'Member attribute uses dn';
$string['member_attribute'] = 'Group membership attribute in user entry. This denotes the user group(s) memberhsips. Usually \'member\', or \'memberUid\'';
$string['member_attribute_key'] = 'Group membership attribute';
$string['memberofattribute_isdn'] = 'If the user member of contains distinguised names, you need to specify it here. If it does, you also need to configure the remaining settings of this section';
$string['memberofattribute_isdn_key'] = 'Member of attribute uses ';$string['nested_groups'] = 'Do you want to use nested groups (groups of groups) for enrolment?';
$string['nested_groups'] = 'Do you want to use nested groups (groups of groups) for enrolment?';
$string['nested_groups_key'] = 'Nested groups';
$string['nested_groups_settings'] = 'Nested groups settings';
$string['nosuchrole'] = "No such role: '{\$a}'\n";
$string['objectclass'] = 'objectClass used to search courses. Usually \'group\' or \'posixGroup\'';
$string['objectclass_key'] = 'Object class';
$string['ok'] = "OK!\n";
$string['opt_deref'] = 'If the group membership contains distinguised names, specify how aliases are handled during search. Select one of the following values: \'No\' (LDAP_DEREF_NEVER) or \'Yes\' (LDAP_DEREF_ALWAYS)';
$string['opt_deref_key'] = 'Dereference aliases';
$string['phpldap_noextension'] = '<em>The PHP LDAP module does not seem to be present. Please ensure it is installed and enabled if you want to use this enrolment plugin.</em>';
$string['pluginname'] = 'LDAP Group enrolments';
$string['pluginname_desc'] = '<p>You can use an LDAP server to control your enrolments. It is assumed your LDAP tree contains groups that map to the courses, and that each of those groups will have membership entries to map to students.</p><p>It is assumed that courses are defined as groups in LDAP, with each group having multiple membership fields (<em>member</em> or <em>memberUid</em>) that contain a uniqueidentification of the user.</p><p>To use LDAP enrolment, your users <strong>must</strong> to have a valid  idnumber field. The LDAP groups must have that idnumber in the member fields for a user to be enrolled in the course. This will usually work well if you are already using LDAP Authentication.</p><p>Enrolments will be updated when the user logs in. You can also run a script to keep enrolments in synch. Look in <em>enrol/ldap/cli/sync.php</em>.</p>';
$string['pluginnotenabled'] = 'Plugin not enabled!';

$string['server_settings'] = 'LDAP server settings';
$string['synccourserole'] = "== Synching course '{\$a->idnumber}' for role '{\$a->role_shortname}'\n";
$string['synchronized_groups'] = 'Done. Synchornized {$a} groups.';
$string['synchronizing_groups'] = 'Synchronizing groups...';
$string['unassignrole']  = "Unassigning role '{\$a->role_shortname}' to user '{\$a->user_username}' from course '{\$a->course_shortname}' (id {\$a->course_id})\n";
$string['unassignroleid']  = "Unassigning role id '{\$a->role_id}' to user id '{\$a->user_id}'\n";
$string['unassignrolefailed'] = "Failed to unassign role '{\$a->role_shortname}' to user '{\$a->user_username}' from course '{\$a->course_shortname}' (id {\$a->course_id})\n";
$string['updatelocal'] = 'Update local data';
$string['user_attribute'] =  'If the group membership contains distinguised names, specify the attribute used to name/search users. If you are using LDAP authentication, this value should match the attribute specified in the \'ID Number\' mapping in the LDAP authentication plugin';
$string['user_attribute_key'] = 'ID number attribute';
$string['user_contexts'] = 'If the group membership contains distinguised names, specify the list of contexts where users are located. Separate different contexts with \';\'. For example: \'ou=users,o=org; ou=others,o=org\'';
$string['user_contexts_key'] = 'User Contexts';
$string['user_created'] = 'User "{$a}" created';
$string['user_dbinsert'] = 'Inserted user {$a->name} with id {$a->id}';
$string['user_dereference'] = 'Determines how aliases are handled during search. Select one of the following values: "No" (LDAP_DEREF_NEVER) or "Yes" (LDAP_DEREF_ALWAYS)';
$string['user_dereference_key'] = 'Dereference aliases';
$string['user_objectclass'] = 'Optional: Overrides objectClass used to name/search users on ldap_user_type. Usually you dont need to change this.';
$string['user_search_sub'] = 'If the group membership contains distinguised names, specify if the search for users is done in subcontexts too';
$string['user_search_sub_key'] = 'Search subcontexts';
$string['user_settings'] = 'User lookup settings';
$string['user_synchronized'] = 'Synchronized {$a->count} added, {$a->discount} removed users for group "{$a->group}"';
$string['user_type'] = 'If the group membership contains distinguished names, specify how users are stored in LDAP';
$string['user_type_key'] = 'User type';
$string['version'] = 'The version of the LDAP protocol your server is using';
$string['version_key'] = 'Version';
