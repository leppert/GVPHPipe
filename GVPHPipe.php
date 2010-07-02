<?php
//read from stdin
$fd = fopen("php://stdin", "r");
$email_post = "";
while (!feof($fd)) {
	$email_post .= fread($fd, 1024);
}
fclose($fd);

$email = parse_email($email_post);

if(stripos( $email['subject'], 'sms' ) !== false){
	$email['type'] = 'SMS';
} else if(stripos( $email['subject'], 'voicemail' ) !== false){
	$email['type'] = 'Voicemail';
} else {
	$email['type'] = 'Inbox';
}

$email = clean_email($email);
			
// ===========================
// = SEND PROWL NOTIFICATION =
// ===========================
//API Documentation: http://prowl.weks.net/api.php
include('ProwlPHP.php');

$prowl = new Prowl('your_prowl_api_key_goes_here');
$prowl->push(array(
	'application'	=> 'GV',
	'event'			=> $email['subject'],
	'description'	=> $email['body'],
	'priority'		=> 0,
),true);

var_dump($prowl->getError());	// Optional
var_dump($prowl->getRemaining()); // Optional
var_dump(date('d m Y h:i:s', $prowl->getResetdate()));	// Optional


// =================
// = EMAIL PARSING =
// =================
//from: http://www.evolt.org/article/Incoming_Mail_and_PHP/18/27914/index.html
function parse_email($email){
	// handle email
	$lines = explode("\n", $email);

	// empty vars
	$parsed_email = array(
		"original" => $email,
		"from" => "",
		"subject" => "",
		"headers" => "",
		"body" => ""
	);
	
	$splittingheaders = true;

	for ($i=0; $i < count($lines); $i++) {
	    if ($splittingheaders) {
	        // this is a header
	        $parsed_email["headers"] .= $lines[$i]."\n";

	        // look out for special headers
	        if (preg_match("/^Subject: (.*)/", $lines[$i], $matches)) {
	            $parsed_email["subject"] = $matches[1];
	        }
	        if (preg_match("/^From: (.*)/", $lines[$i], $matches)) {
	            $parsed_email["from"] = $matches[1];
	        }
	    } else {
	        // not a header, but message
	        $parsed_email["body"] .= $lines[$i]."\n";
	    }

	    if (trim($lines[$i])=="") {
	        // empty line, header section has ended
	        $splittingheaders = false;
	    }
	}
	return $parsed_email;
}

function clean_email($email){
	$email['subject'] = str_replace("from", ":", $email['subject']);

	if($email['type'] == "SMS"){
		$subject = explode("[(",$email['subject']);
		$email['subject'] = $subject[0];
		$email['body'] = explode("\n--\nSent",$email['body']);
		$email['body'] = $email['body'][0];
	}

	if($email['type'] == "Voicemail"){
		$email['subject'] = str_replace("New voicemail", "Voicemail", $email['subject']);
		$email['subject'] = explode(" at ",$email['subject']);
		$email['subject'] = $email['subject'][0];
		preg_match('/(?:Transcript: )(.*)/i', $email['original'], $body);
		$email['body'] = $body[1];
	}																							
	return $email;
}
