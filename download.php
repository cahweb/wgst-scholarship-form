<?php
namespace CAH\WGST;

require_once 'class.dot-env-lite.php';
$dotEnv = new \CAH\Util\DotEnvLite(__DIR__);

require_once 'includes/wgst-scholarship-functions.php';

if (!isset($_GET['auth']) || (!isset($_GET['file']) || intval($_GET['file']) < 0 || intval($_GET['file']) > 4) || !isset($_GET['pre'])) {
    http_response_code(400);
    generateErrorPage(400, "The request was missing required query parameters.");
    exit(400);
}

if (ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', 'Off');
}

$date = new \DateTime('now', new \DateTimeZone('America/New_York'));

// Configure mysqli to throw hard errors, for better tracking
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db = null;
$fileInfo = null;
try {
    $db = get_db();

    $user = scrub($_GET['auth'], $db);
    $file = intval($_GET['file']);
    $name = str_replace(['%27', "'"], '', scrub($_GET['pre'], $db));

    // Build the query
    $sql = "SELECT `filename$file` AS `filename`, `size$file` AS `size`, `filetype$file` AS `filetype`, `content$file` AS `content` FROM womenstudies WHERE `guid` = '$user' LIMIT 1";

    // Run the query
    $result = mysqli_query($db, $sql);

    // Send back a 404 if we get nothing
    if ($result instanceof \mysqli_result && $result->num_rows <= 0) {
        http_response_code(404);
        generateErrorPage(404, "The requested file is not in the database.");
        exit(404);
    } elseif (!$result) {
        http_response_code(500);
        generateErrorPage(500, "There was a problem interacting with the database.");
        exit(500);
    }

    $fileInfo = mysqli_fetch_assoc($result);

} catch (\mysqli_sql_exception $e) {
    $msg = "Database Error on Download Attempt: " . $e;
    logError($msg, __DIR__);

} catch (\Exception $e) {
    $msg = "Download error: " . $e;
    logError($msg, __DIR__);

} finally {
    if ($db instanceof \mysqli) {
        mysqli_close($db);
    }
}

extract($fileInfo);

ob_clean();

header("Content-type: $filetype");
header("Content-length: $size");
header("Content-Disposition: attachment; filename=\"$name.$filename\"");

echo $content;

exit();
