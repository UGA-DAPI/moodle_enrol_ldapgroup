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
 * LDAP enrolment plugin admin setting classes
 *
 * @package    enrol_ldapgroup
 * @author     Iñaki Arenaza
 * @copyright  2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class admin_setting_configtext_trim_lower extends admin_setting_configtext {
    /* @var boolean whether to lowercase the value or not before writing in to the db */
    private $lowercase;

    /**
     * Constructor: uses parent::__construct
     *
     * @param string $name unique ascii name, either 'mysetting' for settings that in config, or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting default value for the setting
     * @param boolean $lowercase if true, lowercase the value before writing it to the db.
     * @param boolean $enabled if true, the input field is enabled, otherwise it's disabled.
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, $lowercase=false, $enabled=true) {
        $this->lowercase = $lowercase;
        $this->enabled = $enabled;
        parent::__construct($name, $visiblename, $description, $defaultsetting);
    }

    /**
     * Saves the setting(s) provided in $data
     *
     * @param array $data An array of data, if not array returns empty str
     * @return mixed empty string on useless data or success, error string if failed
     */
    public function write_setting($data) {
        if ($this->paramtype === PARAM_INT and $data === '') {
            // do not complain if '' used instead of 0
            $data = 0;
        }

        // $data is a string
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }
        if ($this->lowercase) {
            $data = core_text::strtolower($data);
        }
        if (!$this->enabled) {
            return '';
        }
        return ($this->config_write($this->name, trim($data)) ? '' : get_string('errorsetting', 'admin'));
    }

    /**
     * Return an XHTML string for the setting
     * @return string Returns an XHTML string
     */
    public function output_html($data, $query='') {
        $default = $this->get_defaultsetting();
        $disabled = $this->enabled ? '': ' disabled="disabled"';
        return format_admin_setting($this, $this->visiblename,
        '<div class="form-text defaultsnext"><input type="text" size="'.$this->size.'" id="'.$this->get_id().'" name="'.$this->get_full_name().'" value="'.s($data).'" '.$disabled.' /></div>',
        $this->description, true, '', $default, $query);
    }

}
