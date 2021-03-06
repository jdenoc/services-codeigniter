<?php
/**
 * User: denis
 * Date: 2014-07-26
 */

require_once(__DIR__.'/../api_key_interface.php');

class Api_key_model extends CI_Model implements api_key_interface{

    public function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    /**
     * @return bool
     */
    public function validate(){
        $client = $this->get_header_key();
        $server = $this->get_service_keys();
        return in_array($client, $server);
    }

    /**
     * @return string
     */
    public function get_header_key(){
        return $this->input->get_request_header('Authorization');
    }

    /**
     * @return array
     */
    public function get_service_keys(){
        // TODO - get API from local config file or DB
        return array(
            'web'=>'test',
            'app'=>'app_test'
        );
    }

    /**
     * @return string
     */
    public function get_key_origin(){
        return array_search(
            $this->get_header_key(),
            $this->get_service_keys()
        );
    }
}