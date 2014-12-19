<?php

/*****************************************************************************

java_visualize/java_safe_ram_maketrace.php: gateway for submitting code to visualize
David Pritchard (daveagp@gmail.com), created May 2013

This file is released under the GNU Affero General Public License, versions 3
or later. See LICENSE or visit: http://www.gnu.org/licenses/agpl.html

See README for documentation.

******************************************************************************/

$data = file_get_contents("jv-config.json");
$config_error = "";
if ($data == FALSE) {
  $config_error = "Couldn't find jv-config.json";
 }
 else {
   $config_jo = json_decode($data, TRUE); // associative array
   if ($config_jo == NULL) {
     echo "jv-config.json is not JSON formatted";
   }
   else foreach (array("safeexec-executable-abspath", "java_jail-abspath") as $required) {
     if (!array_key_exists($required, $config_jo)) {
       echo "jv-config.json does not define $required. You cannot submit any code.";
     }
   }      
 }
if ($config_error != "") {
  echo $config_error;
  die();
 }

function config_get($key, $default = NULL) {
  global $config_jo;
  if (array_key_exists($key, $config_jo)) 
    return $config_jo[$key];
  return $default;
}

// if desired, override traceprinter parameters
$visualizer_args = config_get("visualizer_args", array());

// point this at the excutable you built from
// https://github.com/cemc/safeexec
$safeexec = config_get("safeexec-executable-abspath");

$safeexec_args = array(
  // point this at wherever you installed and configured
  // https://github.com/daveagp/java_jail
  "chroot_dir" => config_get("java_jail-abspath"),

  // you may choose to tweak these important performance parameters
  "clock" => config_get("safeexec_wallclock_s", 15),
  "cpu" => config_get("safeexec_cpu_s", 10),
  "mem" => config_get("safeexec_mem_k", 2000000),
                      // use up to 2000000k ~ 2g of memory (YMMV)
                      // counting both VMs and all overhead
                      // see java_jail/cp/traceprinter/MEMORY-NOTES

  // on almost all machines you should not need to edit anything below in this file

  "exec_dir" => "/",  // execute in root of chroot
  "env_vars" => "''", // no env vars
  "nproc" => "50",    // max 50 processes
  "nfile" => "50",    // up to 50 file handles. 
                      // depends on number of files you are including!

  "share_newnet" => "", // don't unshare network: 2 VMs talk over port
);

// allow arbitrary overrides
foreach (config_get("safeexec_args", array()) as $k=>$v)
  $safeexec_args[$k] = $v;

// allow wholesale replacement
$safeexec_args = config_get("safeexec_args_override", $safeexec_args);

// the next two definitions assume things are set up like
// https://github.com/daveagp/java_jail 
$java_in_jail = config_get("java_in_jail", "/java/bin/java");
$java_args = array(
  "Xmx" => "128M", // 128 mb per VM
  "cp" =>  '/cp/:/cp/javax.json-1.0.jar:/java/lib/tools.jar:/cp/visualizer-stdlib'
);

// allow arbitrary overrides
foreach (config_get("java_args", array()) as $k=>$v)
  $java_args[$k] = $v;

// allow wholesale replacement
$java_args = config_get("java_args_override", $java_args);

// safeexec uses --exec, sandbox uses nothing
$safeexec_exec_signal = config_get("safeexec_exec_signal", "--exec");

// now, build the command:
// safeexec --arg1 val1 ... --exec java_in_jail -arg1 val1 ... traceprinter.InMemory
$jv_cmd = $safeexec;
foreach ($safeexec_args as $a=>$v) $jv_cmd .= " --$a $v ";
$jv_cmd .= " $safeexec_exec_signal $java_in_jail";
// -X commands don't use a space
foreach ($java_args as $a=>$v) $jv_cmd .= " " . (substr($a, 0, 1)=='X' ? "-$a$v" : "-$a $v") . " ";
$jv_cmd .= "traceprinter.InMemory";

// necessary for sandbox at princeton
$newdir = config_get("chdir_before_call", ".");
chdir($newdir);

// echo $jv_cmd; // for debugging

