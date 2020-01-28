<?php

class Naire_model extends CI_Model
{

	public function __construct()
	{
		parent::__construct();
		// Your own constructor code
		$this->load->database();
	}

	// 获取问卷详细信息
	public function get_naires()
	{
		// 获取参数 naire id
		// JSON 反序列化
		$n_id = json_decode($this->input->raw_input_stream, true)['n_id'];

		if (empty($n_id)) {
			return array("err" => 1, "data" => "请传入参数值");
		}
		$naire = $this->db->query("select * from naire where naire.n_id = {$n_id}")
			->result_array();
		$questions = $this->db->query("select * from question where question.n_id = {$n_id}")
			->result_array();
		$options = $this->db->query("select * from options where options.n_id = {$n_id}")
			->result_array();

//		echo var_dump($naire);
//		echo var_dump($questions);
//		echo var_dump($options);
		if (empty($naire) || empty($questions)) {
			return array("err" => 1, "data" => "未获取到相应问卷");
		}
		$result = array(
			"n_id" => $naire[0]["n_id"],
			"title" => $naire[0]["n_title"],
			"creattime" => $naire[0]["n_creattime"],
			"deadline" => $naire[0]["n_deadline"],
			"status" => $naire[0]["n_status"],
			"options" => $naire[0]["n_options"],
			"intro" => $naire[0]["n_intro"]
		);
		foreach ($questions as $questionkey => $questionval) {
//		  echo var_dump($val);
			$temp = [];
			foreach ($options as $optionitem => $optionval) {
				if ($questionval['q_id'] == $optionval['q_id']) {
					$temp[] = array(
						"o_id" => $optionval['o_id'],
						"content" => $optionval['o_value'],
						"image" => $optionval['o_image'],
						"desc" => $optionval['o_desc'],
						"isAddition" => $optionval['o_isaddtion'] == "1" ? true : false
					);
				}
			}
			if ($questionval["q_type"] == '单选') {
				$result['topic'][] = array(
					"q_id" => $questionval["q_id"],
					"question" => $questionval["q_content"],
					"isRequired" => $questionval["q_isrequire"] == "1" ? true : false,
					"type" => $questionval["q_type"],
					"description" => $questionval["q_description"],
					"selectContent" => "",
					"additional" => "",
					"options" => $temp
				);
			} else if ($questionval["q_type"] == '多选') {
				$result['topic'][] = array(
					"q_id" => $questionval["q_id"],
					"question" => $questionval["q_content"],
					"isRequired" => $questionval["q_isrequire"] == "1" ? true : false,
					"type" => $questionval["q_type"],
					"description" => $questionval["q_description"],
					"selectMultipleContent" => array(),
					"additional" => "",
					"setting" => $questionval["q_setting"] ? json_decode($questionval["q_setting"]) : array("last" => 1), // 多选题才有配置项
					"options" => $temp
				);
			} else if ($questionval["q_type"] == '文本') {
				$result['topic'][] = array(
					"q_id" => $questionval["q_id"],
					"question" => $questionval["q_content"],
					"isRequired" => $questionval["q_isrequire"] == "1" ? true : false,
					"type" => $questionval["q_type"],
					"description" => $questionval["q_description"],
					"selectContent" => ""
				);
			}
		}

		return array("err" => 0, "data" => $result);

	}

	// 获取问卷列表
	public function get_naire_list()
	{
		$query = $this->db->order_by("n_deadline", "desc")->get('naire');
		if (!$query) {
			$err = 1;
		} else {
			$err = 0;
		}
		return array("err" => $err, "data" => $query->result_array());
	}

