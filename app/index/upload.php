<?php
namespace index;

/**
 * 文件上传
 */
class upload extends main{

	/**
	 * 上传
	 */
	function add(){
		if(empty($_FILES['file']))return [-1,'','上传文件失败'];

		$file = $_FILES['file'];
		$dir = date('/Y/m-d/');

		$type = array_pop(explode('.',$file["name"]));
		if($type === 'php')$type = 'txt';
		$filename = $this->dec62(str_replace('.','',''.microtime(true)).mt_rand(1000, 9999)) . '.' . $type;

		if(!is_dir(_UPLOAD_.$dir))mkdir(_UPLOAD_.$dir,0777,true);

		//移动文件
		if(move_uploaded_file($file["tmp_name"],_UPLOAD_.$dir.$filename)){			
			return [0,__UPLOAD__.$dir.$filename];
		}else{
			return [-1,'','上传文件失败'];
		}
	}

	private function dec62($n) {
		$base = 62;
		$index = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$ret = '';
		for($t = floor(log10($n) / log10($base)); $t >= 0; $t --) {
			$a = floor($n / pow($base, $t));
			$ret .= substr($index, $a, 1);
			$n -= $a * pow($base, $t);
		}
		return $ret;
	}

}