// report an error which caused the code not to run
function visError($msg, $row, $col, $code, $se_stdout = null) {
  if (!is_int($col))
    $col = 0;
  if (!is_int($row))
    $row = -1;
  return '{"trace":[{"line": '.$row.', "event": "uncaught_exception", '
    .'"offset": '.$col.', "exception_msg": '.json_encode($msg).'}],'
    .'"code":'.json_encode($code) .
    ($se_stdout === null ? '' : (',"se_stdout":'.json_encode($se_stdout)))
    .'}';
}

// do logging if enabled
function logit($user_code, $internal_error) {

  if (config_get("db_host", "") != ""
      && config_get("db_user", "") != ""
      && config_get("db_database", "") != ""
      && config_get("db_password", "") != "")
    
/* 
At the moment, it's pretty crappy and just logs requests to a 
table 'jv_history' that should have this structure

CREATE TABLE IF NOT EXISTS `jv_history` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 `user_code` varchar(8000) COLLATE utf8_unicode_ci NOT NULL,
 `internal_error` tinyint(1) NOT NULL,  
 PRIMARY KEY (`id`)
)

In the future, it could make sense to cache anything that is not randomized.
*/
    {
      $con = mysqli_connect(config_get("db_host"), config_get("db_user"), 
                            config_get("db_password"), config_get("db_database"));
      $stmt = $con->prepare("INSERT INTO jv_history (user_code, internal_error) VALUES (?, " . ($internal_error?1:0).")");
      $stmt->bind_param("s", $user_code);
      $stmt->execute();
      $stmt->close();
    }
}


// the main method
function maketrace() {  
  if (!array_key_exists('data', $_REQUEST))
    return visError("Error: http mangling? Could not find data variable.", 0, 0, "");

  $data = $_REQUEST['data'];

  if (strlen($data) > config_get("max_request_size_bytes", 10000))
    return visError("Too much code!");
  
  $data = json_decode($data, true); // get assoc. arrays, not objects
  
  $user_code = $data['user_script'];

  $options = $data['options'];
  // sanitize options
  foreach ($options as $k => $v) {
    if ($k == "showStringsAsValues" || $k == "showAllFields") {
      $options[$k] = $options[$k] ? true : false;
    }
    else unset($options[$k]);
  }
  
  $args = $data['args'];
  if (!is_array($args)) 
    return visError("args is not an array");
  if (count($args)>0 && array_keys($args) !== range(0, count($args) - 1)) 
    return visError("wrong args format");
  for ($i=0; $i<count($args); $i++) 
    if (!is_string($args[$i]))
      return visError("wrong arg " + $i + " format");

  $stdin = $data['stdin'];
  if ($stdin != null && !is_string($stdin)) 
    return visError("wrong stdin format");

  $descriptorspec = array
    (0 => array("pipe", "r"), 
     1 => array("pipe", "w"),// stdout
     2 => array("pipe", "w"),// stderr
     );

  $output = array();
  $return = -1;

  global $jv_cmd;
  // use the command
  $process = proc_open($jv_cmd, $descriptorspec, $pipes); //pwd, env not needed
  
  if (!is_resource($process)) return FALSE;
  
  global $visualizer_args;
  if (count($visualizer_args)==0) $visualizer_args = null; // b/c array() is not associative in php!
  $data_to_send = json_encode(array("usercode"=>$user_code, 
                                    "options"=>$options,
                                    "args"=>$args,
                                    "stdin"=>$stdin,
                                    "visualizer_args"=>$visualizer_args));
  
  fwrite($pipes[0], $data_to_send);
  fclose($pipes[0]);
  
  $se_stdout = stream_get_contents($pipes[1]); 
  $se_stderr = stream_get_contents($pipes[2]);
  
  fclose($pipes[1]);
  fclose($pipes[2]);
  
  $safeexec_retval = proc_close($process);

  if ($safeexec_retval === 0 && $se_stdout != "") {  //executed okay
    logit($user_code, false);
    return $se_stdout;
  }

  // there was an error
  logit($user_code, true);

  if ($safeexec_retval === 0)
    return visError("Internal error: safeexec returned 0, but an empty string.\nsafeexec stderr:\n" . $se_stderr, 0, 0, $user_code);

  //this will gum up the javascript, but is convenient to see with browser debugging tools
  return visError("Safeexec did not succeed:\n" . $se_stderr, 0, 0, $user_code, $se_stdout);
}

header("Content-type: text/plain; charset=utf-8");
echo maketrace();

// end of file.