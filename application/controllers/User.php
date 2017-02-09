<?php
class User extends CI_Controller
{
    const SUCCESS = 0;
    const WRONG_USER_OR_PWD = 1;
    const USER_EXIST = 2; 
    const CONTENT_EMPTY = 3;

    public function __construct() {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->library('check');
    }

    public function login() {
        $uname = $this->input->get('username');
        $pwd = $this->input->get('password');

        if (!$uname || !($token = $this->User_model->login($uname, $pwd)))
            $ret = array("errorcode" => self::WRONG_USER_OR_PWD, 
                        "msg" => "错误的用户名或密码");
        else {
            $user = $this->User_model->get_user_info(array('username' => $uname));
            $ret = array("errorcode" => self::SUCCESS,
                        "msg" => "登录成功",
                        "token" => $token);
            $ret = array_merge($ret, $user);
        }
        
        echo json_encode($ret);
        return true;
    }

    public function register() {
        $this->load->helper('array');
        $data = $this->input->post(NULL);
        $data = elements(array('username', 'password', 'introduce'), $data);
        
        if (!$this->User_model->register($data))
            $ret = array("errorcode" => self::USER_EXIST,
                        "msg" => "用户名已存在");
        else
            $ret = array("errorcode" => self::SUCCESS,
                        "msg" => "注册成功");

        echo json_encode($ret);
        return true;
    }

    public function get_info($uid) {
        $data = $this->User_model->get_info($uid);
        
        if (empty($data)) {
            $ret = array("errorcode" => self::CONTENT_EMPTY,
                        "msg" => "没有该用户");
        } else {
            $ret = array("errorcode" => self::SUCCESS, 
                        "msg" => "获取成功");
            $ret = array_merge($data, $ret);
        }
        
        echo json_encode($ret);
        return true;
    }
}
