<?php
class Action_model extends CI_Model
{
	const COMMENT_LIMIT = 20;
	const REVOKE = 1;
	const SET = 0;
	const HOT_ARTICLE_SET = "Article:hot";
	const MSG_QUEUE = "MySQL:queue";
	private $redis = null;

	public function __construct() {
		parent::__construct();
		$this->redis = new Redis();
		$this->redis->connect('127.0.0.1', 6379);
		$this->load->database();
	}

	public function get_star($uid, $article_id) {
		$article_set = "Article:{$article_id}:star";

		if ($this->redis->exists($article_set))
			return $this->redis->sismember($article_set, $uid);

		$this->db->select('id');
		$this->db->where('uid', $uid);
		$this->db->where('article_id', $article_id);
		$state = isset($this->db->get('ipm_star')->row()->id);

		return $state;
	}

	private function set_star_db($uid, $article_id, $state) {

		if ($state == self::REVOKE) {
			$this->db->where('uid', $uid);
			$this->db->where('article_id', $article_id);
			$sql = $this->db->get_compiled_delete('ipm_star');
		}

		if ($state == self::SET) {
			$this->db->set('uid', $uid);
			$this->db->set('article_id', $article_id);
			$sql = $this->db->get_compiled_insert('ipm_star');
		}

		$this->redis->rpush(self::MSG_QUEUE, $sql);
	}

	public function set_star($uid, $article_id) {
		$article_set = "Article:{$article_id}:star";

		if ($this->redis->sismember(self::HOT_ARTICLE_SET, strval($article_id))) {

			if ($this->redis->sismember($article_set, $uid)) {
				$this->redis->srem($article_set, $uid);
				$this->set_star_db($uid, $article_id, self::REVOKE);
			} else {
				$this->redis->sadd($article_set, $uid);
				$this->set_star_db($uid, $article_id, self::SET);
			}

			return true;
		}

		$state = $this->get_star($uid, $article_id);
		$this->set_star_db($uid, $article_id, $state);

		return true;
	}

	public function get_comment_cnt($article_id) {
		$comment_cnt = "Article:{$article_id}:comment:cnt";

		if ($cnt = $this->redis->get($comment_cnt))
			return $cnt;

		$this->db->where('eid', $article_id);
		$cnt = $this->db->count_all_results('ipm_comment');

		return $cnt;
	}

	public function ins_comment($data) {
		$this->db->set($data);
		$sql = $this->db->get_compiled_insert('ipm_comment');
		$this->redis->rpush(self::MSG_QUEUE, $sql);
		
		if ($this->redis->sismember(self::HOT_ARTICLE_SET, $data['eid'])) {
			$comment_cnt = "Article:{$data['eid']}:comment:cnt";
			$this->redis->incr($comment_cnt);
		}
		
		return true;
	}
	
	public function del_comment($cid) {
		$this->db->where('id', $cid);
		$sql = $this->db->get_compiled_delete('ipm_comment');
		$this->redis->rpush(self::MSG_QUEUE, $sql);
		
		$this->db->where('cid', $cid);
		$sql = $this->db->get_compiled_delete('ipm_comment_like');
		$this->redis->rpush(self::MSG_QUEUE, $sql);
		
		return true;
	}

	// public function ins_comment($uid, $article_id, $content) {
	// 	$comment_list = "Article:{$article_id}:comment";
	// 	$comment_cnt = "Article:{$article_id}:comment:cnt";

	// 	$this->db->select('username');
	// 	$this->db->where('id', $uid);
	// 	$username = $this->db->get('ipm_user')->row()->username;

	// 	if ($this->redis->sismember(self::HOT_ARTICLE_SET, $article_id)) {
	// 		$comment = $this->encode_comment($username, $content);
	// 		$this->redis->lpush($comment_list, $comment);
	// 		$this->redis->incr($comment_cnt);
	// 		$this->ins_comment_db($uid, $article_id, $content);

	// 		if ($this->redis->llen($comment_list) > self::COMMENT_LIMIT + 5)
	// 			$this->redis->ltrim(0, self::COMMENT_LIMIT);

	// 		return true;
	// 	}

	// 	$this->ins_comment_db($uid, $article_id, $content);

	// 	return true;
	// }
	
