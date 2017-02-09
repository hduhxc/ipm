<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Check {
	const ACCESS_FORBIDDEN = -1;
    private $CI;

	public function __construct() {
		$this->CI = &get_instance();
		$this->CI->load->model('User_model');
	}

	public function get_uid($is_report = 1) {

        try {
            if (!isset($_SERVER['HTTP_TOKEN']) || !($token = $_SERVER['HTTP_TOKEN']))
                throw new Exception();
            if (!($uid = $this->CI->User_model->get_uid($token)))
                throw new Exception();

        } catch (Exception $e) {

            if ($is_report)
                echo json_encode(array("errcode" => self::ACCESS_FORBIDDEN, "errmsg" => "非法访问"));
            
            return false;
        }

        return $uid;
	}
}
?>
