<?php
header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Headers: *' );

$f3 = require( 'f3/base.php' );
$f3->config( 'env.cfg' );
$f3->set( 'AUTOLOAD', 'classes/' );
$f3->set( 'DEBUG', 0 );

// Output no errors in production
if ( $f3->get( 'production' ) ) {
	$f3->set( 'ONERROR',
		function () {
		}
	);
}

// Routes

$f3->route( 'POST /v1/file/check-files', 'File->checkFiles' );
$f3->route( 'POST /v1/file/upload', 'File->upload' );
$f3->route( 'POST /v1/file/create-note', 'File->createNote' );

// User routes are not used in the self-hosted server.
// Please manually set the `uid` and `private_key` in your server env.cfg,
// and `uid` and `apiKey` in your plugin data.json file.

$f3->route( 'GET /v1/account/get-key', function () {
	echo '<h1>Setup needed</h1>' .
	     'Please <a href="https://github.com/alangrainger/share-note-self-hosted-backend">follow the instructions in the documentation</a> ' .
	     'to add a uid and private_key / apiKey in both your server <code>env.cfg</code> and plugin <code>data.json</code> files.';
	die();
} );

// Finished, launch
$f3->run();
