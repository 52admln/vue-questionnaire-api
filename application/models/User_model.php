<?php

class User_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        // Your own constructor code
        $this->load->database();
    }

    // 获取用户
    public function get_users()
    {
        // 搜索功能
        $keyword = $this->input->post_get('keyword', TRUE);
        $value = $this->input->post_get('value', TRUE);
        // 如果传入用户ID,返回当前用户的信息
        $currentUser = $this->input->post_get('u_id', TRUE);
        // 参数1: $currentPage 当前页码, 参数2: $pageSize 每页显示条数
        // 如果有参数,则返回分页的数据,没有返回全部数据
        $currentPage = $this->input->post_get('current', TRUE);
        $pageSize = $this->input->post_get('page_size', TRUE);
        // 如果存在搜索条件，则模糊搜索
        if ($value != '' && $keyword != '') {
            $total_query = $this->db->like($keyword, $value)->get('users');
        } else {
            $total_query = $this->db->get('users');
        }
        $total = $total_query->num_rows();

        // 如果传入用户ID,返回当前用户的信息
        if ($currentUser != '') {
            // 返回全部数据
            $query = $this->db->where('u_id', $currentUser)
                ->get('users');
            // todo 搜索条件
            if (!$query) {
                $error = 1; // ERROR
            } else {
                $error = 0; // OK
            }
            return array('err' => $error, "data" => $query->result_array(), "total" => $total);
        }

        if ($currentPage == '' && $pageSize == '') {
            if ($value != '' && $keyword != '') {
                // 搜索条件
                $query = $this->db->like($keyword, $value)->get('users');
            } else {
                // 返回全部数据
                $query = $this->db->get('users');
            }

            if (!$query) {
                $error = 1; // ERROR
            } else {
                $error = 0; // OK
            }
            return array('err' => $error, "data" => $query->result_array(), "total" => $total);
        }
        // 返回指定数据
        $offsetRows = $pageSize * ($currentPage - 1); // 数据偏移量
        if ($value != '' && $keyword != '') {
            $query = $this->db->like($keyword, $value)->limit($pageSize, $offsetRows)
                ->get('users');
        } else {
            $query = $this->db->limit($pageSize, $offsetRows)
                ->get('users');
        }

        if (!$query) {
            $error = 1; // ERROR
        } else {
            $error = 0; // OK
        }
        return array('err' => $error, "data" => array(
			"list" => $query->result_array(),
			"total" => $total
		), "msg" => '');

    }

    // 更新用户
    public function update_user()
    {
        $u_id = json_decode($this->input->raw_input_stream, true)['u_id'];
        $update_data = array(
//            'u_major' => json_decode($this->input->raw_input_stream, true)['u_major'],
            'u_name' => json_decode($this->input->raw_input_stream, true)['u_name'],
            'u_sex' => json_decode($this->input->raw_input_stream, true)['u_sex'],
            'u_class' => json_decode($this->input->raw_input_stream, true)['u_class'],
            'u_number' => json_decode($this->input->raw_input_stream, true)['u_number'],
            'u_birthday' => json_decode($this->input->raw_input_stream, true)['u_birthday'],
            'u_nation' => json_decode($this->input->raw_input_stream, true)['u_nation'],
            'u_identity' => json_decode($this->input->raw_input_stream, true)['u_identity'],
            'u_email' => json_decode($this->input->raw_input_stream, true)['u_email'],
            'u_tel' => json_decode($this->input->raw_input_stream, true)['u_tel'],
            'u_password' => sha1(json_decode($this->input->raw_input_stream, true)['u_number'])
        );
        $this->db->where('u_id', $u_id);
        $this->db->update('users', $update_data);
        return $this->db->affected_rows();
    }

    // 批量上传,新增用户
    public function add_user($data)
    {
        // 如果工号冲突，则不添加此用户
        $is_exist = $this->db->get_where('users', array('u_number' => $data[3]));
        if ($is_exist->num_rows() > 0) {
            return 0;
        }
        $insert_data = array(
//            'u_major' => $data[0],
            'u_name' => preg_replace("/[\s]{2,}/", "", $data[0]),
            'u_sex' => $data[1] == '男' ? 0 : 1,
            'u_class' => $data[2],
            'u_number' => $data[3],
            'u_birthday' => $data[4],
            'u_nation' => $data[5],
            'u_identity' => strtoupper($data[6]),
            'u_password' => sha1(strtoupper($data[6])), // 密码与身份证相同
            'u_email' => $data[7],
            'u_tel' => $data[8]
        );

        $this->db->insert('users', $insert_data);
        return $this->db->affected_rows();
    }

    // 清空所有用户
    public function clear_user()
    {
        // 清空用户的同时，删除所有与其关联的数据
        $this->db->empty_table("users");
        $this->db->empty_table("submit_log");
        $this->db->empty_table("result");
        $rows = $this->db->affected_rows();
        if ($rows != 0) {
            $error = 0; // OK
            return array('err' => $error, "data" => $rows);
        } else {
            $error = 1; // ERROR
            return array('err' => $error, "data" => '未删除任何数据');
        }
    }

    // 删除用户
    public function del_user()
    {
        $user_id = json_decode($this->input->raw_input_stream, true)['u_id'];

        // 删除多表中的数据
//        $del_tables = array('users', 'helper');
//        $this->db->where('user_id', $user_id);
//        $this->db->delete($del_tables);
//        $result = $this->db->affected_rows();

        if (empty($user_id)) {
            return array('err' => 1, "data" => '请传入参数');
        } else {
            $this->db->query("DELETE FROM users WHERE users.u_id in ({$user_id})");
            $rows = $this->db->affected_rows();

            // 同时清空任务表
            $this->db->query("DELETE FROM task WHERE user_id in({$user_id})");
            // 删除多表中的数据
            $del_tables = array('users', 'result', 'submit_log');
            $this->db->where_in('u_id', $user_id);
            $this->db->delete($del_tables);

        }

        if ($rows != 0) {
            $error = 0; // OK
            return array('err' => $error, "data" => $rows);
        } else {
            $error = 1; // ERROR
            return array('err' => $error, "data" => '未删除任何数据');
        }

    }

    // 获取用户ID
    public function get_user_id()
    {
        $this->config->load('settings', TRUE);
        $name = json_decode($this->input->raw_input_stream, true)['name'];
        $identity = json_decode($this->input->raw_input_stream, true)['identity'];
        $nId = json_decode($this->input->raw_input_stream, true)['n_id'];

        $query = $this->db->get_where('users', array('u_name' => $name, 'u_identity' => strtoupper($identity)));
        $row = $query->row_array();
        if (count($row) > 0) {
            // 数据库查找该用户
            $query_naire = $this->db->get_where('result', array('n_id' => $nId, 'u_id' => $row["u_id"]));
            $row_naire = $query_naire->num_rows();

            $not_active_days = $this->config->item('not_active_time', 'settings'); // 未活跃时间
            $is_not_active = (!empty($row["u_active_time"]) && (utils_helper::getMillisecond() - $row["u_active_time"]) > $not_active_days);

            // 用户启用了活跃时间，并且用户已经超过了一段时间未登陆
            if ($not_active_days !== 0 && ($row["u_status"] > 0 || $is_not_active)) {
                $this->change_user_status($row["u_id"], 1);
                return array('err' => 1, "data" => "由于一段时间未参与活动，账户已被冻结，请联系管理员进行解冻！");
            }

            $data = array(
                "u_id" => $row["u_id"],
                "name" => $row["u_name"],
                "isFinished" => $row_naire > 0
            );

            return array('err' => 0, "data" => $data);

        } else {
            return array('err' => 1, "data" => "用户不存在");
        }
    }

    // 获取班级列表
    public function get_class_list()
    {
        $query = $this->db->query('SELECT u_class FROM users GROUP BY u_class');
        $result = $query->result_array();
        return array('err' => 0, "data" => $result);
    }
}
