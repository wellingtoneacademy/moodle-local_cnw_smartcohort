<?php

/**
 * Smart cohort
 *
 * @package     local_cnw_smartcohort
 * @copyright   CNW Rendszerintegrációs Zrt. <moodle@cnw.hu>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$contextid = optional_param('contextid', 0, PARAM_INT);

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
} else {
    $context = context_system::instance();
}

require_login();
require_capability('moodle/cohort:manage', $context);

$title = get_string('pluginname', 'local_cnw_smartcohort');
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/cnw_smartcohort/index.php');

echo $OUTPUT->header();

try {
    $action = optional_param('action', false, PARAM_ALPHA);
} catch (coding_exception $e) {
}
switch ($action) {

    //List
    case false:
        //Profile fields names
        $profileFields = [];
        $auth = new auth_plugin_base();
        $customfields = $auth->get_custom_user_profile_fields();
        $userfields = array_merge($auth->userfields, $customfields);
        $customfieldname = $DB->get_records('user_info_field', null, '', 'shortname, name');
        foreach ($userfields as $field) {
            $fieldname = $field;
            if ($fieldname === 'lang') {
                $fieldname = get_string('language');
            } else if (!empty($customfields) && in_array($field, $customfields)) {
                $fieldshortname = str_replace('profile_field_', '', $fieldname);
                $fieldname = $customfieldname[$fieldshortname]->name;
            } else if ($fieldname == 'url') {
                $fieldname = get_string('webpage');
            } else {
                $fieldname = get_string($fieldname);
            }
            $profileFields[$field] = $fieldname;
        }

        echo $OUTPUT->heading(get_string('filters', 'local_cnw_smartcohort'));
        echo $OUTPUT->render(new single_button(new moodle_url('/local/cnw_smartcohort/edit.php'), get_string('create_filter', 'local_cnw_smartcohort'), 'get'));

        $filters = smartcohort_get_filters(true);

        $data = [];
        foreach ($filters as $filter) {
            //BUTTONS
            $buttons = [
                html_writer::link(new moodle_url('/local/cnw_smartcohort/edit.php', ['id' => $filter->id, 'delete' => 1]), $OUTPUT->pix_icon('t/delete', get_string('delete')), ['title' => get_string('delete')]),
                html_writer::link(new moodle_url('/local/cnw_smartcohort/edit.php', ['id' => $filter->id]), $OUTPUT->pix_icon('t/edit', get_string('edit')), ['title' => get_string('edit')]),
                html_writer::link(new moodle_url('/local/cnw_smartcohort/view.php', ['id' => $filter->id]), $OUTPUT->pix_icon('i/users', get_string('users')), ['title' => get_string('users')]),
            ];
            if (!$filter->initialized) unset($buttons[1]);

            //RULES
            $rules = $DB->get_records('cnw_sc_rules', ['filter_id' => $filter->id]);
            $rulesString = "<ul>";
            if (empty($rules)) {
                $rulesString .= "<li>" . get_string('all_users', 'local_cnw_smartcohort') . "</li>";
            } else {
                $i = 0;
                foreach ($rules as $rule) {
                    if ($i == 0) {
                        $rulesString .= '<li>' . get_string('if', 'local_cnw_smartcohort', $profileFields[$rule->field]);
                    } else {
                        $rulesString .= '<li>' . get_string('and_if', 'local_cnw_smartcohort', $profileFields[$rule->field]);
                    }
                    $rulesString .= ' ' . get_string(str_replace(' ', '_', $rule->operator), 'local_cnw_smartcohort') . ' ' . get_string('to', 'local_cnw_smartcohort') .   ' <i>\'' . $rule->value . '\'</i></li>';
                    $i++;
                }
            }
            $rulesString .= "</ul>";

            //AFFECTED USERS COUNT
            $affectedUsers = $DB->count_records('cnw_sc_user_cohort', ['filter_id' => $filter->id]);

            //COHORT
            $cohort = $DB->get_record('cohort', array('id' => $filter->cohort_id));

            $data[] = [
                $filter->name,
                $cohort->name,
                $rulesString,
                ($filter->initialized == 0) ? get_string('no', 'local_cnw_smartcohort') : get_string('yes', 'local_cnw_smartcohort'),
                ($filter->deleted_flag != "0") ? get_string('deleting', 'local_cnw_smartcohort') : (($filter->initialized == 1) ? $affectedUsers : get_string('affect_need_initialize', 'local_cnw_smartcohort')),
                ($filter->deleted_flag == "0") ? implode(' ', $buttons) : ''
            ];
        }

        $table = new html_table();
        $table->id = 'filters';
        $table->attributes['class'] = 'admintable generaltable';
        $table->head = array(get_string('name', 'local_cnw_smartcohort'), get_string('cohort', 'local_cnw_smartcohort'), get_string('rules', 'local_cnw_smartcohort'), get_string('initialized', 'local_cnw_smartcohort'), get_string('affected_users', 'local_cnw_smartcohort'), get_string('edit'));
        $table->colclasses = array('', '', '', '', '', 'action');
        if (!empty($data)) {
            $table->data = $data;
        } else {
            $table->data = [[
                '<i>' . get_string('no_data', 'local_cnw_smartcohort') . '</i>', '', '', '', '', ''
            ]];
        }

        echo html_writer::table($table);

        break;
}


echo $OUTPUT->footer();