	// 保存问卷
	public function save_naire()
	{
		// JSON 反序列化
		$naire = json_decode($this->input->raw_input_stream, true)['naire'];
		$status = json_decode($this->input->raw_input_stream, true)['status'];
		// create / update
		if ($status == 'create') {
			// 执行插入操作
			if ($naire['deadline'] === '' || $naire['title'] === '' || $naire['status'] === '') {
				return array("err" => 1, "data" => "问卷(naire)必填字段不能为空");
			}
			$insert_naire_data = array(
				'n_deadline' => $naire['deadline'],
				'n_title' => trim($naire['title']),
				'n_status' => $naire['status'],
				'n_intro' => trim($naire['intro']),
				'n_options' => array_key_exists('options', $naire) ? trim($naire['options']) : '',
				'n_creattime' => utils_helper::getMillisecond()
			);
			$this->db->insert('naire', $insert_naire_data);
			$naire_id = $this->db->insert_id();
			// 遍历题目
			foreach ($naire['topic'] as $topickey => $topicval) {

				// 题目内容
				if ($topicval['question'] === '' || $topicval['type'] === '' || $topicval['isRequired'] === '') {
					return array("err" => 1, "data" => '问题(question)必填字段不能为空');
				}
				// print_r($topicval['question']);
				$insert_question_data = array(
					'q_content' => trim($topicval['question']),
					'q_type' => $topicval['type'],
					'n_id' => $naire_id,
					'q_setting' => array_key_exists('setting', $topicval) ? json_encode($topicval['setting']) : '',
					'q_isrequire' => $topicval['isRequired'] == "true" ? 1 : 0,
					'q_description' => trim($topicval['description'])
				);

				$this->db->insert('question', $insert_question_data);
				$question_id = $this->db->insert_id();
				if (!empty($topicval['options']) && $topicval['type'] != '文本') {
					// 遍历选项
					foreach ($topicval['options'] as $optionkey => $optionval) {
						// 选项内容 $optionval['content']
						// 选项是否需要填写附加内容 $optionval['isAddition']
						if ($optionval['content'] === '' || $optionval['isAddition'] === '') {
							return array("err" => 1, "data" => '选项(option)必填字段不能为空');
						}
						$insert_option_data = array(
							'o_value' => trim($optionval['content']),
							'n_id' => $naire_id,
							'q_id' => $question_id,
							'o_image' => trim($optionval['image']),
							'o_desc' => trim($optionval['desc']),
							'o_isaddtion' => $optionval['isAddition'] == "true" ? 1 : 0
						);
						$this->db->insert('options', $insert_option_data);
						//print_r($optionval['isAddition'] == 1 ? 'true' : 'false');
					}
				}

			}
			return array("err" => 0, "data" => '新建问卷成功');

		} else {
			// 执行更新操作
			$this->db->trans_start();
			// 执行插入操作
			if ($naire['deadline'] === '' || $naire['title'] === '' || $naire['status'] === '') {
				return array("err" => 1, "data" => "问卷(naire)必填字段不能为空");
			}
			$update_naire_data = array(
				'n_deadline' => $naire['deadline'],
				'n_title' => trim($naire['title']),
				'n_status' => $naire['status'],
				'n_intro' => trim($naire['intro']),
				'n_options' => array_key_exists('options', $naire) ? trim($naire['options']) : '',
				'n_creattime' => utils_helper::getMillisecond()
			);

			$naire_id = $naire['n_id'];
			$this->db->where('n_id', $naire_id);
			$this->db->update('naire', $update_naire_data);

			// 删除原有数据
			$del_tables = array('question', 'options', 'submit_log', 'result');
			$this->db->where('n_id', $naire_id);
			$this->db->delete($del_tables);

			// 遍历题目
			foreach ($naire['topic'] as $topickey => $topicval) {

				// 题目内容
				if ($topicval['question'] === '' || $topicval['type'] === '' || $topicval['isRequired'] === '') {
					return array("err" => 1, "data" => '问题(question)必填字段不能为空');
				}
				// print_r($topicval['question']);
				$insert_question_data = array(
					'q_content' => trim($topicval['question']),
					'q_type' => $topicval['type'],
					'n_id' => $naire_id,
					'q_setting' => array_key_exists('setting', $topicval) ? json_encode($topicval['setting']) : '',
					'q_isrequire' => $topicval['isRequired'] == "true" ? 1 : 0,
					'q_description' => trim($topicval['description'])
				);

				$this->db->insert('question', $insert_question_data);
				$question_id = $this->db->insert_id();
				if (!empty($topicval['options']) && $topicval['type'] != '文本') {
					// 遍历选项
					foreach ($topicval['options'] as $optionkey => $optionval) {
						// 选项内容 $optionval['content']
						// 选项是否需要填写附加内容 $optionval['isAddition']
						if ($optionval['content'] === '' || $optionval['isAddition'] === '') {
							return array("err" => 1, "data" => '选项(option)必填字段不能为空');
						}
						$insert_option_data = array(
							'o_value' => trim($optionval['content']),
							'n_id' => $naire_id,
							'q_id' => $question_id,
							'o_image' => trim($optionval['image']),
							'o_desc' => trim($optionval['desc']),
							'o_isaddtion' => $optionval['isAddition'] == "true" ? 1 : 0
						);
						$this->db->insert('options', $insert_option_data);
						//print_r($optionval['isAddition'] == 1 ? 'true' : 'false');
					}
				}

			}

			$this->db->trans_complete();

			if ($this->db->trans_status() === FALSE) {
				return array("err" => 1, "data" => '写入数据发生错误');
			} else {
				return array("err" => 0, "data" => '更新问卷成功');
			}

		}
	}

