<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * @package: 队列任务专用类
 * @author: friker
 * @date: 2016-11-30 11:08
 */

class Queue
{

    protected $_ci;  # CI 框架 超级对象

    public function __construct()
    {
        $this->_ci = &get_instance();
        $this->_ci->load->model('queue_task_model');
    }

    /*
     * @todo: 添加任务到队列
     * @param : string  $taskphp  待执行任务的PHP 文件 路径
     * @param ：string  $params   执行任务时 所需要的参数
     * @return: array | boolean
     * 例如： 执行发送模板消息给多个人
     * $params = array(
     *    'content' => '发送内容',
     *    'uids'    => array(6,12,142)
     * );
     *
     * $this->load->library('Queue');
     * $pams = http_build_query($params);  #反函数 是  parse_str($pams,$paramsres);
     * $this->queue->addTask('sendTemplate.php',$pams);
     */
    public function addTask($naire_id, $user_list)
    {
        if (empty($naire_id) || empty($user_list)) {
            return 0;
        }
        // insert_batch 批量插入数据
        // 循环 $user_list

        $insert_data = array();
        foreach ($user_list as $key => $val) {
            $insert_data[] = array(
                'user_id' => $val['u_id'], // 用户ID
                'naire_id' => $naire_id,      // 问卷ID
                'task_createtime' => utils_helper::getMillisecond(),
                'task_status' => 0,     // 处理状态 0 待处理 1 已处理
            );
        }

        $queue_count = $this->_ci->queue_task_model->add($insert_data);
        return $queue_count;
    }

    public function getQueueStatus()
    {
        return $this->_ci->db->where('config_name', 'smtp_sending')->get('configs')->row()->config_val;
    }

    public function setQueueStatus($status)
    {
        $update_data = array(
            "config_name" => "smtp_sending",
            "config_val" => $status
        );
        $this->_ci->db->where('config_name', 'smtp_sending')->update('configs', $update_data);
        return $this->_ci->db->affected_rows();
    }

    /*
     * @todo: 读取任务队列
     * @param：integer $take_num  获取任务个数
     * @return: array | boolean
     */
    public function getQueueTask($take_num = 10, $task_id)
    {
        $take_num = intval($take_num);
        if ($take_num < 0) {
            return false;
        }
        if (empty($task_id)) {
            $sql = "SELECT * from task, users, naire WHERE users.u_id = task.user_id AND naire.n_id = task.naire_id AND task.task_status = 0 AND users.u_email != '' ORDER BY task.task_createtime ASC " . " LIMIT 0 , " . $take_num;
        } else {
            $sql = "SELECT * from task, users, naire WHERE users.u_id = task.user_id AND naire.n_id = task.naire_id AND task.task_status = 0 AND users.u_email != '' AND task.task_id in (". $task_id .") ORDER BY task.task_createtime ASC";
        }
        $tasks = $this->_ci->db
            ->query($sql)
            ->result_array();

        if (!$tasks) {
            return false;
        }
        return $tasks;
    }

    /*
     * @todo: 更新任务状态
     * @param: integer $task_id 任务ID
     * @return: boolean
     */
    public function updateQueueById($task_id = 0, $status = 1)
    {
        $task_id = intval($task_id);
        if ($task_id < 0) {
            return false;
        }
        $update_data = array(
            'task_status' => $status,
            'task_updatetime' => utils_helper::getMillisecond()
        );
        $res = $this->_ci->queue_task_model->edit($task_id, $update_data);
        return $res;
    }
}
