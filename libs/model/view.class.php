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

    public function __construct($dbname)
    {
        parent::__construct($dbname);

        if(empty($this->viewFields) || !is_array($this->viewFields)){
            trigger_error('指定的关联表不存在！', E_USER_WARNING);
            return false;
        }
        $bool = false;
        foreach($this->viewFields as $word => $data){
            $this->field($data['field'], $word);
            if($bool)$this->join($data['table'].' '.$word, $data['on'], $data['type']?:'inner');
            else $this->table($data['table'], $word);
            $bool = true;
        }
    }

}