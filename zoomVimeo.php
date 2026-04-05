<?php
error_reporting(E_ALLL);
require('vendor/autoload.php');
// Use the REST API Client to make requests to the Twilio REST API
use Twilio\Rest\Client;
$result=json_decode(file_get_contents('php://input'), true);

if($result['event'] == 'recording.completed'){
	try{
		$accessToken = 'ACCESS_TOKEN';
		$title = date('F d, Y'); //February 06, 2021
		$description = $result['payload']['object']['topic'];
		$recordingCount = count($result['payload']['object']['recording_files']);
		$recordingData = $result['payload']['object']['recording_files'];
		
		//check if its an internal meeting or not
		$word = "- INTERNAL";
		if(strpos($description, $word) !== false){
			//if its an internal meeting then do nothing
		} else{
			//boom
			for($i=0; $i<$recordingCount; $i++){
				$file_extension = $recordingData[$i]['file_type'];
				if($file_extension == 'MP4'){
					uploadVideo($title, $description, $recordingData[$i]['download_url'], $accessToken);
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
			sendSMS($title, $videoURL);
		}catch(Exception $err){
		    print_r($err);
			$message = $err->getMessage();
			$line_no = $err->getLine();
			$subject = 'Video exception occured';
			sendErrorEmail($subject, $message,$exceptionCode);
		}
	}
	function sendSMS($title, $videoURL){
		try{
			$sid = 'TWILIO SID'; // Your Account SID and Auth Token from twilio.com/console
			$token = 'TWILIO_AUTH_TOKEN';
			$client = new Client($sid, $token);
			
			$numbers_in_arrays = explode( ',' , '+11234567890, +11234567890' );

			foreach( $numbers_in_arrays as $number ){
				// Use the client to do fun stuff like send text messages!
				$createSMS = $client->messages->create(
					// the number you'd like to send the message to
					$number,
					[
						// A Twilio phone number you purchased at twilio.com/console
						'from' => '+11234567890', //admin number
						// the body of the text message you'd like to send
						'body' => "Hey there, $title ($videoURL) video uploaded, currently it's getting transcoded please post over MN after 10 minutes."
					]
				);
			}
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
				<p>Hi admin,<br/></p>
				<p>Error code - $code occured while fetching recording from Zoom, please take an immediate action.</p>
				<p>Error message- $msg </p>
				<p>Line number- $line_no <br/><br/></p>
				<p>Thanks</p>
			</body>
		</html>";
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		$headers .= 'From: <support@domain.com>' . "\r\n";
		$headers .= 'Cc: DEVELOPER_EMAIL' . "\r\n";
		mail($to,$subject,$message,$headers);
	}
	function sendSuccessEmail($subject, $videoURL){
		$to = "DEVELOPER_EMAIL";
		$message = "
		<html>
			<body>
				<p>Hi admin,<br/></p>
				<p>Call recording successfully uploaded, currently video is getting transcoded, please check its progress after 3 mins from now. Here is the video link - $videoURL <br/></p>
				<p>Thanks</p>
			</body>
		</html>";
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		$headers .= 'From: <support@domain.com>' . "\r\n";
		$headers .= 'Cc: DEVELOPER_EMAIL' . "\r\n";
		$success =  mail($to,$subject,$message,$headers);
		if($success){
		    echo "sent";
		}else{
	    	echo "not sent";
		}
	}