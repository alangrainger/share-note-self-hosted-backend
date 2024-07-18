<?php
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

// File
$f3->route('POST /v1/file/check-files', 'File->checkFiles');

// Finished, launch
$f3->run();