	// 提交问卷
	public function submit_naire($result, $n_id, $user_id)
	{
		// 对问卷可提交时间做判断，以服务器的时间为准
		$this->db->trans_start();

		$naire_detail = $this->db->where('n_id', $n_id)->get('naire')->row();
		$cur_time = (int)(microtime(true) * 1000);
		if ($naire_detail->n_deadline <= $cur_time) return array("err" => 1, "data" => '问卷提交失败，非可提交时间。');

		$values = [];

		foreach ($result as $key => $val) {
			//	[n_id] => 12
			//  [q_id] => 41
			//  [o_id] => 52
			//  [o_addition] =>
			// 用户 u_id 获取
			// 如果是多选题，需要再次 foreach
			if (is_array($val['o_id'])) {
				foreach ($val['o_id'] as $o_key => $o_val) {
					$values[] = array(
						'n_id' => $val['n_id'],
						'u_id' => $val['u_id'],
						'q_id' => $val['q_id'],
						'o_id' => $o_val,
						'o_addtion' => trim($val['o_addition']),
					);
				}
			} else {
				$values[] = array(
					'n_id' => $val['n_id'],
					'u_id' => $val['u_id'],
					'q_id' => $val['q_id'],
					'o_id' => is_null($val['o_id']) ? '' : $val['o_id'],
					'o_addtion' => $val['o_addition'],
				);
			}
		}

		$this->db->insert_batch('result', $values);

		// 记录问卷填写日志表
		$option_data = array(
			'n_id' => $n_id,
			'u_id' => $user_id,
			's_creattime' => $cur_time
		);
		$this->db->insert('submit_log', $option_data);
		$this->db->trans_complete();

		if ($this->db->trans_status() === FALSE) {
			return array("err" => 1, "data" => '写入数据发生错误');
		} else {
			return array("err" => 0, "data" => '问卷提交成功');
		}
	}

	// 删除问卷
	public function del_naire()
	{
		$n_id = $this->input->post_get('n_id', TRUE);
		// 删除多表中的数据
		$del_tables = array('naire', 'question', 'options', 'result', 'submit_log');
		$this->db->where_in('n_id', explode(',', $n_id));
		$this->db->delete($del_tables);

		// 同时清空任务表
		$this->db->query("DELETE FROM task WHERE naire_id IN ({$n_id})");
		$rows = $this->db->affected_rows();

		$result = $this->db->affected_rows() + $rows;

//		$this->db->query("DELETE FROM naire, question, options, result  WHERE n_id={$n_id}");
//		$rows = $this->db->affected_rows();

		return array('err' => 0, "data" => $result);
	}

	// 问卷统计
	public function statis_naire()
	{
		// 获取参数 naire id
		// JSON 反序列化
		$n_id = json_decode($this->input->raw_input_stream, true)['n_id'];

		if ($n_id == '') {
			return array("err" => 1, "data" => "请传入参数值");
		}
		$naire = $this->db->query("select * from naire where naire.n_id = {$n_id}")
			->result_array();
		$questions = $this->db->query("select * from question where question.n_id = {$n_id}")
			->result_array();
		$options = $this->db->query("select * from options where options.n_id = {$n_id}")
			->result_array();

//		echo var_dump($naire);
//		echo var_dump($questions);
//		echo var_dump($options);
		if (empty($naire) || empty($questions)) {
			return array("err" => 1, "data" => "未获取到相应问卷");
		}
		// 先遍历 问卷表，拿到问卷id
		$result["naire"] = array(
			"n_id" => $naire[0]["n_id"],
			"title" => $naire[0]["n_title"],
			"creattime" => $naire[0]["n_creattime"],
			"deadline" => $naire[0]["n_deadline"],
			"status" => $naire[0]["n_status"],
			"intro" => $naire[0]["n_intro"]
		);
		$joinCountQuery = $this->db->query("SELECT * FROM result WHERE result.n_id = {$n_id} GROUP BY result.u_id");
		$joinCount = $joinCountQuery->num_rows(); // 投票总人数

		// 再遍历题目表，拿到题目id，去遍历选项表
		foreach ($questions as $questionkey => $questionval) {
//		  echo var_dump($val);
			$temp = []; // 待添加的选项
			$charts = []; // 每个选项的个数
			$addtionContent = []; // 附加理由
			// 用于图表显示
			// 查询该题目总调查人数
			// select *,count(*) as total from result where n_id = {$naire[0]["n_id"]} and q_id = {$questionval["q_id"]} group by q_id
			$total = 0;
			$totalResult = $this->db->query("select *,count(*) as total from result where n_id = {$naire[0]['n_id']} and q_id = {$questionval['q_id']} group by q_id");

			if ($totalResult->num_rows() > 0) {
				$total = $totalResult->result_array()[0]["total"];
			} else {
//                return array("err" => 1, "data" => "暂无用户提交问卷");
			}

			foreach ($options as $optionitem => $optionval) {
				// 如果题目id 等于 选项表当中的题目id，则将该选项添加到临时数组中
				if ($questionval['q_id'] == $optionval['q_id']) {

					// 查询每个选项在数据库中的个数
					// select *,count(*) as total from result where n_id = {$naire[0]['n_id']} and q_id = {$questionval['q_id']} and o_id = {$optionval['o_id']}
					$count = $this->db->query("select *,count(*) as total from result where n_id = {$naire[0]['n_id']} and q_id = {$questionval['q_id']} and o_id = {$optionval['o_id']}")->result_array()[0]["total"];
					$charts[] = $count;
					$percent = $count > 0 ? round(($count / $joinCount * 100), 2) : 0;
					// 查询附加理由的内容
					// select * from result, options where result.n_id = {$naire[0]['n_id']} and result.o_id and options.o_id and result.q_id = {$questionval['q_id']}  and options.o_isaddtion = {$optionval['o_id']}

					$addtionData = $this->db->query("select * from result, options where result.n_id = {$naire[0]['n_id']} and result.o_id = options.o_id and result.q_id = {$questionval['q_id']}  and options.o_isaddtion = 1 and result.o_id = {$optionval['o_id']}")->result_array();
					foreach ($addtionData as $addtionitem => $addtionval) {
						if ($addtionval["o_addtion"] != "") {
//							print_r($addtionval["o_addtion"]);
							$addtionContent[] = array(
								"content" => $addtionval["o_addtion"]);
						}
					}


					$temp[] = array(
						"o_id" => $optionval['o_id'],
						"content" => $optionval['o_value'],
						"isAddition" => $optionval['o_isaddtion'] == "1" ? true : false,
						"count" => $count,
						"percent" => $percent
					);
					$optionList[] = $optionval['o_value'];
				}
			}
			// 单选题
			if ($questionval["q_type"] == '单选') {
				$result['questions'][] = array(
					"q_id" => $questionval["q_id"],
					"question" => $questionval["q_content"],
					"isRequired" => $questionval["q_isrequire"] == "1" ? true : false,
					"type" => $questionval["q_type"],
					"description" => $questionval["q_description"],
					"selectContent" => "",
					"additional" => "",
					"options" => $temp,
					"charts" => $charts,
					"addtionContent" => $addtionContent
				);
				// 多选题
			} else if ($questionval["q_type"] == '多选') {
				$result['questions'][] = array(
					"q_id" => $questionval["q_id"],
					"question" => $questionval["q_content"],
					"isRequired" => $questionval["q_isrequire"] == "1" ? true : false,
					"type" => $questionval["q_type"],
					"description" => $questionval["q_description"],
					"selectMultipleContent" => array(),
					"additional" => "",
					"options" => $temp,
					"charts" => $charts
				);
				// 文本题
			} else if ($questionval["q_type"] == '文本') {
				// 拿问答题提交内容
				$answerList = [];
				$answerData = $this->db->query("select * from result,users where n_id = {$naire[0]["n_id"]} and q_id = {$questionval["q_id"]} and result.u_id = users.u_id")->result_array();
				foreach ($answerData as $item => $val) {
//					print_r($val["o_addtion"]);
					$answerList[] = array(
						"content" => $val["o_addtion"],
						"userNumber" => $val["u_number"],
						"userName" => $val["u_name"]
					);
				}
				$result['questions'][] = array(
					"q_id" => $questionval["q_id"],
					"question" => $questionval["q_content"],
					"isRequired" => $questionval["q_isrequire"] == "1" ? true : false,
					"type" => $questionval["q_type"],
					"description" => $questionval["q_description"],
					"selectContent" => "",
					"answerList" => $answerList
				);

			}
		}

		return array("err" => 0, "data" => $result);

	}

