<?php

/* This Script was written by Chuk Ekeh */

date_default_timezone_set('Europe/Sofia');

require '/var/www/html/vendor/autoload.php'; // phpseclib required

use phpseclib3\Net\SFTP;

/* ====================== CONFIG ====================== */
// SFTP credentials
$sftpHost  = 'sftp server';  //replace with sftp server hostname or ip
$sftpPort  = 22;
$sftpUser  = 'username'; //replace with sftp username
$sftpPass  = 'password'; // replace with sftp password


$remoteDir = '/tmp/sftp';                  // remote TEMP folder
$ageThresholdSecs = 2 * 60 * 60;       // 2 hours

// Filenames to ignore when deciding to send alerts
$ignoreFiles = [
    'OIC04662.axml',
    'OIC05262.axml',
    'OIC90173.axml',
    'OIC90182.axml'
];

// Email settings
$recipientEmail = 'email'; //replace with email
$subjectPrefix  = '[NOC ALERT] SFTP TEMP stuck files';
$headers  = "Reply-To: Noc <email>\r\n";
$headers .= "Return-Path: Noc <email>\r\n";
$headers .= "From: Noc <email>\r\n";
$headers .= "Organization: Solar\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
$headers .= "X-Priority: 1\r\n";
$headers .= "X-Mailer: PHP". phpversion() ."\r\n";
$recipientEmail1 = 'email';
$body1 = 'Connection to SFTP Failed ';
$body2 = 'Unable to list Directory ';
$subject1 = "SFTP Script Error";
/* ==================================================== */

/* Helper function to convert seconds to human-readable interval */
function humanInterval($seconds) {
    $intervals = [
        'day'    => 86400,
        'hour'   => 3600,
        'minute' => 60,
        'second' => 1
    ];

    foreach ($intervals as $name => $value) {
        if ($seconds >= $value) {
            $count = round($seconds / $value, 1);
            return $count . ' ' . $name . ($count > 1 ? 's' : '');
        }
    }

    return '0 seconds';
}

/* Connect to SFTP */

$sftp = new SFTP ($sftpHost, $sftpPort, 15);
if ($sftp->login($sftpUser, $sftpPass)) {
    echo 'Connection to SFTP successfull <br>';

} else {
    @mail($recipientEmail1, $subject1, $body1, $headers);
    die("ERROR: SFTP login failed for user {$sftpUser}@{$sftpHost}");

}


/* Check the listing in Directory TEMP */

$entries = $sftp->nlist($remoteDir);


if ($entries === false) {
    @mail($recipientEmail1, $subject1, $body2, $headers);
    die("ERROR: Unable to list directory: {$remoteDir}");
}

// Loop through TEMP DIR and check the last modified time

$now = time();

$stuck = [];
foreach ($entries as $name) {
    if ($name === '.' || $name === '..') continue;
    $remotePath = rtrim($remoteDir, '/') . '/' . $name;
    if ($sftp->is_dir($remotePath)) continue;

    $stat = $sftp->stat($remotePath);
    if (!is_array($stat) || !isset($stat['mtime'])) continue;

    $mtime = (int)$stat['mtime'];
    $age = $now - $mtime;
    if ($age >= $ageThresholdSecs) {
        $stuck[] = [
            'name' => $name,
            'mtime' => $mtime,
            'age' => $age
        ];


    }
}

// Filter out ignored filenames from the stuck list
$stuck = array_values(array_filter($stuck, function($item) use ($ignoreFiles) {
    return !in_array($item['name'], $ignoreFiles, true);
}));

// Echo New Files that is stuck for more than 2 hours

if (!empty($stuck)) {
    foreach ($stuck as $item) {
        $mtimeStr = date('Y-m-d H:i:s', $item['mtime']);
        $ageReadable = humanInterval($item['age']);
        $ageHours = round($item['age'] / 3600, 2);
        echo "Stuck file: {$item['name']}, Last Modified: {$mtimeStr}, Age: {$ageReadable} hours<br>";
    }
}



// Send email if any remaining stuck files for than 2 hours

if (!empty($stuck)) {
    $subject = $subjectPrefix . ' - ' . count($stuck) . ' stuck file detected';
    $body = "<html><body>";
    $body .= "<p>Hello Team,</p>";
    $body .= "<p>Please find below the list of files that have been stuck in the /TEMP directory  for over 2 hours.</p>";
    $body .= "<table border='1' cellpadding='5' cellspacing='0'>";
    $body .= "<tr><th>File Name</th><th>Time Stamp</th></tr>";
    foreach ($stuck as $item) {
        $mtimeStr = date('Y-m-d H:i:s', $item['mtime']);
        $ageReadable = humanInterval($item['age']);
        $body .= "<tr>";
        $body .= "<td>{$item['name']}</td>";
        $body .= "<td>{$mtimeStr}</td>";
//        $body .= "<td>{$ageReadable}</td>";
        $body .= "</tr>";
    }
    $body .= "</table>";
    $body .= "<p>Thanks,</p>";
    $body .= "<p>NOC Team</p>";
    $body .= "</body></html>";

    mail($recipientEmail, $subject, $body, $headers);
} else {
    echo "No actionable stuck files after ignoring known filenames; no email sent.<br>";
}


?>
