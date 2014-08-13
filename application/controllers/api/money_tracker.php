<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Secrets
 *
 * This is an example of a few basic user interaction methods you could use
 * all done with a hardcoded array.
 *
 * @package		CodeIgniter
 * @subpackage	Rest Server
 * @category	Controller
 * @author		Phil Sturgeon
 * @link		http://philsturgeon.co.uk/code/
 */

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
require APPPATH.'/libraries/REST_Controller.php';

class Money_Tracker extends REST_Controller{

    private $_db_config;
    private $_db_config_file = '/../../config/money-tracker.db_config.php';
    private $_origin;
    private $_model_dir = 'money_tracker/';

    public function __construct(){
        parent::__construct();
        if(!file_exists(__DIR__.$this->_db_config_file)){
            $this->send_response('DB config file not found');
        } else {
            $this->_db_config = require_once(__DIR__.$this->_db_config_file);
        }
    }

    public function delete_get() {
        // TODO - test
        $this->validate_access();

        $id = $this->get('id');
        $this->load->model($this->_model_dir.'attachment_model', 'Attachment', $this->_db_config);
        $attachment_id = $this->get('attachment');
        if(!empty($attachment_id)){
            $this->Attachment->delete($id, $attachment_id);
        } else {
            $this->load->model($this->_model_dir.'entry_model', 'Entry', $this->_db_config);
            $this->load->model($this->_model_dir.'account_model', 'Account', $this->_db_config);
            $this->Attachment->delete($id);
            $entry_value = $this->Entry->delete($id);
            $account_id = $this->Account->get_account_id_from_entry($id);
            $this->Account->update_balance(-1*$entry_value, $account_id);
        }
        $this->send_response(1, __FUNCTION__);
    }

    public function entry_get() {
        $this->validate_access();

        $id = $this->get('id');
        $this->load->model($this->_model_dir.'entry_model', 'Entry', $this->_db_config);
        $entry = $this->Entry->get($id);
        if(empty($entry)){
            $this->send_response('Entry not found');
        }
        if($entry['has_attachment']){
            $this->load->model($this->_model_dir.'attachment_model', 'Attachment', $this->_db_config);
            $attachments = $this->Attachment->get_entry();
            if(empty($attachments)){
                $entry['has_attachment']=0;
                $entry['attachments'] = array();
            } else {
                $entry['attachments'] = $attachments;
            }
        }

        $tag_ids = json_decode($entry['tags'], true);
        $entry['tags'] = $this->Entry->get_select_tags($tag_ids);
        $this->send_response($entry, __FUNCTION__);
    }

    public function count_post() {
        $this->validate_access();

        $where_array = $this->process_where_array(json_decode(base64_decode($this->post('where'))), true);
        $this->load->model($this->_model_dir.'entry_model', 'Entry', $this->_db_config);
        $total_entries = $this->Entry->count($where_array);
        $this->send_response($total_entries, __FUNCTION__);
    }

    public function list_post() {
        $this->validate_access();

        $limit = $this->post('limit');
        $start = $this->post('start');
        $where_array = $this->process_where_array(json_decode(base64_decode($this->post('where')), true));
        $this->load->model($this->_model_dir.'entry_model', 'Entry', $this->_db_config);
        $entries = $this->Entry->list_entries($where_array, $start, $limit);

        $this->send_response($entries, __FUNCTION__);
    }

    public function list_accounts_get(){
        // TODO - test
        $this->validate_access();

        $this->load->model($this->_model_dir.'account_model', 'Account', $this->_db_config);
        $accounts = $this->Account->list_accounts();
        $this->send_response($accounts, __FUNCTION__);
    }

