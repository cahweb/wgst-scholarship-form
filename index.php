<?php
namespace CAH\WGST;

// Requires
//require_once "recaptcha.php";
require_once "class.dot-env.php";

require_once "class.phpmailer.php";
//require_once "class.smtp.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Includes
require_once "includes/wgst-scholarship-functions.php";
require_once "includes/class.formInput.php";
require_once "includes/class.fileInput.php";
require_once "includes/enum.exception-codes.php";

// Load the environment variables
$dotEnv = new \CAH\Util\DotEnv(__DIR__);

// Make sure we don't spam people with emails during development
define('IS_DEV', getenv('ENV') != 'production');

$errorLog = "./wgst-scholarship-error.log";

// Get the form values and parse them
$formJSON = json_decode(file_get_contents('lib/form-values.json'), true);
$generalInfo = $formJSON['generalInfo'];
$fileInputs = $formJSON['fileInputs'];
unset($formJSON);

// Get the base URL
$baseurl = getenv('BASEURL');

// Initialize processing variables
$messages = [];
$databaseSuccess = false;
$emailSuccess = false;

// Process form submission
if (isset($_POST['submit'])) {
    // Throw errors when MySQL messes up
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // Create database connection
    $db = null;
    try {
        $db = get_db();

        // Validate inputs

        // Loop through the input names and assign them to variables
        foreach(range(0, count($generalInfo) - 1) as $i) {
            $name = $generalInfo[$i]['name'];
            if (isset($_POST[$name])) {
                $$name = !is_numeric($_POST[$name]) && is_string($_POST[$name])
                    ? scrub($_POST[$name], $db)
                    : $_POST[$name];
            }
        }

        // The scholarships are neither numbers nor strings, so we'll handle them here
        $selectedScholarships = $_POST['scholarships'] ?? [];

        if (empty($selectedScholarships)) {
            $messages[] = [
                'msg'  => 'You must select at least one scholarship to apply for.',
                'type' => 'danger',
            ];
        }

        // Get the file data
        $fileData = [];
        foreach (['resume', 'personalStatement'] as $file) {
            if (!isset($_FILES[$file]) || empty($_FILES[$file]['name'])) {
                continue;
            }

            $fh = fopen($_FILES[$file]['tmp_name'], 'r');

            $fileData[] = [
                'filename'    => $_FILES[$file]['name'],
                'size'    => $_FILES[$file]['size'],
                'filetype'    => $_FILES[$file]['type'],
                'content' => addslashes(fread($fh, $_FILES[$file]['size'])),
            ];

            fclose($fh);
        }

        // Get the unique identifier that we'll use to generate their download links
        $guid = getNewGuid();

        // Assemble SQL query
        if (empty($messages)) {
            $columns = [
                'guid',
                'fname',
                'lname',
                'email',
                'pid',
                'gpa',
                'year',
                'scholarships',
            ];

            // We only want to submit information to file fields if we have a file
            // for that field, so we'll create the columns dynamically
            $fileColumns = [];
            for ($i = 0; $i < count($fileData); $i++) {
                $keys = array_keys($fileData[$i]);
                foreach ($keys as $key) {
                    $fileColumns[] = "$key$i";
                }
            }

            // Include the files, if there are any, and the final column, `submitted`
            $columnStr = implode(', ', $columns);
            $columnStr .= (!empty($fileColumns) ? ', ' . implode(', ', $fileColumns) : '') . ', submitted';
            $columnStr = "($columnStr)";

            // Put together most of the query, lacking only the file data
            $sql = "INSERT INTO womenstudies $columnStr VALUES ('$guid', '$firstname', '$lastname', '$email', '$pid', '$gpa', '$classYear', '" . (implode(', ', $selectedScholarships) . "'");

            // Loop through the files and add the data, matching the fields we created
            for ($i = 0; $i < count($fileData); $i++) {
                $sql .= ", '{$fileData[$i]['filename']}', '{$fileData[$i]['size']}', '{$fileData[$i]['filetype']}', '{$fileData[$i]['content']}'";
            }

            // Finish off by calling the function for the `submitted` column
            $sql .= ", NOW())";

            // Run the query
            $databaseSuccess = mysqli_query($db, $sql);
            // INSERT queries return `true` on success and `false` on failure
            if (!$databaseSuccess) {
                throw new \Exception('MySQL INSERT query failed.', ExceptionCode::DATABASE);
            }

            // Send the confirmation email
            $mail = new PHPMailer();
            $mail->IsSMTP();
            $mail->IsHTML(true);
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = false;
            $mail->Host = getenv('EMAIL_HOST');
            $mail->Port = getenv('EMAIL_PORT');
            $mail->Username = getenv('EMAIL_USER');
            $mail->Password = getenv('EMAIL_PASS');
            $mail->CharSet = 'utf-8';

            $mail->SetFrom('womenst@ucf.edu', "UCF Women's and Gender Studies Program");
            $mail->AddAddress($email, "$firstname $lastname");
            
            // Only add the other stakeholders' email addresses if we're in production
            if (!IS_DEV) {
                $mail->AddAddress('cahweb@ucf.edu', 'CAH Web');
                $faculty = json_decode(file_get_contents("lib/current-faculty.json"), true)['faculty'];
                foreach ($faculty as $info) {
                    $mail->AddAddress($info['email'], $info['name']);
                }
            }

            $mail->Subject = "$firstname $lastname - " . (stripslashes(implode(', ', $selectedScholarships))) . " Application";

            // Get the body of the email from an external file, to cut down on
            // clutter
            $emailBody = '';
            ob_start();
            include 'includes/email-body.php';
            $emailBody = ob_get_clean();
            $mail->Body = "<body>$emailBody</body>";

            // Try to send the email, and error if it fails
            if (!$mail->Send()) {
                throw new \Exception("The email failed to send. Applicant GUID: $guid", ExceptionCode::EMAIL);
            }

            // Everything worked properly
            $messages[] = [
                'msg'  => 'Your scholarship application was submitted successfully!',
                'type' => 'success',
            ];
            $emailSuccess = true;
        }

    } catch (\Exception $e) { // Error handling
        $date = new \DateTime('now', new \DateTimeZone('America/New_York'));
        $msg = date_format($date, "[Y-m-d H:i:s T]") . " $e\n";
        logError($msg, __DIR__);

        $msg = '';
        $type = '';

        // Set our message to the user based on the error code we defined
        // with the thrown exception, if any
        switch ($e->getCode()) {
            case ExceptionCode::EMAIL:
                $msg = 'The confirmation email failed to send, but your information was successfully entered into the database. If you require a confirmation email for your records, please contact <a href="mailto:cahweb@ucf.edu">CAH Web</a>';
                $type = 'info';
                break;

            case ExceptionCode::DATABASE:
            default:
                $msg = 'Problem communicating with the database. If this issue persists, please contact the <a href="mailto:cahweb@ucf.edu">CAH Web Team</a>';
                $type = 'danger';
                break;
        }

        // Set the message we want to pass to the user, if any
        if (!empty($msg)) {
            $messages[] = [
                'msg'  => $msg,
                'type' => $type,
            ];
        }

    } finally {
        // Clean up after ourselves
        if ($db instanceof \mysqli) {
            mysqli_close($db);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Scholarships Form | UCF Women&apos;s and Gender Studies</title>

        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

        <!-- Pegasus favicon -->
        <link rel="icon" href="https://www.ucf.edu/wp-content/blogs.dir/13/files/2017/02/cropped-Favicon-ucf-512x512-32x32.png" sizes="32x32">
        <link rel="icon" href="https://www.ucf.edu/wp-content/blogs.dir/13/files/2017/02/cropped-Favicon-ucf-512x512-192x192.png" sizes="192x192">
        <link rel="apple-touch-icon" href="https://www.ucf.edu/wp-content/blogs.dir/13/files/2017/02/cropped-Favicon-ucf-512x512-180x180.png">

        <!-- Athena CSS -->
        <link rel="stylesheet" href="https://cdn.ucf.edu/athena-framework/v1.2.0/css/framework.min.css" />

        <!-- Page styles -->
        <link rel="stylesheet" href="css/style.css" />

        <!-- reCAPTCHA -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    </head>

    <body>
        <header>
            <!-- UCF Header Bar -->
            <script type="text/javascript" id="ucfhb-script" src="//universityheader.ucf.edu/bar/js/university-header.js?use-1200-breakpoint=1"></script>

            <nav class="navbar navbar-default bg-default mb-4" role="navigation">
                <div class="container">
                    <a class="navbar-brand" style="color: inherit; text-decoration: none;" href="<?= getenv('PROGRAM_URL') ?>">Women's and Gender Studies</a>
                </div>
            </nav>
            <div class="container">
                <h1>Scholarship Submission Form</h1>
            </div>
        </header>

        <main class="container mt-5 mb-4">
        <?php if (!empty($messages)) : ?>
            <?php foreach ($messages as $error) : ?>
                <div class="alert alert-<?= $error['type'] ?>" role="alert">
                    <p class="alert-text"><?= $error['msg'] ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!isset($_POST['submit']) || !$databaseSuccess) : ?>
            <form id="scholarshipForm" method="post" enctype="multipart/form-data">
                <h2>General Information</h2>
                <p class="form-text"><em>All fields are required.</em></p>
                <?php
                // Here we're assigning values per form input, and passing those
                // values to a `\CAH\WGST\FormInput` object which we echo to
                // create the HTML
                foreach ($generalInfo as $input) {
                    $objProps = [
                        'name',
                        'type',
                        'label',
                        'required',
                        'formText',
                        'options',
                    ];

                    $name            = "";
                    $type            = "";
                    $label           = "";
                    $required        = false;
                    $formText        = "";
                    $options         = [];
                    $additionalAttrs = [];

                    foreach ($input as $key => $value) {
                        if (in_array($key, $objProps)) {
                            $$key = $value;
                        } else {
                            // Anything that might change from input to input
                            // is gathered here, and handled within the
                            // FormInput object itself
                            $additionalAttrs[$key] = $value;
                        }
                    }

                    echo new FormInput(
                        $name,
                        $type,
                        $label,
                        isset($$name) ? $$name : null,
                        $options,
                        $required,
                        $formText,
                        $additionalAttrs
                    );
                }

                // Get the scholarship data via cURL request
                $ch = curl_init(getenv('WGST_REST_URL'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                // Read the JSON payload
                $scholarships = json_decode(curl_exec($ch), true);
                curl_close($ch);

                // Display the scholarships as checkboxes
                ?>
                <h2 class="mt-4">Scholarships</h2>
                <p class="form-text">Select one or more scholarships you wish to apply for. Please ensure you qualify for the scholarship(s) before submitting your application.</p>
                <?php if (is_array($scholarships) && !empty($scholarships)) : ?>
                <table class="table table-responsive" style="border-collapse: collapse; border: none;">
                    <?php foreach ($scholarships as $scholarship) : ?>
                    <tr>
                        <td>
                            <div class="form-check">
                                <label class="form-check-label">
                                    <input class="form-check-input" type="checkbox" name="scholarships[]" value="<?= $scholarship['name'] ?>">
                                    <?= $scholarship['name'] ?>
                                </label>
                            </div>
                        </td>
                        <td>
                            (Deadline: <?= date_format(date_create_from_format("Y-m-d", $scholarship['deadline'], new \DateTimeZone('America/New_York')), "M j, Y"); ?>)
                        </td>
                        <td>
                            <a href="<?= $scholarship['permalink'] ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener">View Requirements</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else : ?>
                <p class="form-text text-muted"><em>No scholarships are open for application at this time.</em></p>
                <?php endif; ?>
                <h2 class="mt-4">Supporting Documents &amp; Attachments</h2>
                <p class="form-text">Please attach any necessary documentation for the scholarship(s) you have chosen.</p>
                <ul class="list-unstyled mb-3">
                <?php
                // File attachments. Similar deal to the "General Info" section, above
                foreach ($fileInputs as $input) {
                    $attrNames = [
                        'name',
                        'label',
                        'isMulti',
                        'accept',
                        'formText',
                        'required',
                    ];

                    $name = '';
                    $label = '';
                    $isMulti = false;
                    $accept = '';
                    $formText = null;
                    $required = false;

                    foreach ($attrNames as $attr) {
                        if (isset($input[$attr])) {
                            $$attr = $input[$attr];
                        }
                    }
                    ?>

                    <li class="container">
                        <?= new FileInput($name, $label, $isMulti, $accept, $required, $formText) ?>
                    </li>
                    <?php
                }
                ?>
                </ul>
                <p class="form-text">If all the information you've entered is correct, please press "Submit," below.</p>
                <div class="g-recaptcha mt-4 mb-3" data-sitekey="<?= $siteKey ?>"></div>
                <button type="submit" name="submit" class="btn btn-primary btn-lg">Submit</button>
                <button type="reset" class="btn btn-primary btn-lg">Reset</button>
            </form>
        <?php endif; ?>
        </main>

        <footer>
            <div class="bg-default py-5<?= isset($_POST['submit']) && $databaseSuccess ? "position-absolute-bottom" : "" ?>">
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
            </div>
        </footer>

        <!-- Athena JS -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha384-vtXRMe3mGCbOeY7l30aIg8H9p3GdeSe4IFlP6G8JMa7o7lXvnz3GFKzPxzJdPfGK" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.7/js/tether.min.js" integrity="sha384-CPFFlrdhp9pDDCqRqdTKNZ5oAHsnfnvHGt0CsRIeL5Gc7V/OkP1Y4w8zTxlV3mat" crossorigin="anonymous"></script>
        <script src="https://cdn.ucf.edu/athena-framework/v1.2.0/js/framework.min.js"></script>

        <!-- Form UI JS -->
        <script src="js/index.js"></script>
    </body>
</html>
