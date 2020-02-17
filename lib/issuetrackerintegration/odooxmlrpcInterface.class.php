<?php

/**
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * @author   AnxoBel (GITHUB)
 *
 * 
 * */
use Ripoo\OdooClient;

// Load Composer's autoloader
require 'autoload.php';

class odooxmlrpcInterface extends issueTrackerInterface {

    private $APIClient;

    function __construct($type, $config, $name) {
        $this->name = $name;
        $this->interfaceViaDB = false;
        $this->methodOpt['buildViewBugLink'] = array('addSummary' => true, 'colorByStatus' => false);
        $this->guiCfg = array('use_decoration' => true);

        if (!$this->setCfg($config)) {
            return false;
        }

        $this->completeCfg();
        $this->connect();
    }

    function connect() {
        $this->createAPIClient();
    }

    function createAPIClient() {
        try {
            $this->APIClient = new OdooClient((string) $this->cfg->uribase,
                    (string) $this->cfg->database,
                    (string) $this->cfg->username,
                    (string) $this->cfg->password);
            $this->connected = true;
        } catch (Exception $e) {
            $this->connected = false;
            tLog(__METHOD__ . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    function completeCfg() {
        $base = trim($this->cfg->uribase, "/") . '/';

        if (!property_exists($this->cfg, 'uriview')) {
            $this->cfg->uriview = $base . '/web#action=176&model=project.task&view_type=form&menu_id=132&active_id=' . $this->cfg->project_id . '&id=';
        }

        if (!property_exists($this->cfg, 'uricreate')) {
            $this->cfg->uricreate = $base;
        }
    }

    function addIssue($summary, $description) {
        $data = [
            'description' => $description,
            'name' => $summary,
            'project_id' => (int) $this->cfg->project_id,
            'user_id' => (int) $this->cfg->assignee_id,
            'stage_id' => (int) $this->cfg->stack_id,
            'x_studio_tasktype' => (int) $this->cfg->task_type,
        ];

        try {
            $id = $this->APIClient->create('project.task', $data);
            $this->APIClient->write('project.task', [$id], ['x_studio_reporter' => $this->cfg->reporter_id]);

            $tag_ids_array = array_map('intval', explode(",", $this->cfg->tag_ids));
            $this->APIClient->write('project.task', [$id], ['tag_ids' => [[(int) $this->cfg->task_type, false, $tag_ids_array]]]); //Not sure if the first value is x_studio_tasktype

            $followers_args = [[(int) $id]];
            $partner_ids_array = array_map('intval', explode(",", $this->cfg->partner_ids));

            $followers_kwargs = ["partner_ids" => $partner_ids_array];

            $this->APIClient->model_execute_kw('project.task', 'message_subscribe', $followers_args, $followers_kwargs);
        } catch (Exception $e) {
            tLog($e->getMessage(), 'ERROR');
        }

        return $id;
    }

    function canCreateViaAPI() {
        return true;
    }

    function checkBugIDExistence($issueID) {
        if (($status_ok = $this->checkBugIDSyntax($issueID))) {
            $issue = $this->getIssue($issueID);
            $status_ok = is_object($issue) && !is_null($issue);
        }
        return $status_ok;
    }

    function checkBugIDSyntax($issueID) {
        return $this->checkBugIDSyntaxNumeric($issueID);
    }

    public function getIssue($issueID) {
        $criteria = [
            ['id', '=', $issueID],
        ];
        $limit = 1;

        $issueArray = $this->APIClient->search_read('project.task', $criteria, getOdooProjectTaskFields(), $limit);

        if (!empty($issueArray)) {
            return $issueArray[0];
        } else {
            return null;
        }
    }

    public static function getCfgTemplate() {
        $template = "<!-- Template " . __CLASS__ . " -->\n" .
                "<issuetracker>\n" .
                "\t<username>USERNAME</username>\n" .
                "\t<password>PASSWORD</password>\n" .
                "\t<uribase>http://YOURINSTANCE.odoo.com/</uribase>\n" .
                "\t<database>DATABASE_NAME</database>\n" .
                "\t<reporter_id>REPORTER_ID</reporter_id>\n" .
                "\t<assignee_id>ASIGNEE_ID</assignee_id>\n" .
                "\t<project_id>PROJECT_ID</project_id>\n" .
                "\t<task_type>TASK_TYPE</task_type>\n" .
                "\t<!-- stack_id is refered to the stack in the kanban-style view,\n" .
                "\t which corresponds to the Odoo's field stage_id -->\n" .
                "\t<stack_id>STACK_ID</stack_id>\n" .
                "\t<!-- tag_ids is a list of the ids separated by comma\n" .
                "\t (e.g. <tag_ids>40,42</tag_ids>) -->\n" .
                "\t<tag_ids>TAG_IDS</tag_ids>\n" .
                "\t<!-- partner_ids is a list of the ids separated by comma\n" .
                "\t (e.g. <partner_ids>50,51</partner_ids>) -->\n" .
                "\t<partner_ids>PARTNER_IDS</partner_ids>\n" .
                "</issuetracker>\n";

        return $template;
    }

    private static function getOdooProjectTaskFields() {
        return [
            '__last_update',
            'access_token',
            'access_url',
            'access_warning',
            'active',
            'activity_date_deadline',
            'activity_ids',
            'activity_state',
            'activity_summary',
            'activity_type_id',
            'activity_user_id',
            'allow_timesheets',
            'analytic_account_active',
            'attachment_ids',
            'billable_type',
            'child_ids',
            'color',
            'company_id',
            'create_date',
            'create_uid',
            'date_assign',
            'date_deadline',
            'date_end',
            'date_last_stage_update',
            'date_start',
            'description',
            'display_name',
            'displayed_image_id',
            'effective_hours',
            'email_cc',
            'email_from',
            'id',
            'is_project_map_empty',
            'kanban_state',
            'kanban_state_label',
            'legend_blocked',
            'legend_done',
            'legend_normal',
            'manager_id',
            'message_attachment_count',
            'message_channel_ids',
            'message_follower_ids',
            'message_has_error',
            'message_has_error_counter',
            'message_ids',
            'message_is_follower',
            'message_main_attachment_id',
            'message_needaction',
            'message_needaction_counter',
            'message_partner_ids',
            'message_unread',
            'message_unread_counter',
            'name',
            'notes',
            'parent_id',
            'partner_id',
            'planned_hours',
            'priority',
            'progress',
            'project_id',
            'rating_count',
            'rating_ids',
            'rating_last_feedback',
            'rating_last_image',
            'rating_last_value',
            'remaining_hours',
            'sale_line_id',
            'sale_order_id',
            'sequence',
            'stage_id',
            'subtask_count',
            'subtask_effective_hours',
            'subtask_planned_hours',
            'subtask_project_id',
            'tag_ids',
            'timesheet_ids',
            'total_hours_spent',
            'user_email',
            'user_id',
            'website_message_ids',
            'working_days_close',
            'working_days_open',
            'working_hours_close',
            'working_hours_open',
            'write_date',
            'write_uid',
            'x_studio_priority',
            'x_studio_reporter',
            'x_studio_tasktype'
        ];
    }

}
