<?php
class User_model extends CI_Model
{
    const INTERNAL_ERROR = -1;
    const EXPIRE_TIME = 7 * 24 * 3600;  // A week
    const TOKEN_LEN = 8;

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    private function get_user($uname) {
        $this->db->select('*');
        $this->db->where('username', $uname);
        $query = $this->db->get('ipm_user')->row_array();

        return $query;
    }

    private function gen_token() {
        $charset = "ABCDEDFHIJKLMNOPQRSTUVWXYZ0123456789abcdedghijklmnopqrstuvwxyz";
        $max = strlen($charset) - 1;
        $token = "";

        for ($i = 0; $i < self::TOKEN_LEN; $i++)
            $token .= $charset[rand(0, $max)];

        return $token;
    }

    public function get_uid($token) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $uid = $redis->get("User:{$token}");
        if (!$uid) 
            return false;

        $redis->expire("User:{$token}", self::EXPIRE_TIME);
        return $uid;
    }
    
    public function get_user_info($query) {
        $this->db->select('id, username, introduce');
        $this->db->where($query);
        $user = $this->db->get('ipm_user')->row_array();
        $user['uid'] = $user['id'];
        unset($user['id']);
        
        return $user;
    }
    
    private function set_token($uid, $token) {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->setex("User:{$token}", self::EXPIRE_TIME, $uid)
            or die(json_encode(array("errcode" => self::INTERNAL_ERROR,
                                    "errmsg" => "内部错误")));

        return true;
    }

    public function register($form) {
        if ($this->get_user($form['username']))
            return false;

        $this->db->insert('ipm_user', $form)
            or die(json_encode(array("errcode" => self::INTERNAL_ERROR,
                                    "errmsg" => "内部错误")));

        return true;
    }

    public function login($uname, $pwd) {
        $user = $this->get_user($uname);
        if (!$user || $pwd != $user['password'])
            return false;

        $token = $this->gen_token();
        $this->set_token($user['id'], $token);

        return $token;
    }

    public function get_info($uid) {
        $this->db->select('username, introduce');
        $this->db->where('id', $uid);
        $data = $this->db->get('ipm_user')->row_array();

        if ($data)
            $data['portrait'] = 'http://img4.duitang.com/uploads/item/201602/23/20160223104150_x2jAC.jpeg';

        return $data;
    }
}
?>
