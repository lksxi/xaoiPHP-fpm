<?php
namespace xaoi;

class mysql_tab{
	protected $db;
	private $tab;

	function __construct($db,$tab){
		$this->db = $db;
		$this->tab = strpos($tab,'{pre}') !== false?str_replace('{pre}',$db->pre(),$tab):$db->pre().$tab;
		if(strpos($this->tab,' ') === false && strpos($this->tab,'`') !== 0)$this->tab = '`'.$this->tab.'`';
	}

	function begin(){
		return $this->db->begin();
	}

	function rollback(){
		return $this->db->rollback();
	}

	function commit(){
		return $this->db->commit();
	}

	private function is_limit($v){
		return $this->db->is_limit($v);
	}

	function add($d){
		$sql = ['insert','into',$this->tab,'field'=>'','values','value'=>''];
		$field = [];
		$str = [];
		$bind = [];
		foreach($d as $k => $v){
			$field[] = $k;
			$str[] = '?';
			$bind[] = $v;
		}
		$sql['field'] = '(`'.implode('`,`',$field).'`)';
		$sql['value'] = '('.implode(',',$str).')';

		return $this->db->_query(implode(' ',$sql),$bind,'add');
	}

	function get(){
		$where = [];
		$field = '';
		$group = '';
		$order = '';
		$limit = '';
		$d = func_get_args();
		switch(count($d)){
			case 1:
				if($this->is_limit($d[0])){
					$limit = $d[0];
				}else{
					$where = $d[0];
				}
			break;
			case 2:
				if($this->is_limit($d[1])){
					list($where,$limit) = $d;
				}else{
					list($where,$field) = $d;
				}
			break;
			case 3:
				if($this->is_limit($d[2])){
					list($where,$field,$limit) = $d;
				}else{
					list($where,$field,$order) = $d;
				}
			break;
			case 4:
				if($this->is_limit($d[3])){
					list($where,$field,$order,$limit) = $d;
				}else{
					list($where,$field,$group,$order) = $d;
				}
			break;
			case 5:
				list($where,$field,$group,$order,$limit) = $d;
			break;
		}

		$sql = ['select','field'=>'*','from',$this->tab,'where'=>'','group'=>'','order'=>'','limit'=>''];
		$sql_bind = [];
		if(!empty($field)){
			$field = explode(',',$field);
			$l = [];
			foreach($field as $v){
				if(stripos($this->tab,'as') || stripos($v,'as')){
					$l[] = $v;
				}else{
					$l[] = '`'.$v.'`';
				}
			}
			$sql['field'] = implode(',',$l);
		}
		if(!empty($where)){
			$str = [];
			foreach($where as $k => $v){
				$s = substr_count($k,'?');
				if($s === 0){
					if(is_array($v)){
						if(empty($v))return [];
						$ins = [];
						foreach($v as $v2){
							$ins[] = '?';
							$sql_bind[] = $v2;
						}
						$str[] = $k.' in('.implode(',',$ins).')';
					}else{
						$str[] = $k.' = ?';
						$sql_bind[] = $v;
					}
				}elseif($s === 1){
					$str[] = $k;
					$sql_bind[] = is_array($v)?$v[0]:$v;
				}else{
					$str[] = $k;
					foreach($v as $v2){
						$sql_bind[] = $v2;
					}
				}
			}
			$sql['where'] = 'where '.implode(' and ',$str);
		}
		if(!empty($group)){
			if(is_string($group))
				$sql['group'] = 'group by ' . $group;
			else if(is_array($group)){
				$sql['group'] = 'group by '.implode(',',$group);
			}
		}
		if(!empty($order)){
			if(is_string($order))
				$sql['order'] = 'order by ' . $order;
			else if(is_array($order)){
				$str = [];
				foreach($order as $k => $v){
					$str[] = $k.' '.($v?'desc':'asc');
				}
				$sql['order'] = 'order by '.implode(',',$str);
			}
		}
		if(!empty($limit)){
			if(is_numeric($limit)){
				$start	= $limit;
			}else{
				$start	= (int)$limit[0];
				$size	= (int)$limit[1];
			}
			$sql['limit'] = 'limit ' . $start . (!empty($size)? ','.$size: '');
		}
		$ret = $this->db->_query(implode(' ',$sql),$sql_bind);
		return $limit === 1?(empty($ret)?[]:$ret[0]):$ret;
	}

	function set(){
		$where = [];
		$set = '';
		$d = func_get_args();
		switch(count($d)){
			case 1:
				$set = $d[0];
			break;
			case 2:
				$where = $d[0];
				$set = $d[1];
			break;
		}

		$sql = ['update',$this->tab,'set','set'=>'','where'=>''];
		$sql_bind = [];
		if(is_array($set)){
			$str = [];
			foreach($set as $k => $v){
				if(is_array($v)){
					$str[] = $k;
					foreach($v as $v2)$sql_bind[] = $v2;
				}else{
					$str[] = '`'.$k.'`=?';
					$sql_bind[] = $v;
				}
			}
			$sql['set'] = implode(',',$str);
		}else{
			$sql['set'] = $set;
		}
		if(!empty($where)){
			$str = [];
			foreach($where as $k => $v){
				$s = substr_count($k,'?');
				if($s === 0){
					if(is_array($v)){
						$ins = [];
						foreach($v as $v2){
							$ins[] = '?';
							$sql_bind[] = $v2;
						}
						$str[] = $k.' in('.implode(',',$ins).')';
					}else{
						$str[] = $k.' = ?';
						$sql_bind[] = $v;
					}
				}elseif($s === 1){
					$str[] = $k;
					$sql_bind[] = is_array($v)?$v[0]:$v;
				}else{
					$str[] = $k;
					foreach($v as $v2){
						$sql_bind[] = $v2;
					}
				}
			}
			$sql['where'] = 'where '.implode(' and ',$str);
		}
		return $this->db->_query(implode(' ',$sql),$sql_bind,'set');
	}

	function del($d){
		$sql = ['delete','from',$this->tab,'where'=>''];
		$sql_bind = [];
		$str = [];
		foreach($d as $k => $v){
			$s = substr_count($k,'?');
			if($s === 0){
				if(is_array($v)){
					$ins = [];
					foreach($v as $v2){
						$ins[] = '?';
						$sql_bind[] = $v2;
					}
					$str[] = $k.' in('.implode(',',$ins).')';
				}else{
					$str[] = $k.' = ?';
					$sql_bind[] = $v;
				}
			}elseif($s === 1){
				$str[] = $k;
				$sql_bind[] = is_array($v)?$v[0]:$v;
			}else{
				$str[] = $k;
				foreach($v as $v2){
					$sql_bind[] = $v2;
				}
			}
		}
		$sql['where'] = 'where '.implode(' and ',$str);
		return $this->db->_query(implode(' ',$sql),$sql_bind,'del');
	}
}