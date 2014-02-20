<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2013 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|   
+---------------------------------------------------------------------------
*/


if (!defined('IN_ANWSION'))
{
	die;
}

class posts_class extends AWS_MODEL
{
	public function set_posts_index($post_id, $post_type, $data)
	{
		if (!is_array($data))
		{
			return false;
		}
		
		if ($posts_index_id = $this->fetch_one('posts_index', 'id', "post_id = " . intval($post_id) . " AND post_type = '" . $this->quote($post_type) . "'"))
		{
			$this->update('posts_index', $data, "post_id = " . intval($post_id) . " AND post_type = '" . $this->quote($post_type) . "'");
		}
		else
		{
			$data = array_merge($data, array(
				'post_id' => intval($post_id),
				'post_type' => $post_type
			));
			
			$this->insert('posts_index', $data);
		}
	}
	
	public function remove_posts_index($post_id, $post_type)
	{
		return $this->delete('posts_index', "post_id = " . intval($post_id) . " AND post_type = '" . $this->quote($post_type) . "'");
	}
	
	public function get_posts_list($post_type, $page = 1, $per_page = 10, $sort = null, $topic_ids = null, $category_id = null, $answer_count = null, $day = 30, $is_recommend = false)
	{
		if ($sort == 'unresponsive')
		{
			$answer_count = 0;
		}
		
		switch ($sort)
		{
			default :
				$order_key = 'add_time DESC';
				break;
			
			case 'new' :
				$order_key = 'update_time DESC';
				break;
		}
		
		if (is_array($topic_ids))
		{
			foreach ($topic_ids AS $key => $val)
			{
				if (!$val)
				{
					unset($topic_ids[$key]);
				}
			}
		}
		
		if ($topic_ids)
		{
			$posts_index = $this->get_posts_list_by_topic_ids($post_type, $topic_ids, $category_id, $answer_count, $order_key, $is_recommend, $page, $per_page);
		}
		else
		{
			$where = array();
		
			if (isset($answer_count))
			{
				$where[] = 'answer_count = ' . intval($answer_count);
			}
				
			if ($is_recommend)
			{
				$where[] = 'is_recommend = 1';
			}
		
			if ($category_id)
			{
				$where[] = 'category_id IN(' . implode(',', $this->model('system')->get_category_with_child_ids('question', $category_id)) . ')';
			}
			
			if ($post_type)
			{
				$where[] = "post_type = '" . $this->quote($post_type) . "'";
			}
		
			$posts_index = $this->fetch_page('posts_index', implode(' AND ', $where), $order_key, $page, $per_page);
			
			$this->posts_list_total = $this->found_rows();
		}
		
		return $this->process_explore_list_data($posts_index);
	}
	
	public function get_hot_posts($post_type, $category_id = 0, $topic_ids = null, $day = 30, $page = 1, $per_page = 10)
	{
		if ($day)
		{
			$add_time = strtotime('-' . $day . ' Day');
		}
		
		$where[] = "add_time > " . intval($add_time) . " AND agree_count > 0 AND answer_count > 0";
		
		if ($post_type)
		{
			$where[] = "post_type = '" . $this->quote($post_type) . "'";
		}
		
		if ($category_id)
		{
			$where[] = 'category_id IN(' . implode(',', $this->model('system')->get_category_with_child_ids('question', $category_id)) . ')';
		}
		
		if (is_array($topic_ids))
		{
			foreach ($topic_ids AS $key => $val)
			{
				if (!$val)
				{
					unset($topic_ids[$key]);
				}
			}
		}
		
		if ($topic_ids)
		{
			array_walk_recursive($topic_ids, 'intval_string');
			
			if ($post_ids = $this->model('topic')->get_item_ids_by_topics_ids($topic_ids, $post_type))
			{				
				$where[] = 'post_id IN(' . implode(',', $post_ids) . ')';
			}
			else
			{
				return false;
			}
		}
		
		$posts_index = $this->fetch_page('posts_index', implode(' AND ', $where), 'popular_value DESC', $page, $per_page);
		
		$this->posts_list_total = $this->found_rows();
		
		return $this->process_explore_list_data($posts_index);
	}
	
	public function get_posts_list_total()
	{
		return $this->posts_list_total;
	}
	