	// 交叉分析获取题目
	public function get_questions()
	{
		// 获取参数 naire id
		// JSON 反序列化
		$n_id = json_decode($this->input->raw_input_stream, true)['n_id'];

		if ($n_id == '') {
			return array("err" => 1, "data" => "请传入参数值");
		}
		// 问卷信息
		$naire = $this->db->query("select * from naire where naire.n_id = {$n_id}")
			->result_array();
		$questions = $this->db->query("select q_id as value, q_content as label from question where question.n_id = {$n_id} and (question.q_type = '单选' or question.q_type = '多选')")
			->result_array();

		if (empty($naire)) {
			return array("err" => 1, "data" => "未获取到相应问卷");
		}

		// 问卷信息 先遍历 问卷表，拿到问卷id
		$result["naire"] = array(
			"n_id" => $naire[0]["n_id"],
			"title" => $naire[0]["n_title"],
			"creattime" => $naire[0]["n_creattime"],
			"deadline" => $naire[0]["n_deadline"],
			"status" => $naire[0]["n_status"],
			"intro" => $naire[0]["n_intro"]
		);

		$result["questions"] = $questions;

		return array("err" => 0, "data" => $result);


	}

	// 交叉分析
	public function cross_analysis()
	{
		// todo
		// 需要接收两个参数，一个题目的id，一个是题目的id
		// 交叉分析
		// 第一列选项内容， 表头也是选项内容
		// 用户可以添加多个条件，然后进行分别匹配，渲染出结果


		// 获取参数 naire id
		// JSON 反序列化
//        var_dump($this->input->raw_input_stream);
//        $ss = $GLOBALS['HTTP_RAW_POST_DATA'];
//        $ss = json_decode($ss,true);
//        var_dump($ss);exit;
		$n_id = json_decode($this->input->raw_input_stream, true)['n_id'];
		$x_id = json_decode($this->input->raw_input_stream, true)['x_id'];
		$y_id = json_decode($this->input->raw_input_stream, true)['y_id'];


		if (empty($n_id) || empty($x_id) || empty($y_id)) {
			return array("err" => 1, "data" => "请传入参数值");
		}

		// 表头
		$table_column = $this->db->query("select * from options where options.q_id = {$y_id}")
			->result_array();
		$table_row = $this->db->query("select * from options where options.q_id = {$x_id}")
			->result_array();

		$cross_result = $this->db->query("select t1.o_value as x_value, t1.o_id as x_id,t2.o_id as y_id, count(*) as count from (select result.u_id, result.q_id, result.o_id, options.o_value from result, options where result.n_id = {$n_id} and options.o_id = result.o_id) as t1, (select result.u_id, result.q_id, result.o_id, options.o_value from result, options where result.n_id = {$n_id} and options.o_id = result.o_id) as t2 where t1.u_id = t2.u_id and t1.q_id = {$x_id} and t2.q_id = {$y_id} group by t1.o_id, t2.o_id")->result_array();


		if (empty($cross_result)) {
			return array("err" => 1, "data" => "暂无用户提交问卷");
		}

		// 返回结构
		$result["cross_result"] = $cross_result;
		$result["column"] = $table_column;
		$result["row"] = $table_row;

		return array("err" => 0, "data" => $result);


	}