	private function get_comment_like($uid, $cid) {
		$this->db->select('id');
		$this->db->where('uid', $uid);
		$this->db->where('cid', $cid);
		$state = isset($this->db->get('ipm_comment_like')->row()->id);

		return $state;
	}
	
	public function set_comment_like($uid, $cid) {
		$is_like = $this->get_comment_like($uid, $cid);
		
		if ($is_like) {
			$this->db->where('uid', $uid);
			$this->db->where('cid', $cid);
			$sql = $this->db->get_compiled_delete('ipm_comment_like');
			$this->redis->rpush(self::MSG_QUEUE, $sql);
		
			$this->db->set('likenum', 'likenum - 1', FALSE);
			$sql = $this->db->get_compiled_update('ipm_comment');
			$this->redis->rpush(self::MSG_QUEUE, $sql);
		} else {
			$this->db->set('uid', $uid);
			$this->db->set('cid', $cid);
			$sql = $this->db->get_compiled_insert('ipm_comment_like');
			$this->redis->rpush(self::MSG_QUEUE, $sql);
		
			$this->db->set('likenum', 'likenum + 1', FALSE);
			$sql = $this->db->get_compiled_update('ipm_comment');
			$this->redis->rpush(self::MSG_QUEUE, $sql);
		}
		
		return true;
	}
	
	public function get_comment_by_id($cid) {
		$this->db->select('*');
		$this->db->where('id', $cid);
		$data = $this->db->get('ipm_comment')->row_array();
		
		return $data;	
	}
	
	public function get_comment($uid, $article_id, $page) {
		$off = $page * self::COMMENT_LIMIT;
		$limit = self::COMMENT_LIMIT;
		
 		if ($uid) {
 			$sql = <<<EOF
				SELECT c.*, l.id AS islike
				FROM ipm_comment AS c
				LEFT OUTER JOIN ipm_comment_like AS l
				ON l.uid = {$uid} AND l.cid = c.id
				WHERE c.eid = {$article_id}
				LIMIT {$off}, {$limit}
EOF;
 			$data = $this->db->query($sql)->result_array();
			 
 		} else {
 			$this->db->select('*');
 			$this->db->where('eid', $article_id);
 			$this->db->limit($limit, $off);
 			$data = $this->db->get('ipm_comment')->result_array();
 		}

		return $data;
	}

	public function get_favorite($uid, $article_id) {
		$article_set = "Article:{$article_id}:favorite";

		if ($this->redis->exists($article_set))
			return $this->redis->sismember($article_set, $uid);

		$this->db->select('id');
		$this->db->where('uid', $uid);
		$this->db->where('article_id', $article_id);
		$state = isset($this->db->get('ipm_favorite')->row()->id);

		return $state;
	}

	private function set_favorite_db($uid, $article_id, $state) {

		if ($state == self::REVOKE) {
			$this->db->where('uid', $uid);
			$this->db->where('article_id', $article_id);
			$sql = $this->db->get_compiled_delete('ipm_favorite');
		}

		if ($state == self::SET) {
			$this->db->set('uid', $uid);
			$this->db->set('article_id', $article_id);
			$sql = $this->db->get_compiled_insert('ipm_favorite');
		}

		$this->redis->rpush(self::MSG_QUEUE, $sql);

		return true;
	}

	public function set_favorite($uid, $article_id) {
		$article_set = "Article:{$article_id}:favorite";

		if ($this->redis->sismember(self::HOT_ARTICLE_SET, $article_id)) {

			if ($this->redis->sismember($article_set, $uid)) {
				$this->redis->srem($article_set, $uid);
				$this->set_favorite_db($uid, $article_id, self::REVOKE);
			} else {
				$this->redis->sadd($article_set, $uid);
				$this->set_favorite_db($uid, $article_id, self::SET);
			}

			return true;
		}

		$state = $this->get_favorite($uid, $article_id);
		$this->set_favorite_db($uid, $article_id, $state);

		return true;
	}

	public function get_favorites($uid) {
		$this->db->select('eid, tag, ctid, title, cover, content');
		$this->db->where('uid', $uid);
		$data = $this->db->get('ipm_article_favorite')->result_array();

		return $data;
	}
}
?>