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

    private function getUserInfobyEmail($email) {
        $res_users_fields = [
            'id',
        ];
        $res_partner_fields = [
            'id',
            'name'
        ];
        $criteria = [
            ['email', '=', $email],
        ];

        $partnerInfo = $this->APIClient->search_read('res.partner', $criteria, $res_partner_fields, 1);
        $userInfo = $this->APIClient->search_read('res.users', $criteria, $res_users_fields, 1);

        $mergedUserInfo = [
            'email' => $email,
            'userId' => $userInfo[0]['id'],
            'partnerId' => $partnerInfo[0]['id'],
            'name' => $partnerInfo[0]['name']
        ];

        return ($mergedUserInfo);
    }

    function addIssue($summary, $description) {
        $data = [
            'description' => $description,
            'name' => $summary,
            'project_id' => (int) $this->cfg->project_id,
            'user_id' => (int) $this->getUserInfobyEmail($this->cfg->assignee)['userId'],
            'stage_id' => (int) $this->cfg->stack_id,
            'x_studio_tasktype' => (int) $this->cfg->task_type,
        ];

        try {
            $id = $this->APIClient->create('project.task', $data);
            $this->APIClient->write('project.task', [$id], ['x_studio_reporter' => (int) $this->getUserInfobyEmail($this->cfg->reporter)['userId']]);

            $tag_ids_array = array_map('intval', explode(",", $this->cfg->tag_ids));
            $this->APIClient->write('project.task', [$id], ['tag_ids' => [[(int) $this->cfg->task_type, false, $tag_ids_array]]]); //Not sure if the first value is x_studio_tasktype

            $followers_args = [[(int) $id]];

            $partner_ids_array = array_map(function($email) {
                return (int) $this->getUserInfobyEmail($email)['partnerId'];
            }, explode(",", $this->cfg->followers));
            $followers_kwargs = ["partner_ids" => $partner_ids_array];

            $this->APIClient->model_execute_kw('project.task', 'message_subscribe', $followers_args, $followers_kwargs);

            $this->sendEmail($this->cfg->username, $this->cfg->followers, 'New Testlink bug created: ' . $summary, $this->cfg->uriview . $id);
        } catch (Exception $e) {
            tLog($e->getMessage(), 'ERROR');
        }

        return $id;
    }

    private function sendEmail($from, $to, $subject, $body) {
        /* Send mail via Odoo to the followers we have set */
        $invitation_mail = [
            'subject' => $subject,
            'body_html' => $body,
            'email_from' => $from,
            'email_to' => $to
        ];

        $mail_id = $this->APIClient->create('mail.mail', $invitation_mail);

        /*
         * kw_args cannot be empty, it doesn't work in other way. That's why 
         * we set an existing variable to its default value. And it always throws
         * a ResponseFaultException due to some internal Odoo logic. As we weren't
         * able to identify which of our parameters is causing this issue, we ignore
         * this exception.
         */
        try {
            $this->APIClient->model_execute_kw('mail.mail', 'send', [[$mail_id]], ["auto_commit" => false]);
        } catch (Ripoo\Exception\ResponseFaultException $e) {
            /* We ignore it*/
        }
    }

    /*
     * This should check the accuracy of the provided variables, but for the moment
     * it won't be done.
     */

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
                "\t<reporter>REPORTER_EMAIL</reporter>\n" .
                "\t<assignee>ASIGNEE_EMAIL</assignee>\n" .
                "\t<project_id>PROJECT_ID</project_id>\n" .
                "\t<task_type>TASK_TYPE</task_type>\n" .
                "\t<!-- stack_id is refered to the stack in the kanban-style view,\n" .
                "\t which corresponds to the Odoo's field stage_id -->\n" .
                "\t<stack_id>STACK_ID</stack_id>\n" .
                "\t<!-- tag_ids is a list of the ids separated by comma\n" .
                "\t (e.g. <tag_ids>40,42</tag_ids>) -->\n" .
                "\t<tag_ids>TAG_IDS</tag_ids>\n" .
                "\t<!-- followers is a list of the people e-mails that want to follow the task Testlink creates separated by comma\n" .
                "\t (e.g. <followers>alice@example.com,bob@example.com</followers>) -->\n" .
                "\t<followers>FOLLOWERS_EMAILS</followers>\n" .
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