	// 样本数据
	public function source_data()
	{
		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', '1000');
		// 获取参数 naire id
		// JSON 反序列化
		$n_id = $this->input->post_get('n_id', TRUE);
		$current = $this->input->post_get('current', TRUE);
		$page_size = 10;
		if (empty($n_id)) {
			return array("err" => 1, "data" => "请传入参数值");
		}
		$offset = ($current - 1) * $page_size;
		// 问卷信息
		$naire = $this->db->query("select * from naire where naire.n_id = {$n_id}")
			->result_array();
		// 问题结果
		$questions = $this->db->query("SELECT question.q_id, question.q_content, question.q_type FROM result, question WHERE result.q_id = question.q_id and result.n_id = {$n_id} GROUP BY question.q_id")
			->result_array();
		$allTextQuestion = true;
		foreach ($questions as $question_key => $question_val) {
			if ($question_val['q_type'] != '文本') {
				$allTextQuestion = false;
				break;
			}
		}
		// 参与问卷的用户
		$total = $this->db->query("SELECT users.u_id, users.u_name, users.u_class, users.u_number, submit_log.s_creattime FROM users, result, submit_log WHERE users.u_id = result.u_id and result.n_id = {$n_id} and submit_log.n_id = {$n_id} and submit_log.u_id = users.u_id GROUP BY result.u_id")->num_rows();

		$users_id_sql = "SELECT t.u_id FROM (SELECT users.u_id FROM users, result, submit_log WHERE users.u_id = result.u_id and result.n_id = {$n_id} and submit_log.n_id = {$n_id} and submit_log.u_id = users.u_id GROUP BY result.u_id ORDER BY submit_log.s_creattime ASC LIMIT {$offset} , {$page_size}) AS t";

		$users = $this->db->query("SELECT users.u_id, users.u_name, users.u_class, users.u_number, submit_log.s_creattime FROM users, result, submit_log WHERE users.u_id = result.u_id and result.n_id = {$n_id} and submit_log.n_id = {$n_id} and submit_log.u_id = users.u_id GROUP BY result.u_id ORDER BY submit_log.s_creattime ASC " . " LIMIT " . $offset . " , " . $page_size . " ")->result_array();

		$userResultSql = "";
		// 全部是文本题目
		if ($allTextQuestion) {
			// 用户答案结果 result.o_id = options.o_id and
			$userResultSql = "SELECT users.u_id, question.q_id, question.q_content, question.q_type, options.o_value, result.o_id, result.o_addtion, options.o_isaddtion from result, question, users, options WHERE result.o_id = 0 and question.q_id = result.q_id and users.u_id = result.u_id and result.n_id = {$n_id} AND result.u_id IN ({$users_id_sql})  GROUP BY result.u_id, result.q_id";
		} else {
			$userResultSql = "(SELECT users.u_id, question.q_id, question.q_content, question.q_type, options.o_value, result.o_id, result.o_addtion, options.o_isaddtion from result, question, users, options WHERE result.o_id = options.o_id and question.q_id = result.q_id and users.u_id = result.u_id and result.n_id = {$n_id} ) UNION ALL (SELECT users.u_id, question.q_id, question.q_content, question.q_type, options.o_value, result.o_id, result.o_addtion, options.o_isaddtion from result, question, users, options WHERE result.o_id = 0 and question.q_id = result.q_id and users.u_id = result.u_id and result.n_id = {$n_id} AND result.u_id IN ({$users_id_sql})  GROUP BY result.u_id)";
		}
		// 用户答案结果 result.o_id = options.o_id and
		$user_result = $this->db->query($userResultSql)
			->result_array();
//		echo var_dump($naire);
		// echo var_dump($questions);
//		echo var_dump($users);
		// echo var_dump($user_result);

		if (empty($user_result)) {
			return array("err" => 1, "data" => "暂无用户提交问卷");
		}
		if (empty($naire) || empty($questions)) {
			return array("err" => 1, "data" => "未获取到相应问卷");
		}

		// 问卷信息 先遍历 问卷表，拿到问卷id
		$result["naire"] = array(
			"n_id" => $naire[0]["n_id"],
			"title" => $naire[0]["n_title"],
			"creattime" => $naire[0]["n_creattime"],
			"deadline" => $naire[0]["n_deadline"],
			"status" => $naire[0]["n_status"],
			"intro" => $naire[0]["n_intro"]
		);
		// 再遍历题目表，拿到题目id，去遍历选项表
		// 题目信息，作为表头
		$result["question"] = $questions;
		// todo 依照 result 表中的用户进行遍历，以用户为一个单位

		$result["user_result"] = [];

		foreach ($users as $users_key => $users_val) {
			$result_temp = array(
				"u_id" => $users_val["u_id"],
				"u_name" => $users_val["u_name"],
				"u_class" => $users_val["u_class"],
				"u_number" => $users_val["u_number"],
				"s_creattime" => $users_val["s_creattime"]
			);
			$q_id = 0; // 用于多选题的特殊情况
			$curAnswer = ""; // 用于多选题的特殊情况
			// 遍历操作 题目-》用户答案
			foreach ($user_result as $user_result_key => $user_result_val) {
				// 同一个用户
				if ($user_result_val["u_id"] == $users_val["u_id"]) {

					if ($user_result_val["q_id"] == $q_id && $user_result_val["q_type"] == "多选") { // 多选题
						$curAnswer .= "、" . $user_result_val["o_value"];
					} else if ($user_result_val["q_type"] == "文本") { // 文本题目
						$curAnswer = $user_result_val["o_addtion"];
					} else if ($user_result_val["q_type"] == "单选") { // 单选题
						if ($user_result_val["o_addtion"] == "") {
							$curAnswer = $user_result_val["o_value"];
						} else {
							$curAnswer = $user_result_val["o_value"] . "，附加理由：" . $user_result_val["o_addtion"];
						}

					} else {
						$curAnswer = $user_result_val["o_value"];
					}
					$result_temp["q_" . $user_result_val["q_id"]] = $curAnswer;

					$q_id = $user_result_val["q_id"];


				}
			}

			// 合并两个数组
			$result["user_result"][] = $result_temp;

		}


		return array("err" => 0, "data"=> array(
			"result" => $result,
			"total" => $total
		), "msg" => '');
	}

