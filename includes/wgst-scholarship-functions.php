<?php
namespace CAH\WGST;


/**
 * Prepares a string for entry into an SQL query
 *
 * Runs a submitted string through a few functions intended to avoid
 * `INSERT` errors and SQL injection attacks.
 *
 * @param string $value The user-submitted string
 * @param \mysqli $db The MySQL connection object
 *
 * @return string
 */
function scrub($value, $db) : string
{
    return mysqli_real_escape_string($db, htmlentities($value));
}


/**
 * Creates a unique identifier for each user
 *
 * This will be used as a unique identifier when users attempt to
 * download submitted files
 *
 * @return string
 */
function getNewGuid() : string
{
    return str_replace(['{', '}', '-'], '', com_create_guid());
}


/**
 * Creates a database connection from pre-loaded environment variables
 *
 * This requires the appropriate values to have been loaded already (*e.g.*,
 * via the `\CAH\Util\DotEnvLite` class). If `mysqli_report()` has been set to
 * throw errors, this will do so on a connection error.
 *
 * @return \mysqli|null
 */
function get_db() : ?\mysqli
{
    $host    = getenv('DB_HOST');
    $user    = getenv('DB_USER');
    $pass    = getenv('DB_PASS');
    $db      = getenv('DB');
    $charset = getenv('DB_CHARSET');

    $db = mysqli_connect($host, $user, $pass, $db);
    mysqli_set_charset($db, $charset);

    return $db;
}


/**
 * Writes an error to a local error log, for ease of tracking
 *
 * Errors in this application *should* be few and far between, but
 * this will make it easier to track them down if and when they happen.
 *
 * @param string $msg The error message to write to the log
 * @param string $baseDir The directory in which to write the log
 *
 * @return void
 */
function logError(string $msg, $baseDir) : void
{
    $logFile = "$baseDir/wgst-scholarship-error.log";
    $date = new \DateTime('now', new \DateTimeZone('America/New_York'));
    error_log(date_format($date, "[Y-m-d H:i:s T]") . " $msg\n", 3, $logFile);
}


/**
 * Creates a bare-bones error page for HTTP error responses
 *
 * In the event of an error on the download page, this generates
 * an HTML page to let the user know, in general terms, why the
 * attempt failed.
 *
 * 
 */
function generateErrorPage(int $responseCode, string $msg)
{
    $codeLookup = [
        400 => "Bad Request",
        404 => "Not Found",
        500 => "Internal Server Error",
    ];

    $title = "$responseCode {$codeLookup[$responseCode]}";

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
        <head>
            <title><?= $title ?></title>

            <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

            <!-- Pegasus favicon -->
            <link rel="icon" href="https://www.ucf.edu/wp-content/blogs.dir/13/files/2017/02/cropped-Favicon-ucf-512x512-32x32.png" sizes="32x32">
            <link rel="icon" href="https://www.ucf.edu/wp-content/blogs.dir/13/files/2017/02/cropped-Favicon-ucf-512x512-192x192.png" sizes="192x192">
            <link rel="apple-touch-icon" href="https://www.ucf.edu/wp-content/blogs.dir/13/files/2017/02/cropped-Favicon-ucf-512x512-180x180.png">

            <!-- Athena CSS -->
            <link rel="stylesheet" href="https://cdn.ucf.edu/athena-framework/v1.2.0/css/framework.min.css" />
        </head>
        <body>
            <header>
                <!-- UCF Header Bar -->
                <script type="text/javascript" id="ucfhb-script" src="//universityheader.ucf.edu/bar/js/university-header.js?use-1200-breakpoint=1"></script>

                <nav class="navbar navbar-default bg-default mb-4" role="navigation">
                    <div class="container">
                        <a class="navbar-brand" style="color: inherit; text-decoration: none;" href="<?= getenv('BASEURL') ?>">Women's and Gender Studies</a>
                    </div>
                </nav>
                <div class="container">
                    <h1 class="font-condensed mt-5"><?= $title ?></h1>
                </div>
            </header>
            <main class="container mt-5 mb-4">
                <p class="h4"><?= $msg ?></p>
                <p>If this problem persists, please contact the <a href="mailto:cahweb@ucf.edu">CAH Web Team</a>.</p>
            </main>
            <footer class="position-absolute-bottom bg-default py-5">
                <div class="container">
                    <div class="row">
                        <div class="col-md-6">
                            <a class="text-inverse text-decoration-none h5" href="<?= getenv('PROGRAM_URL') ?>">Women's and Gender Studies at UCF</a>
                        </div>
                        <div class="col-md-6">
                            <p class="h5 heading-underline">Contact Us</p>
                            <table>
                                <tr>
                                    <th class="pr-3 px-2">Email: </th>
                                    <td><a class="text-inverse text-decoration-none" href="mailto:wgst@ucf.edu">wgst@ucf.edu</a></td>
                                </tr>
                                <tr>
                                    <th class="pr-3 px-2">Phone: </th>
                                    <td><a class="text-inverse text-decoration-none" href="tel:+14078236502">(407) 823-6502</a></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </footer>
        </body>
    </html>
    <?php
    echo ob_get_clean();
    exit($responseCode);
}
