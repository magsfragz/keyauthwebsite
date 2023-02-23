<?php

namespace misc\etc;

function sanitize($input)
{
    if (empty($input) & !is_numeric($input)) { // in the event the input can't be sanitized
        return NULL;
    }
    $search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
    $replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");
    return str_replace($search, $replace, strip_tags(trim($input))); // return string with quotes escaped to prevent SQL injection, script tags stripped to prevent XSS attack, and trimmed to remove whitespace
}
function random_string_upper($length = 10, $keyspace = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'): string // replaces upper-case X characters in key mask
{ // https://github.com/FinGu/c_auth/blob/cfbd7036e69561e538e26dc47f7690dbc0d8ba53/functions/general/functions.php#L55
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $rand_index = random_int(0, strlen($keyspace) - 1);
        $out .= $keyspace[$rand_index];
    }
    return $out;
}
function random_string_lower($length = 10, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyz'): string // replaces lower-case X characters in key mask
{ // https://github.com/FinGu/c_auth/blob/cfbd7036e69561e538e26dc47f7690dbc0d8ba53/functions/general/functions.php#L55
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $rand_index = random_int(0, strlen($keyspace) - 1);
        $out .= $keyspace[$rand_index];
    }
    return $out;
}
function formatBytes($bytes, $precision = 2) // convert number of file bytes to human-recognizable unit
{ // https://stackoverflow.com/a/2510459
    $units = array(
        'B',
        'KB',
        'MB',
        'GB',
        'TB'
    );
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
function generateRandomString($length = 10)
{ // https://stackoverflow.com/a/4356295
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
function generateRandomNum($length = 6)
{ // adapted from https://stackoverflow.com/a/4356295
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
function isBreached($pw) // query HaveIBeenPwned's API (huge dataset, FBI and many other agencies worldwide contrubute) to see if pass has been leaked. This only supplies their API with SHA1 hash prefix
{ // from https://github.com/Mikjaer/haveibeenpwned/blob/main/p0wned.php
    $hash = strtoupper(sha1($pw));
    foreach (explode("\n", file_get_contents("https://api.pwnedpasswords.com/range/" . substr($hash, 0, 5))) as $pmatch)
        if (substr($hash, 0, 5) . substr($pmatch, 0, strpos($pmatch, ":")) == $hash)
            return true;
}
function isPhonyEmail($email) // check if valid email format, if known temporary email, if email server online, and if mailbox found on email server
{
	if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return true;
	}
	
    $resp = file_get_contents("https://api.mailcheck.ai/email/" . $email);
    $json = json_decode($resp);
	
	if($json->disposable) {
		return true;
	}
	
	if (strpos($email, ".ru") !== false) {
		return false; 
		// russian email services either have a lot of downtime or heavy rate limits, because every other time I make SMTP connection with an .ru email it fails
	}
	
	if (strpos($email, "proton") !== false) {
		return false; 
		// protonmail is also giving us issues
	}
	
	ini_set("default_socket_timeout", 1);
	$connection = @fsockopen("gmail-smtp-in.l.google.com", 25);
	
	// check if port 25 is open (many hosts have it closed inherently)
	if (is_resource($connection)) {
		global $mail;
		require_once (($_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/panel" || $_SERVER['DOCUMENT_ROOT'] == "/usr/share/nginx/html/api") ? "/usr/share/nginx/html" : $_SERVER['DOCUMENT_ROOT']) . '/includes/VerifyEmail.class.php'; 
		
		return (!$mail->check($email));
	}
	else {
	    return false;
	}
}