	// 全部样本数据，用于Excel 导出
	public function all_source_data($n_id, $current = 1, $page_size = 500)
	{
		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', '1000');
		// 获取参数 分页
		$offset = ($current - 1) * $page_size;
		// JSON 反序列化
		// 问卷信息
		$naire = $this->db->query("select * from naire where naire.n_id = {$n_id}")
			->result_array();
		// 问题结果
		$questions = $this->db->query("SELECT question.q_id, question.q_content, question.q_type FROM result, question WHERE result.q_id = question.q_id and result.n_id = {$n_id} GROUP BY question.q_id")
			->result_array();
		// 参与问卷的用户
//        $users = $this->db->query("SELECT users.u_id, users.u_name, users.u_class, users.u_number, submit_log.s_creattime FROM users, result, submit_log WHERE users.u_id = result.u_id and result.n_id = {$n_id} and submit_log.n_id = {$n_id} and submit_log.u_id = users.u_id GROUP BY result.u_id")->result_array();
		$users_sql = "SELECT users.u_id, users.u_name, users.u_class, users.u_number, submit_log.s_creattime FROM users, result, submit_log WHERE users.u_id = result.u_id and result.n_id = {$n_id} and submit_log.n_id = {$n_id} and submit_log.u_id = users.u_id GROUP BY result.u_id ORDER BY submit_log.s_creattime ASC " . " LIMIT " . $offset . " , " . $page_size;
		$users_id_sql = "SELECT t.u_id FROM (SELECT users.u_id FROM users, result, submit_log WHERE users.u_id = result.u_id and result.n_id = {$n_id} and submit_log.n_id = {$n_id} and submit_log.u_id = users.u_id GROUP BY result.u_id ORDER BY submit_log.s_creattime ASC LIMIT {$offset} , {$page_size}) AS t";
		$users = $this->db->query($users_sql)->result_array();

		// 用户答案结果
		// $user_result = $this->db->query("(SELECT users.u_id, question.q_id, question.q_content, question.q_type, options.o_value, result.o_id, result.o_addtion, options.o_isaddtion from result, question, users, options WHERE result.o_id = options.o_id and question.q_id = result.q_id and users.u_id = result.u_id and result.n_id = {$n_id} ) UNION ALL (SELECT users.u_id, question.q_id, question.q_content, question.q_type, options.o_value, result.o_id, result.o_addtion, options.o_isaddtion from result, question, users, options WHERE result.o_id = 0 and question.q_id = result.q_id and users.u_id = result.u_id and result.n_id = {$n_id} AND result.u_id IN ({$users_id_sql}) GROUP BY result.u_id)")
		//     ->result_array();

//		echo var_dump($naire);
//		echo var_dump($questions);
//		echo var_dump($users);
//		echo var_dump($user_result);

		$allTextQuestion = true;
		foreach ($questions as $question_key => $question_val) {
			if ($question_val['q_type'] != '文本') {
				$allTextQuestion = false;
				break;
			}
		}

		$userResultSql = "";
		// 全部是文本题目
		if ($allTextQuestion) {
			// 用户答案结果 result.o_id = options.o_id and
			$userResultSql = "SELECT users.u_id, question.q_id, question.q_content, question.q_type, options.o_value, result.o_id, result.o_addtion, options.o_isaddtion from result, question, users, options WHERE result.o_id = 0 and question.q_id = result.q_id and users.u_id = result.u_id and result.n_id = {$n_id} AND result.u_id IN ({$users_id_sql})  GROUP BY result.u_id, result.q_id";
		} else {
			$userResultSql = "(SELECT users.u_id, question.q_id, question.q_content, question.q_type, options.o_value, result.o_id, result.o_addtion, options.o_isaddtion from result, question, users, options WHERE result.o_id = options.o_id and question.q_id = result.q_id and users.u_id = result.u_id and result.n_id = {$n_id} ) UNION ALL (SELECT users.u_id, question.q_id, question.q_content, question.q_type, options.o_value, result.o_id, result.o_addtion, options.o_isaddtion from result, question, users, options WHERE result.o_id = 0 and question.q_id = result.q_id and users.u_id = result.u_id and result.n_id = {$n_id} AND result.u_id IN ({$users_id_sql}) GROUP BY result.u_id)";
		}
		// 用户答案结果 result.o_id = options.o_id and
		$user_result = $this->db->query($userResultSql)
			->result_array();

		if (empty($user_result)) {
			return array("err" => 1, "data" => "暂无用户提交问卷");
		}
		if (empty($naire) || empty($questions)) {
			return array("err" => 1, "data" => "未获取到相应问卷");
		}

		// 问卷信息 先遍历 问卷表，拿到问卷id
		$result["naire"] = array(
			"n_id" => $naire[0]["n_id"],
			"title" => $naire[0]["n_title"],
			"creattime" => $naire[0]["n_creattime"],
			"deadline" => $naire[0]["n_deadline"],
			"status" => $naire[0]["n_status"],
			"intro" => $naire[0]["n_intro"]
		);
		// 再遍历题目表，拿到题目id，去遍历选项表
		// 题目信息，作为表头
		$result["question"] = $questions;
		// todo 依照 result 表中的用户进行遍历，以用户为一个单位

		$result["user_result"] = [];

		foreach ($users as $users_key => $users_val) {
			$result_temp = array(
				"u_id" => $users_val["u_id"],
				"u_name" => $users_val["u_name"],
				"u_class" => $users_val["u_class"],
				"u_number" => $users_val["u_number"],
				"s_creattime" => $users_val["s_creattime"]
			);
			$q_id = 0; // 用于多选题的特殊情况
			$curAnswer = ""; // 用于多选题的特殊情况
			// 遍历操作 题目-》用户答案
			foreach ($user_result as $user_result_key => $user_result_val) {
				// 同一个用户
				if ($user_result_val["u_id"] == $users_val["u_id"]) {

					if ($user_result_val["q_id"] == $q_id && $user_result_val["q_type"] == "多选") { // 多选题
						$curAnswer .= "、" . $user_result_val["o_value"];
					} else if ($user_result_val["q_type"] == "文本") { // 文本题目
						$curAnswer = $user_result_val["o_addtion"];
					} else if ($user_result_val["q_type"] == "单选") { // 单选题
						if ($user_result_val["o_addtion"] == "") {
							$curAnswer = $user_result_val["o_value"];
						} else {
							$curAnswer = $user_result_val["o_value"] . "，附加理由：" . $user_result_val["o_addtion"];
						}

					} else {
						$curAnswer = $user_result_val["o_value"];
					}
					$result_temp["q_" . $user_result_val["q_id"]] = $curAnswer;

					$q_id = $user_result_val["q_id"];


				}
			}

			// 合并两个数组
			$result["user_result"][] = $result_temp;

		}


		return $result;
	}

