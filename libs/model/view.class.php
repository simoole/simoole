<?php

namespace Root\Model;
use Root\Model;

class ViewModel extends Model
{
    protected $viewFields = [
//		'A' => [
//			'table' => '<表名(无前缀)>',
//			'field' => [
//				'<字段名>',
//				'<字段名>' => '<字段别名>'
//			]
//		],
//		'B' => [
//			'table' => '<数据库名>.<表名(无前缀)>',
//			'field' => [
//				'<字段名>'
//			],
//			'on' => 'B.<字段名>=A.<字段名>',
//			'group' => '<字段名>',
//          'order' => ['<字段名>', 'asc|desc']
//		]
    ];

    //初始化模型
    public function _init(){
        if(empty($this->viewFields) || !is_array($this->viewFields)){
            trigger_error('指定的关联表不存在！', E_USER_WARNING);
            return false;
        }
        $bool = false;
        foreach($this->viewFields as $word => $data){
            if(empty($data['table']))continue;
            if(!empty($data['field']))$this->field($data['field'], $word);
            if($bool && empty($data['on']))continue;
            if($bool)$this->join($data['table'].' '.$word, $data['on'], !empty($data['type'])?$data['type']:'inner');
            else $this->table($data['table'], $word);
            $bool = true;
        }
    }

}