<?php
class Article extends CI_Controller
{
    const SUCCESS = 0;
    const ACCESS_FORBIDDEN = 1;
    const CONTENT_EMPTY = 2;

	public function __construct() {
		parent::__construct();
		$this->load->model('Article_model');
	}

    public function get_category_list() {
    	$query = $this->Article_model->get_categories();
        $categories = array();
        
        foreach ($query as $cat) 
            array_push($categories, array("ctid" => $cat['id'], "tag" => $cat['name']));
        
        $ret = array("errorcode" => self::SUCCESS,
                    "msg" => "获取成功",
                    "classtags" => $categories);
           
    	echo json_encode($ret);
    	
    	return true;
    }

    public function get_category_article($category_id, $off) {
    	$query = $this->Article_model->get_category_article($category_id, $off);
        $articles = array();
        
        if (empty($query)) 
            $ret = array(
                "errorcode" => self::CONTENT_EMPTY,
                "msg" => "内容为空",
            );
        else 
            $ret = array(
                "errorcode" => self::SUCCESS,
                "msg" => "获取成功",
                "essaylist" => $query
            );
            
        $expire_time = gmdate("D, d M Y 00:00:00", strtotime('+1 day'))." GMT";
        $this->output
            ->set_content_type('application/json')
            ->set_header("Expires: {$expire_time}")
            ->set_output(json_encode($ret));
        
    	return true;
    }

    public function get_stat($article_id) {
        $this->load->model('Action_model');
        $this->load->library('Check');
        $ret = array();

        if ($uid = $this->check->get_uid(0)) {
            $ret['like'] = $this->Action_model->get_star($uid, $article_id);
            $ret['favorite'] = $this->Action_model->get_favorite($uid, $article_id);
        }

        $ret['comment_cnt'] = $this->Action_model->get_comment_cnt($article_id);
        $ret['errorcode'] = self::SUCCESS;
        $ret['msg'] = '成功获取';
        
        echo json_encode($ret);

    	return true;
    }
}
?>