	/**
	 * 查询问卷回收情况
	 * @param $currentNaire 问卷ID
	 * @param bool $only_not_finish 是否只获取未完成的用户
	 * @return array
	 */
	public function get_finish_statis($currentNaire, $only_not_finish = false)
	{
		$this->config->load('settings', TRUE);
		$not_active_days = $this->config->item('not_active_time', 'settings'); // 未活跃时间
		// 如果传入用户ID,返回当前用户的信息
		if ($currentNaire == '') {
			return array("err" => 1, "data" => "请传入问卷id");
		}
		$naire = $this->db->where('n_id', $currentNaire)
			->get('naire')->result_array();

		if (empty($naire)) {
			return array("err" => 1, "data" => "未找到相应问卷");
		}

		$sql = "SELECT * FROM ( SELECT *, CASE WHEN id > 0 THEN 1 ELSE 0 END is_finished FROM (SELECT users.u_id, users.u_number, users.u_identity, users.u_name, users.u_nation,users.u_birthday, users.u_sex,users.u_class, users.u_email, users.u_tel, users.u_status, result.u_id as id FROM result RIGHT JOIN users ON users.u_id = result.u_id AND result.n_id = {$currentNaire} GROUP BY users.u_id) as A ) as B WHERE 0=0 ";
		if ($only_not_finish) {
			$sql = $sql . " AND is_finished = 0 ";
		}
		// 冻结用户不会被加入到邮件队列中
		if ($not_active_days > 0) {
			$sql = $sql . " AND u_status < 1 ";
		}
		$query = $this->db->query($sql);

		if (!$query) {
			return array();
		} else {
			return array(
				"naire" => $naire[0],
				"statis" => $query->result_array()
			);
		}
	}

