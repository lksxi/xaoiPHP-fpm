<?php
namespace index;

class user extends main{

	public function refresh(){
		$user = session('user');

		$time = time();
		$token = base64_encode($user['id'].':'.$time.':'.md5($user['id'].$time).base64_encode($user['password']));
		app('session')->session_id($token);

		return [
			0,
            [
                'access_token'		=> $token,
                'expires_in'		=> config('xaoi/session.expire')
            ]
		];
	}

	//修改密码
	function repwd(){
		$dold = \tool\pwd::md5(input('post.newpwd'));
		$dnew = \tool\pwd::md5(input('post.oldpwd'));

		$res = db('user')->set([
			'id'	=> session('user.id'),
			'password'	=> $dold,
		],[
			'password'	=> $dnew,
		]);

		if($res){
			return [0];
		}else{
			return [500,null,'密码错误'];
		}
	}

}
