<?php
require('vendor/autoload.php');
// Use the REST API Client to make requests to the Twilio REST API
use Twilio\Rest\Client;
getAndUploadRecording();

function getAndUploadRecording(){
	$userId = 'YOUR_USER_ID';
	$toDate= date('Y-m-d');
	$fromDate= date('Y-m-d'); //'2021-01-27'; 
	$successCode = 200;
	$errrCode = 404;
	$exceptionCode = 401;
	$accessToken = 'YOUR_ACCESS_TOKEN';
	try{
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => "https://api.zoom.us/v2/users/$userId/recordings?trash_type=meeting_recordings&to=$toDate&from=$fromDate&mc=false&page_size=1",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
			"authorization: Bearer $accessToken"
		  ),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if($err) {
			$message = $err;
			$subject = 'Error occured';
			$line_no = 0;
			sendErrorEmail($subject, $message, $line_no, $errrCode);
			return;
		}else {
			$data = json_decode($response, true);
			if(isset($data['code'])){
				$subject = "Zoom API response return error code - ".$data['code'];
				$message = $data['message'];
				sendErrorEmail($subject, $message);
			}else{
				if($data['meetings'] && $data['meetings']['0']){
					$title = date('F d, Y'); //February 06, 2021
					$description = $data['meetings']['0']['topic'];
					$recordingCount = count($data['meetings']['0']['recording_files']);
					$recordingData = $data['meetings']['0']['recording_files'];
					for($i=0; $i<$recordingCount; $i++){
						$file_extension = $recordingData[$i]['file_extension'];
						if($file_extension == 'MP4'){
							uploadVideo($title, $description, $recordingData[$i]['download_url'], $accessToken);
						}
					}
				}
			}
		}
	}catch(Exception $err){
		$message = $err->getMessage();
		$line_no = $err->getLine();
		$subject = 'Recording exception occured';
		sendErrorEmail($subject, $message, $line_no, $exceptionCode);
	}	
}

function uploadVideo($title, $description, $videoURL, $accessToken){
	$client_id = 'VIMEO_CLIENT_ID';
	$client_secret = 'VIMEO_CLIENT_SECRET';
	try{
		$client = new \Vimeo\Vimeo($client_id, $client_secret);
		$token = $client->clientCredentials('upload');
		$client->setToken('VIMEO_TOKEN');
		$video_response = $client->request(
			'/me/videos',
			[
				'name' => $title,
				'description' => $description,
				'upload' => [
					'approach' => 'pull',
					'link' => "$videoURL?access_token=$accessToken"
				],
			],
			'POST'
		);
		$subject = 'Call recording uploaded';
		$videoURL = $video_response['body']['link'];
		sendSuccessEmail($subject, $videoURL);
	}catch(Exception $err){
		$message = $err->getMessage();
		$line_no = $err->getLine();
		$subject = 'Video exception occured';
		sendErrorEmail($subject, $message,$exceptionCode);
	}
}

function sendSMS($title){
	try{
		// Your Account SID and Auth Token from twilio.com/console
		$sid = 'TWILIO_SID';
		$token = 'TWILIO_AUTH_TOKEN';
		$client = new Client($sid, $token);

		// Use the client to do fun stuff like send text messages!
		$createSMS = $client->messages->create(
			// the number you'd like to send the message to
			'+11234567890',
			[
				// A Twilio phone number you purchased at twilio.com/console
				'from' => '+11234567890',
				// the body of the text message you'd like to send
				'body' => "Hey there, $title vide uploaded, please post asap."
			]
		);
	}catch(Exception $err){
		$message = $err->getMessage();
		print_r($err);
	}
}

function sendErrorEmail($subject, $msg, $line_no = NULL, $code = NULL){
	$to = "DEVELOPER_EMAIL";
	$message = "
	<html>
		<body>
			<p>Hi there,<br/></p>
			<p>Error code - $code occured while fetching recording from Zoom, please take an immediate action.</p>
			<p>Error message- $msg </p>
			<p>Line number- $line_no <br/><br/></p>
			<p>Thanks</p>
		</body>
	</html>";
	$headers = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
	$headers .= 'From: <noreply@domain.com>' . "\r\n";
	$headers .= 'Cc: developer@gmail.com' . "\r\n";
	mail($to,$subject,$message,$headers);
}

function sendSuccessEmail($subject, $videoURL){
	$to = "DEVELOPER_EMAIL";
	$message = "
	<html>
		<body>
			<p>Hi there,<br/></p>
			<p>Call recording successfully uploaded, currently video is getting transcoded, please check its progress after 3 mins from now. Here is the video link - $videoURL <br/></p>
			<p>Thanks</p>
		</body>
	</html>";
	$headers = "MIME-Version: 1.0" . "\r\n";
	$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
	$headers .= 'From: <noreply@domain.com>' . "\r\n";
	$headers .= 'Cc: developer@gmail.com' . "\r\n";
	mail($to,$subject,$message,$headers);
}
?>