	// 查看回收情况
	public function submit_statis()
	{
		$this->config->load('settings', TRUE);
		$not_active_days = $this->config->item('not_active_time', 'settings'); // 未活跃时间
		// 如果传入用户ID,返回当前用户的信息
		$currentNaire = $this->input->post_get('n_id', TRUE);
		if ($currentNaire == '') {
			return array("err" => 1, "data" => "请传入问卷id");
		}
		$status = $this->input->post_get('status', TRUE);
		$u_class = $this->input->post_get('u_class', TRUE);
		$sql = "SELECT * FROM ( SELECT *, CASE WHEN id > 0 THEN 1 ELSE 0 END is_finished FROM (SELECT users.u_id, users.u_number, users.u_identity, users.u_name, users.u_nation,users.u_birthday, users.u_sex, users.u_class, users.u_email, users.u_tel, users.u_status, result.u_id as id FROM result RIGHT JOIN users ON users.u_id = result.u_id AND result.n_id = {$currentNaire} GROUP BY users.u_id) as A ) as B WHERE 0=0 ";
		if ($status != '' && $status >= 0) {
			$sql = $sql . " AND is_finished = '{$status}' ";
		}
		if ($u_class != '' && $u_class != "all") {
			$sql = $sql . " AND u_class = '{$u_class}' ";
		}
		// 冻结用户不会被加入到未完成统计中
		if ($not_active_days > 0) {
			$sql = $sql . " AND u_status < 1 ";
		}
		/*
		 * 统计是否已完成
		 * SELECT *, CASE WHEN result > 0 THEN 1 ELSE 0 END is_finished FROM (SELECT users.u_id,users.u_name,users.u_sex,users.u_class,users.u_number,COUNT(*) as result FROM users LEFT JOIN result ON users.u_id = result.u_id AND result.n_id = 23 GROUP BY result.u_id) as A
		 *
		 * */
		// 参数1: $currentPage 当前页码, 参数2: $pageSize 每页显示条数
		// 如果有参数,则返回分页的数据,没有返回全部数据
		$currentPage = $this->input->post_get('current', TRUE);
		$pageSize = $this->input->post_get('page_size', TRUE);

		$total_query = $this->db->query($sql);
		$total = $total_query->num_rows();

		if ($currentPage == '' && $pageSize == '') {
			// 返回全部数据
			$query = $this->db->query($sql);
			if (!$query) {
				$error = 1; // ERROR
			} else {
				$error = 0; // OK
			}
			return array('err' => $error, "data" => $query->result_array(), "total" => $total);
		}
		// 返回指定数据
		$offsetRows = $pageSize * ($currentPage - 1); // 数据偏移量
		$query = $this->db->query($sql . " LIMIT {$offsetRows}, {$pageSize}");

		if (!$query) {
			$error = 1; // ERROR
		} else {
			$error = 0; // OK
		}
		return array('err' => $error, "data" => array(
			"data" => $query->result_array(),
			"total" => $total
		), "msg" => '');

	}

	// 修改状态
	public function change_status()
	{
		$n_id = $this->input->post_get('n_id', TRUE);
		// 修改发布状态
		$this->db->query("UPDATE naire SET n_status = (1-n_status) WHERE n_id='{$n_id}'");
		$result = $this->db->affected_rows();
		return array('err' => 0, "data" => $result);
	}

	// 修改问卷时间
	public function change_time()
	{
		$n_id = json_decode($this->input->raw_input_stream, true)['n_id'];
		$n_deadline = json_decode($this->input->raw_input_stream, true)['n_deadline'];
		// 修改发布状态
		$this->db->query("UPDATE naire SET n_deadline = {$n_deadline} WHERE n_id='{$n_id}'");
		$result = $this->db->affected_rows();
		return array('err' => 0, "data" => $result);
	}
}
