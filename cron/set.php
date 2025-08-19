<?php


$id = "8cb63238-cb76-11ef-871c-00155d400402";
$datetime = "2025-02-06T10:00:00+03:00";
$date = '06/02/2025';
$time = '10:00';


$taskName = "PlanetaFitness" . time();
$phpPath = "C:\\xampp\\php\\php.exe";
$scriptPath = "C:\\Users\\rahim\\PhpstormProjects\\fitness_scheduler\\cron\\make_reserve.php";
$arguments = $id;

$command = "schtasks /create /tn \"$taskName\" /tr \"$phpPath $scriptPath $arguments\" /sc once /st $time /sd $date";

exec($command, $output, $returnVar);