	public function process_explore_list_data($posts_index)
	{
		if (!$posts_index)
		{
			return false;
		}
		
		foreach ($posts_index as $key => $data)
		{
			switch ($data['post_type'])
			{
				case 'question':
					$question_ids[] = $data['post_id'];
				break;
				
				case 'article':
					$article_ids[] = $data['post_id'];
				break;
			}
			
			$data_list_uids[] = $data['uid'];
		}
		
		if ($question_ids)
		{
			if ($last_answers = $this->model('answer')->get_last_answer_by_question_ids($question_ids))
			{
				foreach ($last_answers as $key => $val)
				{
					$data_list_uids[$val['uid']] = $val['uid'];
				}
			}
			
			$topic_infos['question'] = $this->model('topic')->get_topics_by_item_ids($question_ids, 'question');
			
			$question_infos = $this->model('question')->get_question_info_by_ids($question_ids);
		}
		
		if ($article_ids)
		{
			$topic_infos['article'] = $this->model('topic')->get_topics_by_item_ids($article_ids, 'article');
			
			$article_infos = $this->model('article')->get_article_info_by_ids($article_ids);
		}
		
		$users_info = $this->model('account')->get_user_info_by_uids($data_list_uids);

		foreach ($posts_index as $key => $data)
		{
			switch ($data['post_type'])
			{
				case 'question':
					$explore_list_data[$key] = $question_infos[$data['post_id']];
					
					$explore_list_data[$key]['answer'] = array(
						'user_info' => $users_info[$last_answers[$data['post_id']]['uid']],
						'answer_content' => $last_answers[$data['post_id']]['answer_content'],
						'anonymous' => $last_answers[$data['post_id']]['anonymous']
					);
				break;
				
				case 'article':
					$explore_list_data[$key] = $article_infos[$data['post_id']];
				break;
			}
			
			
			if (get_setting('category_enable') == 'Y')
			{
				$explore_list_data[$key]['category_info'] = $this->model('system')->get_category_info($data['category_id']);
			}
			
			$explore_list_data[$key]['topics'] = $topic_infos[$data['post_type']][$data['post_id']];
					
			$explore_list_data[$key]['user_info'] = $users_info[$data['uid']];
		}
		
		return $explore_list_data;
	}
	
	public function get_posts_list_by_topic_ids($post_type, $topic_ids, $category_id = null, $answer_count = null, $order_by = 'post_id DESC', $is_recommend = false, $page = 1, $per_page = 10)
	{
		if (!is_array($topic_ids))
		{
			return false;
		}

		array_walk_recursive($topic_ids, 'intval_string');
		
		$result_cache_key = 'posts_list_by_topic_ids_' . implode('_', $topic_ids) . '_' . md5($answer_count . $category_id . $order_by . $is_recommend . $page . $per_page . $post_type);
		
		$found_rows_cache_key = 'posts_list_by_topic_ids_found_rows_' . implode('_', $topic_ids) . '_' . md5($answer_count . $category_id . $is_recommend . $per_page . $post_type);
			
		$where[] = 'topic_relation.topic_id IN(' . implode(',', $topic_ids) . ')';
			
		if ($answer_count !== null)
		{
			$where[] = "posts_index.answer_count = " . intval($answer_count);
		}
		
		if ($is_recommend)
		{
			$where[] = 'posts_index.is_recommend = 1';
		}
				
		if ($category_id)
		{
			$where[] = 'posts_index.category_id IN(' . implode(',', $this->model('system')->get_category_with_child_ids('question', $category_id)) . ')';
		}
		
		$on_query[] = 'posts_index.post_id = topic_relation.item_id';
		
		if ($post_type)
		{
			$on_query[] = "posts_index.post_type = '" . $this->quote($post_type) . "'";
			$on_query[] = "topic_relation.type = '" . $this->quote($post_type) . "'";
		}
		else
		{
			$on_query[] = 'posts_index.post_type = topic_relation.type';
		}
		
		if (!$found_rows = AWS_APP::cache()->get($found_rows_cache_key))
		{
			$_found_rows = $this->query_row('SELECT COUNT(DISTINCT posts_index.post_id) AS count FROM ' . $this->get_table('posts_index') . ' AS posts_index LEFT JOIN ' . $this->get_table('topic_relation') . " AS topic_relation ON " . implode(' AND ', $on_query) . " WHERE " . implode(' AND ', $where));
			
			$found_rows = $_found_rows['count'];
			
			AWS_APP::cache()->set($found_rows_cache_key, $found_rows, get_setting('cache_level_high'));
		}
		
		$this->posts_list_total = $found_rows;
		
		if (!$result = AWS_APP::cache()->get($result_cache_key))
		{
			$result = $this->query_all('SELECT posts_index.* FROM ' . $this->get_table('posts_index') . ' AS posts_index LEFT JOIN ' . $this->get_table('topic_relation') . " AS topic_relation ON " . implode(' AND ', $on_query) . " WHERE " . implode(' AND ', $where) . ' GROUP BY posts_index.post_id ORDER BY posts_index.' . $order_by, calc_page_limit($page, $per_page));
			
			AWS_APP::cache()->set($result_cache_key, $result, get_setting('cache_level_high'));
		}
		
		return $result;
	}
}