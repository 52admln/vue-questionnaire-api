<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Naire extends CI_Controller
{

    public function index()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->get_naire_list();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 获取指定问卷详情
    public function detail()
    {
        // 获取参数 naire id
        $this->load->model('naire_model');
        $type = json_decode($this->input->raw_input_stream, true)['type']; // 类型为 vote / 或 不传
        if (isset($type) && $type == 'vote') {
            $result = $this->naire_model->get_vote_detail();
        } else {
            $result = $this->naire_model->get_naires();
        }
        echo json_encode($result);
    }

    public function getRank()
    {
        // 获取参数 naire id
        $this->load->model('naire_model');
        $result = $this->naire_model->statis_naire();
        echo json_encode($result);
    }

    public function setStyle()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $n_id = json_decode($this->input->raw_input_stream, true)['n_id'];
            $options = json_decode($this->input->raw_input_stream, true)['options'];
            if (empty($n_id) || empty($options)) {
                echo json_encode(array(
                    "err" => 1,
                    "data" => "请传入参数"
                ));
                return;
            }
            $result = $this->db->where('n_id', $n_id)->update('naire', array(
                'n_options' => $options
            ));
            if ($result > 0) {
                $error = 0;
            } else {
                $error = 1;
            }

            echo json_encode(array(
                "err" => $error,
                "data" => $error ? "数据更新失败" : "数据更新成功"
            ));
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 保存问卷
    public function save()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->save_naire();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }

    }

    // 提交问卷
    public function submit()
    {
        $this->load->model('naire_model');
        $this->load->model('user_model');
        $post_data = json_decode($this->input->raw_input_stream, true)['result'];
        $user_id = json_decode($this->input->raw_input_stream, true)['userId'];
        $n_id = json_decode($this->input->raw_input_stream, true)['nId'];
        $result = $this->naire_model->submit_naire($post_data, $n_id, $user_id);

        // 当用户成功提交后，发送提交成功邮件
        if ($result['err'] == 0) {
            $this->user_model->update_user_active($user_id);
            self::_sendMail($user_id);
        }

        echo json_encode($result);
    }

    /**
     * 发送提交成功邮件通知
     * @param $user_id
     * @return mixed
     */
    private function _sendMail($user_id)
    {
        $this->load->library('email');
        $this->load->model('user_model');
        $this->config->load('settings', TRUE);

        $result = $this->db->where('u_id', $user_id)->get('users')->row();

        $subject = '恭喜您！您填写的问卷已经成功提交';
        $message = '<p>尊敬的 ' . $result->u_name . '：</p>
                    <p>您好！</p></br>
                    <p>您的问卷已提交成功，感谢参与。</p>';

        $body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=' . strtolower(config_item('charset')) . '" />
            <title>' . html_escape($subject) . '</title>
            <style type="text/css">
                body {
                    font-family: Arial, Verdana, Helvetica, sans-serif;
                    font-size: 16px;
                }
            </style>
        </head>
        <body>
        ' . $message . '
        </body>
        </html>';

        $result = $this->email
            ->from($this->config->item('mail_from', 'settings'), $this->config->item('poster_name', 'settings'))// 发件人
            ->to($result->u_email)// 收件人
            ->subject($subject)
            ->message($body)
            ->send();
        return $result;
    }

    // 删除问卷
    public function del()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->del_naire();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 问卷统计
    public function statis()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->statis_naire();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 交叉分析
    public function crossanalysis()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->cross_analysis();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 交叉分析题目选项
    public function questions()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->get_questions();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 样本数据
    public function sourcedata()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->source_data();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 用于导出 Excel 表
    public function sourcedataExport()
    {
        $this->load->model('naire_model');
        $token = $this->input->post_get('token'); // 这里的 token 通过 get 参数的方式获取
        $n_id = $this->input->post_get('n_id', TRUE);
        $current = $this->input->post_get('current', TRUE);
        $page_size = $this->input->post_get('page_size', TRUE);
        if (empty($n_id) || empty($current) || empty($page_size)) {
            echo json_encode(array("err" => 1, "data" => "请传入参数值"));
            return;
        }
        $result = $this->naire_model->all_source_data($n_id, $current, $page_size);

        if ($token != '' && jwt_helper::validate($token)) {
            // 加载 PHPExcel 库
            $this->load->library('PHPExcel.php');
            $this->load->library('PHPExcel/IOFactory.php');
            $objPHPExcel = new PHPExcel();
            // 设置当前工作表
            $objPHPExcel->setActiveSheetIndex(0);
            $objActSheet = $objPHPExcel->getActiveSheet();

            // 预设表头
            $preHeader = array('姓名', '提交时间', '部门', '工号');

            // Excel 标题
            $objActSheet->setCellValue('A1', $result["naire"]["title"]);
            $objActSheet->getStyle('A1')->getFont()->setSize(20);
            $objActSheet->getStyle('A1')->getFont()->setBold(true);
            $objActSheet->mergeCells('A1:' . PHPExcel_Cell::stringFromColumnIndex(count($result["question"]) + count($preHeader) - 1) . '1');
            $objActSheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

            // 渲染预设表头
            for ($i = 0; $i < count($preHeader); $i++) {
                $objPHPExcel->getActiveSheet()
                    ->setCellValue(PHPExcel_Cell::stringFromColumnIndex($i) . '2', $preHeader[$i]);
            }
            // 每行渲染的key
            $columns = array('u_name', 's_creattime', 'u_class', 'u_number');
            // 预设表头后的题目名称
            $key = 4;
            foreach ($result["question"] as $v) {
                $objPHPExcel->getActiveSheet()
                    ->setCellValue(PHPExcel_Cell::stringFromColumnIndex($key) . '2', $v["q_content"]);
                array_push($columns, "q_" . $v["q_id"]);
                $key++;
            }

            // 渲染用户填写的数据，从第三行开始
            $maxColumn = count($columns);
            for ($i = 0; $i < $maxColumn; $i++) {
                $key = 2; // 当前行
                foreach ($result["user_result"] as $v) {
                    //设置循环从第二行开始
                    $key++;
                    $pCoordinate = PHPExcel_Cell::stringFromColumnIndex($i) . '' . $key;
                    if ($columns[$i] == 's_creattime') {
                        $pValue = date('Y-m-d H:i:s', intval($v[$columns[$i]] / 1000));
                    } else {
                        $pValue = $v[$columns[$i]];
                    }
                    $objPHPExcel->getActiveSheet()->setCellValue($pCoordinate, $pValue);
                }
            }

            $filename = 'source_data_' . date("Ymdhms", time()) . '.xls'; //save our workbook as this file name
            header('Content-Type: application/vnd.ms-excel'); //mime type
            header('Content-Disposition: attachment;filename="' . $filename . '"'); //tell browser what's the file name
            header('Cache-Control: max-age=0'); //no cache

            //save it to Excel5 format (excel 2003 .XLS file), change this to 'Excel2007' (and adjust the filename extension, also the header mime type)
            //if you want to save it as .XLSX Excel 2007 format
            $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
            //force user to download the Excel file without writing it to server's HD
            $objWriter->save('php://output');
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 查看统计情况
    public function submitStatis()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->submit_statis();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 导出完成情况为 Excel
    public function exportStatis()
    {
        $this->load->model('naire_model');
        $token = $this->input->post_get('token'); // 这里的 token 通过 get 参数的方式获取
        $result = $this->naire_model->get_finish_statis($this->input->post_get('n_id')); // is_finished  已完成 1 未完成 0
        if ($token != '' && jwt_helper::validate($token)) {
            // 加载 PHPExcel 库
            $this->load->library('PHPExcel.php');
            $this->load->library('PHPExcel/IOFactory.php');
            $objPHPExcel = new PHPExcel();
            // 设置当前工作表
            $objPHPExcel->setActiveSheetIndex(0);
            $objActSheet = $objPHPExcel->getActiveSheet();
            // Excel 标题
            $objActSheet->setCellValue('A1', $result["naire"]["n_title"]);
            $objActSheet->getStyle('A1')->getFont()->setSize(20);
            $objActSheet->getStyle('A1')->getFont()->setBold(true);
            $objActSheet->mergeCells('A1:J1');
            $objActSheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

            // 设置列宽
            $header_arr = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
            foreach ($header_arr as $key) {
                $objPHPExcel->getActiveSheet()->getColumnDimension($key)->setWidth(20);
            }

            // 设置表头
            $objPHPExcel->getActiveSheet()
                ->setCellValue('A2', '工号')
                ->setCellValue('B2', '身份证')
                ->setCellValue('C2', '姓名')
                ->setCellValue('D2', '民族')
                ->setCellValue('E2', '出生日期')
                ->setCellValue('F2', '性别')
                ->setCellValue('G2', '部门')
                ->setCellValue('H2', '邮箱')
                ->setCellValue('I2', '手机号码')
                ->setCellValue('J2', '完成情况');

            // 接下来就是写数据到表格里面去
            $key = 2;
            foreach ($result["statis"] as $v) {
                //设置循环从第二行开始
                $key++;
                $objActSheet
                    //Excel的第A列，name是你查出数组的键值字段，下面以此类推
                    ->setCellValueExplicit('A' . $key, $v['u_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValueExplicit('B' . $key, $v['u_identity'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValue('C' . $key, $v['u_name'])
                    ->setCellValue('D' . $key, $v['u_nation'])
                    ->setCellValue('E' . $key, $v['u_birthday'])
                    ->setCellValue('F' . $key, $v['u_sex'] == '0' ? '男' : '女')
                    ->setCellValue('G' . $key, $v['u_class'])
                    ->setCellValue('H' . $key, $v['u_email'])
                    ->setCellValue('I' . $key, $v['u_tel'])
                    ->setCellValue('J' . $key, $v['is_finished'] == '0' ? '未完成' : '已完成');
            }


            $filename = 'export' . date("Ymdhms", time()) . '.xls'; //save our workbook as this file name
            header('Content-Type: application/vnd.ms-excel'); //mime type
            header('Content-Disposition: attachment;filename="' . $filename . '"'); //tell browser what's the file name
            header('Cache-Control: max-age=0'); //no cache

            //save it to Excel5 format (excel 2003 .XLS file), change this to 'Excel2007' (and adjust the filename extension, also the header mime type)
            //if you want to save it as .XLSX Excel 2007 format
            $objWriter = IOFactory::createWriter($objPHPExcel, 'Excel5');
            //force user to download the Excel file without writing it to server's HD
            $objWriter->save('php://output');
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 设置问卷状态
    public function changeStatus()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->change_status();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }

    // 设置问卷截止时间
    public function changeTime()
    {
        $this->load->model('naire_model');
        $header = $this->input->get_request_header('Authorization', TRUE);
        list($token) = sscanf($header, 'token %s');
        if ($header != '' && jwt_helper::validate($token)) {
            $result = $this->naire_model->change_time();
            echo json_encode($result);
        } else {
            show_error("Permission denied", 401, "Please check your token.");
        }
    }
}
