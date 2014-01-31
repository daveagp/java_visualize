<?php

/*****************************************************************************

java_visualize/java_safe_ram_maketrace.php: gateway for submitting code to visualize
David Pritchard (daveagp@gmail.com), created May 2013

This file is released under the GNU Affero General Public License, versions 3
or later. See LICENSE or visit: http://www.gnu.org/licenses/agpl.html

See README for documentation.

******************************************************************************/

global $safeexec, $safeexec_args, $java_in_jail, $java_args; 

// point this at the excutable you built from
// https://github.com/cemc/safeexec
$safeexec = "../../safeexec/safeexec";   // EDIT THIS

$safeexec_args = array(
  // point this at wherever you installed and configured
  // https://github.com/daveagp/java_jail
  "chroot_dir" => "../../java_jail/",    // EDIT THIS

  // you may choose to tweak these important performance parameters
  "clock" => "15",    // up to 15s of wall time
  "cpu" => "10",      // up to 10s of cpu time
  "mem" => "1000000", // use up to 1000000k ~ 1g of memory (YMMV)
                      // counting both VMs and all overhead
                      // see java_jail/cp/traceprinter/MEMORY-NOTES

  // on almost all machines you should not need to edit anything below in this file

  "exec_dir" => "/",  // execute in root of chroot
  "env_vars" => "''", // no env vars
  "nproc" => "50",    // max 50 processes
  "nfile" => "30",    // up to 30 file handles
);

// the next two definitions assume things are set up like
// https://github.com/daveagp/java_jail 
$java_in_jail = '/java/bin/java';
$java_args = array(
  "Xmx128M" => "", // 128 mb per VM
  "cp" =>      '/cp/:/cp/javax.json-1.0.jar:/java/lib/tools.jar:/cp/stdlib'
);

// this is to override the definitions above in a git-friendly way
if (file_exists('.cfg.php')) 
  require_once('.cfg.php');

/* e.g. my .cfg.php looks like this:
<?php
global $safeexec, $safeexec_args;
$safeexec = "../safeexec/safeexec";  
$safeexec_args['chroot_dir'] = "../java_jail/";        
*/

// now, build the command
global $jv_cmd;
$jv_cmd = $safeexec;
foreach ($safeexec_args as $a=>$v) $jv_cmd .= " --$a $v ";
$jv_cmd .= "--exec $java_in_jail";
foreach ($java_args as $a=>$v) $jv_cmd .= " -$a $v ";
$jv_cmd .= "traceprinter.InMemory";

// echo $jv_cmd; // for debugging

/* To enable logging, create a file called .dbcfg.php that looks like this:

<?php
define('JV_HOST', "your database host here (could be localhost)");
define('JV_USER', "your database user here");
define('JV_DB', "your database db here");
define('JV_PWD', "your database password here");

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

// report an error which caused the code not to run
function visError($msg, $row, $col, $code, $se_stdout = null) {
  if (!is_int($col))
    $col = 0;
  return '{"trace":[{"line": '.$row.', "event": "uncaught_exception", '
    .'"offset": '.$col.', "exception_msg": '.json_encode($msg).'}],'
    .'"code":'.json_encode($code) .
    ($se_stdout === null ? '' : (',"se_stdout":'.json_encode($se_stdout)))
    .'}';
}

function logit($user_code, $internal_error) {
  if (file_exists('.dbcfg.php')) {
    require_once('.dbcfg.php');
    $con = mysqli_connect(JV_HOST, JV_USER, JV_PWD, JV_DB);
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

  if (strlen($data) > 10000)
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

  // use the command
  global $jv_cmd;
  $process = proc_open($jv_cmd, $descriptorspec, $pipes); //pwd, env not needed
  
  if (!is_resource($process)) return FALSE;

  $data_to_send = json_encode(array("usercode"=>$user_code, 
                                    "options"=>$options,
                                    "args"=>$args,
                                    "stdin"=>$stdin));
  
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