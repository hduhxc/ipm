<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Action extends CI_Controller {
	const SUCCESS = 0;
	const CONTENT_EMPTY = 1;
    const WRONG_CID = 2;

	public function __construct() {
		parent::__construct();
		$this->load->library('check');
		$this->load->model('Action_model');
	}

    public function set_star($article_id) {
    	$uid = $this->check->get_uid() or exit();

    	$this->Action_model->set_star($uid, $article_id);
        echo json_encode(array("errorcode" => self::SUCCESS, 
        					"msg" => "成功点赞/取消赞"));

        return true;
    }

    public function ins_comment($article_id) {
    	$uid = $this->check->get_uid() or exit();
    	$data = $this->input->post();

    	$this->Action_model->ins_comment($data);
    	echo json_encode(array("errorcode" => self::SUCCESS,
    						"msg" => "成功发布评论"));

    	return true;
    }

    public function get_comment($article_id, $page) {
		$uid = $this->check->get_uid(0);
    	$comments = $this->Action_model->get_comment($uid, $article_id, $page);
		$ret = array("errorcode" => self::SUCCESS, "msg" => "成功发布", "comments" => $comments);
		echo json_encode($ret);

    	return true;
    }
	
	public function set_comment_like($cid) {
		$uid = $this->check->get_uid() or exit();
		$this->Action_model->set_comment_like($uid, $cid);
		
		echo json_encode(array("errorcode" => self::SUCCESS, "msg" => "成功点赞/取消赞"));
		
		return true;
	}
	
	public function del_comment($cid) {
		$uid = $this->check->get_uid() or exit();
		$comment = $this->Action_model->get_comment_by_id($cid);
		
		if (!$comment || $comment['uid'] != $uid) {
			$ret = array("errorcode" => self::WRONG_CID, "msg" => "错误的评论号");
		} else {
			$this->Action_model->del_comment($cid);
			$ret = array("errorcode" => self::SUCCESS, "msg" => "删除成功");	
		}
		
		echo json_encode($ret);
		
		return true;
	}

    public function set_favorite($article_id) {
    	$uid = $this->check->get_uid() or exit();
    	$this->Action_model->set_favorite($uid, $article_id);
    	echo json_encode(array("errorcode" => self::SUCCESS,
    						"msg" => "成功加入到收藏列表"));

    	return true;
    }

    public function get_favorites() {
    	$uid = $this->check->get_uid() or exit();
    	$data = $this->Action_model->get_favorites($uid);
		
		if (empty($data)) 
			$ret = array("errorcode" => self::CONTENT_EMPTY,
						"msg" => "列表为空");
		else
			$ret = array("errorcode" => self::SUCCESS,
						"msg" => "获取成功",
						"essaylist" => $data);
						
        echo json_encode($ret);

        return true;
    }
}
?>
