<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');

$f3 = require('f3/base.php');
$f3->config('env.cfg');
$f3->set('AUTOLOAD', 'classes/');
$f3->set('DEBUG', 1);

// Output no errors in production
if ($f3->get('production')) {
    $f3->set('ONERROR',
        function () {
        }
    );
}

// Routes

$f3->route('GET /v1/test', function () {
	error_log('some message');
});

$f3->route('POST /v1/file/check-files', 'File->checkFiles');
$f3->route('POST /v1/file/upload', 'File->upload');
$f3->route('POST /v1/file/create-note', 'File->createNote');

// Finished, launch
$f3->run();
