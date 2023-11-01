# UCF Women's and Gender Studies Scholarship Application Form

This form enables students to apply for scholarships being offered through the UCF College of Arts & Humanities' Women's and Gender Studies Program. The emails to students and faculty/staff upon successful submission also include download links for the students' submitted files, which this application also supports

## Installation and setup

Clone this repository, copy `.env.example`, fill out the values (including relabeling the `ENV` value to `production`, if appropriate), and rename it to `.env`. That should be all that's required.

**Note:** This assumes that reCAPTCHA and PHPMailer support exists already on the server in question, through the `include_dir` setting in `php.ini`, as well as the custom PHP environment loader, `\CAH\Util\DotEnvLite`.