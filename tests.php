<?php
include 'php/funcs.php';


//a(explode('/', '///'));



include 'php/url_builder.php';
$url = new URL_Builder();

$item = array();
$item[] = 'mailto:a';
$item[] = 'javascript:a';
$item[] = '//';
$item[] = '///';
$item[] = 'a///';
$item[] = '/\/';
$item[] = '/\\\\';
$item[] = '\/\\';
$item[] = '////a/df';
$item[] = '//ajax.googleapis.com';
$item[] = '//www.example.com:8043';
$item[] = '//www.example.com:8043/';
$item[] = '//ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.min.js';
$item[] = 'http///ajax/libs/j://g';
$item[] = 'https://ajax.googleapis.com/ajax/libs/j://g';
$item[] = 'ftp:/ajax.googleapis.com/ajax/libs/j:/f';
$item[] = '/ajax.googleapis.com/ajax/libs/j://f';
$item[] = '/ajax.googleapis.com/ajax/libs/j:/f';
$item[] = '//ajax.googleapis.com/ajax/libs/j:sd';
$item[] = 'index.php://sd';
$item[] = '://ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.min.js';
$item[] = ':/ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.min.js';
$item[] = ':';
$item[] = ':/';
$item[] = ':/a';
$item[] = '://';
$item[] = '://a';
$item[] = 'a://ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.min.js';
$item[] = '#hashtag';
$item[] = '#http://www.example.com';
$item[] = '#http%3A%2F%2Fwww.example.com';
$item[] = '?url=http://www.example.com';
$item[] = '?url=http://www.example.com#hashtag';
$item[] = '?url=http://www.example.com#http://www.example.com';
$item[] = '?url=http%3A%2F%2Fwww.example.com';
$item[] = '?url=http:';
$item[] = '?url=http:/';
$item[] = '/?url=http:/';
$item[] = 'http:';
$item[] = 'http';
$item[] = 'http:/www.example.com';
$item[] = 'http:/example/a.com';
$item[] = 'http:/';
$item[] = 'http://';
$item[] = 'http:///';
$item[] = 'http:///www.example.com';
$item[] = 'http://w';
$item[] = 'http://www.example.com';
$item[] = 'http://www.example.com/';
$item[] = 'http://www.example.com/abc';
$item[] = 'http://www.example.com/abc/';
$item[] = 'http://www.example.com/abc.a';
$item[] = 'http://www.example.com/abc.php/';
$item[] = 'http:\\www.example.com/abc.php/';
$item[] = 'http://www.example.com/abc.php/sfasfas/../../../a.php';
$item[] = 'http://www.example.com/abc.php/sfasfas/./.././a.php';
$item[] = 'http:?Fdsf://dfsaf';

foreach($item as $val)
{
	$tmp = $url->parse($val);
	if ( ! empty($tmp['errors']) || $tmp['path_type'] < 4 || $tmp['path_type'] > 7)
	{
		continue;
	}
	//print_r(url_data($val));
	echo '<strong>'.$val.'</strong><br />';
	//q(parse_url($val), 'green');
	//q($url->parse($val));
	q($tmp, 'purple');
	$tmp2 = $url->resolve_href($tmp, 'http', 'google.ca', '/fas/', 'a.php');
	q($tmp2);
	echo '<a href="'.$val.'">'.$val.'</a><br />';
	echo "<br />--------------------------------------<br /><br />";
}
exit;