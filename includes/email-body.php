<?php
$submitDate = new \DateTime('now', new \DateTimeZone('America/New_York'));
?>
<table style="width: 900px; border-collapse: collapse; border: none; font-family: Arial, Helvetica, sans-serif;">
    <tr>
        <td>
            <p>The following application was submitted on <?= date_format($submitDate, "D, M j, Y \\a\\t g:i a T") ?>:</p>
        </td>
    </tr>
    <tr>
        <td>
            <table style="width: 100%; border-collapse: collapse; border: none; font-family: Arial, Helvetica, sans-serif;">
                <tr>
                    <th style="text-align: right; padding-right: 3em; width: 150px;">Name:</th><td><?= "$firstname $lastname" ?></td>
                </tr>
                <tr>
                    <th style="text-align: right; padding-right: 3em; width: 150px;">Email:</th><td><?= $email ?></td>
                </tr>
                <tr>
                    <th style="text-align: right; padding-right: 3em; width: 150px;">UCF ID:</th><td><?= $pid ?></td>
                </tr>
                <tr>
                    <th style="text-align: right; padding-right: 3em; width: 150px;">GPA:</th><td><?= $gpa ?></td>
                </tr>
                <tr>
                    <th style="text-align: right; padding-right: 3em; width: 150px;">Class Year:</th><td><?= $classYear ?></td>
                </tr>
                <tr>
                    <th style="text-align: right; padding-right: 3em; width: 150px;">Scholarships:</th><td><?= implode(',<br />', $selectedScholarships) ?></td>
                </tr>
            <?php if (!empty($fileData)): ?>
                <tr>
                    <th style="text-align: right; padding-right: 3em; width: 150px; vertical-align: top;">Submitted Files:</th>
                    <td>
                    <?php foreach ($fileData as $i => $file) : ?>
                        <a href="<?= $baseurl ?>/download/?auth=<?= $guid ?>&file=<?= $i ?>&pre='<?= "$firstname $lastname" ?>'"><?= $file['filename'] ?></a>
                        <?= $i < count($fileData) - 1 ? "<br />" : "" ?>
                    <?php endforeach; ?>
                    </td>
                </tr>
            <?php endif; ?>
            </table>
        </td>
    </tr>
</table>