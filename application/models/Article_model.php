<?php
class Article_model extends CI_Model
{
	private $redis = null;
	const PAGE_LIMIT = 20;
	const LIST_LIMIT = 200;

	public function __construct() {
		parent::__construct();
		$this->load->database();
		$this->redis = new Redis();
		$this->redis->connect('127.0.0.1', 6379);
	}

	private function decode_article_link($article_link) {
		$arr = array();
		list($arr['id'], $arr['title'], $arr['summary'], $arr['link'], $arr['cover'], $arr['category']) = explode('!@', $article_link);

		return $arr;
	}

	private function encode_article_link($arr) {
		return "{$arr['id']}!@{$arr['title']}!@{$arr['summary']}!@{$arr['link']}!@{$arr['cover']}!@{$arr['category']}";
	}

	public function get_categories() {
		$this->db->select('id, name');
		$data = $this->db->get('ipm_category')->result_array();

		return $data;
	}

	public function get_category_article($cat_id, $off) {
		$category_list = "Article:category:{$cat_id}";
		$arr = array();

		if ($off >= 0 && $off + self::PAGE_LIMIT <= self::LIST_LIMIT) {
			$data = $this->redis->lrange($category_list, $off, $off + self::PAGE_LIMIT - 1);

			foreach ($data as $val)
				array_push($arr, $this->decode_article_link($val));

			if (!empty($arr))
				return $arr;
		}

		$this->db->select('id, title, summary, link, cover, category');
		if ($cat_id)
			$this->db->where('category', $cat_id);
		$this->db->limit(self::PAGE_LIMIT, $off);
		$this->db->order_by('id', 'DESC');
		$arr = $this->db->get('ipm_article')->result_array();

		return $arr;
	}
}
?>
