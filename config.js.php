<?php
header("Content-type: text/javascript");
echo "// server-specific options to be passed upon initial page load\n";

$config_error = "";
$data = file_get_contents("jv-config.json");
if ($data == FALSE) {
  $config_error = "Couldn't find jv-config.json";
 }
 else {
   $config_jo = json_decode($data, TRUE); // associative array
   if ($config_jo == NULL) {
     $config_error = "jv-config.json is not JSON formatted";
   }
 }

if ($config_error != "") {
  $config_error .= "\\nYou will not be able to submit code.";
  $config_error .= "\\nPlease notify an administratior.";
  echo "setTimeout(function() {alert(\"$config_error\");},1000);\n";
}
else {
  echo "var pytutor_ajax_timeout_millis = " .$config_jo["ajax_timeout_millis"] . ";";
}