    public function save_post() {
        // TODO - test
        $this->validate_access();

        $entry_data = json_decode(base64_decode($this->post('data')), true);
        if(empty($entry_data)){
            $this->send_response(0, __FUNCTION__);
        }

        $this->load->model($this->_model_dir.'entry_model', 'Entry', $this->_db_config);
        $this->load->model($this->_model_dir.'attachment_model', 'Attachment', $this->_db_config);
        $this->load->model($this->_model_dir.'account_model', 'Account', $this->_db_config);

        $account_id = null;
        if(!empty($entry_data['id']) || $entry_data['id'] != -1){
            $existing_entry_data = $this->Entry->get($entry_data['id']);
            if(!empty($existing_entry_data)){
                $existing_entry_data['value'] *= ($existing_entry_data['expense'] ? -1 : 1);
                $account_id = $this->Account->get_account_id_from_entry($existing_entry_data['id']);
                $this->Account->update_balance(-1*$entry_data['value'], $account_id);
            }
        }

        $entry_id = $this->Entry->save($entry_data);
        $this->Attachment->save($entry_id, $entry_data['attachments']);
        $entry_data['value'] *= ($entry_data['expense'] ? -1 : 1);
        if(is_null($account_id)){
            $account_id = $this->Account->get_account_id_from_entry($entry_id);
        }
        $this->Account->update_balance($entry_data['value'], $account_id);
        $this->send_response(1, __FUNCTION__);
    }

    public function user_key_get(){
        // TODO - rebuild / decided if I actually need this
        // TODO - get user decryption key
        $this->send_response("This doesn't work yet");
    }

    public function tags_get(){
        $this->validate_access();

        $this->load->model($this->_model_dir.'entry_model', 'Entry', $this->_db_config);
        $tags = $this->Entry->get_all_tags();
        $this->send_response($tags, __FUNCTION__);
    }

    private function validate_access(){
        // TODO - finish...??
        $this->load->model($this->_model_dir.'api_key_model', 'API');
        $valid_key = $this->API->validate();
        if(!$valid_key){
            $this->send_response("Invalid API Key:".$this->API->get_header_key());
        }

//        $this->load->model('secrets/user_model', 'User', $this->_db_config);
//        $valid_user = $this->User->validate($user_id);
//        if(!$valid_user){
//            $this->send_response("User does not exist", true);
//        }

        $this->_origin= $this->API->get_key_origin();
    }

    private function send_response($data, $function=false){
        if($function){
            $code = 200;
            $error_msg = '';
            switch($function){
                case 'list_accounts_get':
                case 'entry_get':
                case 'tags_get':
                case 'list_post':
                    $result = base64_encode(json_encode($data));
                    break;
                default:
                    $result = $data;
            }
        } else {
            $code = 400;
            $error_msg = $data;
            $result = '';
        }
        $this->response(array('error'=>$error_msg, 'result'=>$result), $code);
    }

    private function process_where_array($where_array){
        $where_stmt = array('entries.deleted'=>0);
        if(!empty($where_array['start_date']))
            $where_stmt["entries.`date` >="] = $where_array['start_date'];
        if(!empty($where_array['end_date']))
            $where_stmt["entries.`date` <="] = $where_array["end_date"];
        if(!empty($where_array['account_type']))
            $where_stmt["entries.account_type"] = $where_array["account_type"];
        if(isset($where_array['attachments']) && in_array($where_array['attachments'], array(0,1)))
            $where_stmt["entries.has_attachment"] = $where_array["attachments"];
        if(!empty($where_array['confirm'])){
            $where_stmt["entries.confirm"] = 0;
            unset($where_array['confirm']);
        }
        if(!empty($where_array['min_value']))
            $where_stmt["entries.value >="] = $where_array["min_value"];
        if(!empty($where_array['max_value']))
            $where_stmt["entries.value <="] = $where_array["max_value"];
        if(!empty($where_array['group']))
            $where_stmt["account_types.account_group"] = $where_array["group"];
        if(!empty($where_array['tags'])){
            foreach($where_array['tags'] as $tag){
                $tag_array = array();
                $tag_array[] = "entries.tags LIKE '[".$tag."]'";
                $tag_array[] = "entries.tags LIKE '[".$tag.",%'";
                $tag_array[] = "entries.tags LIKE '%,".$tag.",%'";
                $tag_array[] = "entries.tags LIKE '%,".$tag."]'";
                $where_stmt[] = '('.implode(" OR ", $tag_array).')';
            }
        }
        return $where_stmt;
    }
}