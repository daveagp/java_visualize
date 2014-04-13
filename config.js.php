<?php
header("Content-type: text/javascript");
echo "// server-specific options to be passed upon initial page load\n";
echo "var pytutor_ajax_timeout_millis = ";

$timeout_millis = 15000; // default
$config_error = "";
$data = file_get_contents("config.json");
if ($data == FALSE) {
  $config_error = "Couldn't find config.json";
 }
 else {
   $config_jo = json_decode($data, TRUE); // associative array
   if ($config_jo == NULL) {
     $config_error = "config.json is not JSON formatted";
   }
   if (!array_key_exists("ajax_timeout_millis", $config_jo)) {
     $config_error = "config.json does not define ajax_timeout_millis";
   } else {
     $timeout_millis = $config_jo["ajax_timeout_millis"];
   }
 }
echo $timeout_millis . ";";
if ($config_error != "")
  echo "\n// " . $config_error;