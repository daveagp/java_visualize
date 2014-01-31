<?php

/*****************************************************************************

java_visualize/java_safe_maketrace.php: gateway for submitting code to visualize
David Pritchard (daveagp@gmail.com), created May 2013

This file is released under the GNU Affero General Public License, versions 3
or later. See LICENSE or visit: http://www.gnu.org/licenses/agpl.html

See README for documentation.

******************************************************************************/


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
  require_once('.dbcfg.php');
  $con = mysqli_connect(JV_HOST, JV_USER, JV_PWD, JV_DB);
  $stmt = $con->prepare("INSERT INTO jv_history (user_code, internal_error) VALUES (?, " . ($internal_error?1:0).")");
  $stmt->bind_param("s", $user_code);
  $stmt->execute();
  $stmt->close();
}


// the main method
function maketrace() {  
  if (!array_key_exists('data', $_REQUEST))
    return visError("Error: http mangling? Could not find data variable.", 0, 0, "");

  $data = $_REQUEST['data'];
  //$user_stdin = $_REQUEST['user_stdin']; //stdin is not really supported yet for java
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
//     3 => array("pipe", "r"),// stdin for visualized program
     );

  define ('JV_PRINCETON', substr($_SERVER['SERVER_NAME'], -13)=='princeton.edu');
  if (JV_PRINCETON) {
    
    $safeexec  = "/n/fs/htdocs/cos126/safeexec/safeexec"; // an executable
    $java_jail = "/n/fs/htdocs/cos126/java_jail/";    // a directory, with trailing slash
    $inc = "-i $safeexec -i /n/fs/htdocs/cos126/java_jail/cp -i /etc/alternatives/java_sdk_1.7.0/lib/tools.jar";
    
    $cp = 'cp/:cp/javax.json-1.0.jar:cp/stdlib:/etc/alternatives/java_sdk_1.7.0/lib/tools.jar';
    
    $java = '/etc/alternatives/java_sdk_1.7.0/bin/java';
    
    // clear out the environment variables in the safeexec call.
    // note: -cp would override CLASSPATH if it were set
    chdir($java_jail);
    $command_execute = "sandbox $inc $safeexec --nproc 500 --mem 3000000 --nfile 30 --clock 15 --exec $java -Xmx400M -cp $cp traceprinter.InMemory";

  }
  else {
    $safeexec = "../../safeexec/safeexec"; // an executable
    if (substr($_SERVER['REQUEST_URI'], 0, 5)=='/dev/')
      $java_jail = "../../../dev_java_jail/";    // a directory, with trailing slash
    else
      $java_jail = "../../../java_jail/";    // a directory, with trailing slash
    $cp = '/cp/:/cp/javax.json-1.0.jar:/java/lib/tools.jar:/cp/stdlib';
    $command_execute = "$safeexec --chroot_dir $java_jail --exec_dir / --env_vars '' --nproc 50 --mem 500000 --nfile 30 --clock 5 --exec /java/bin/java -Xmx128M -cp $cp traceprinter.InMemory";
  }

  $output = array();
  $return = -1;

  $process = proc_open($command_execute, $descriptorspec, $pipes); //cwd, env not needed
  
  if (!is_resource($process)) return FALSE;

  $data_to_send = json_encode(array("usercode"=>$user_code, 
                                    "options"=>$options,
                                    "args"=>$args,
                                    "stdin"=>$stdin));
  
  fwrite($pipes[0], $data_to_send);
  fclose($pipes[0]);
  
  //  fwrite($pipes[3], $user_stdin);
  //  fclose($pipes[3]);
  
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