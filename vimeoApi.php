<?php
require('vendor/autoload.php');
$client_id = 'VIMEO_CLIENT_ID';
$client_secret = 'VIMEO_CLIENT_SECRET';
$client = new \Vimeo\Vimeo($client_id, $client_secret);
$token = $client->clientCredentials('upload');
$client->setToken('VIMEO_TOKEN');
$video_response = $client->request(
    '/me/videos',
    [
		'name' => 'API Video',
		'description' => 'API Video',
        'upload' => [
            'approach' => 'pull',
            'link' => 'https://us02web.zoom.us/rec/download/VIMEO_LINK?access_token=VIMEO_ACCESS_TOKEN'
        ],
    ],
    'POST'
);
echo "<pre>"; 
print_r($video_response); 
echo "</pre>";