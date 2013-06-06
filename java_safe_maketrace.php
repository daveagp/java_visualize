<?php

/*****************************************************************************

java_visualize/java_safe_maketrace.php: gateway for submitting code to visualize
David Pritchard (daveagp@gmail.com), created May 2013

This file is released under the GNU Affero General Public License, versions 3
or later. See LICENSE or visit: http://www.gnu.org/licenses/agpl.html

See README for documentation.

******************************************************************************/


// report an error which caused the code not to run
function visError($msg, $row, $col, $code) {
  if (!is_int($col))
    $col = 0;
  return '{"trace":[{"line": '.$row.', "event": "uncaught_exception", '
    .'"offset": '.$col.', "exception_msg": '.json_encode($msg).'}],'
    .'"code":'.json_encode($code).'}';
}

function guessClassName($code) {
  // we just do a cheap search for "public class XXXX"
  // let's disallow $ to be overly protective
  $pattern = '|public\\s+class\\s+([a-zA-Z0-9_]+)\\b|';
  $matches = array();
  $result = preg_match($pattern, $code, $matches);
  if ($result === 0) {
    return NULL;
  }
  else {
    return $matches[1];
  }
}

// the main method
function maketrace() {  
  if (!array_key_exists('user_script', $_REQUEST))
    return visError("Error: http mangling? Could not find user_script variable.", 0, 0, "");

  $user_code = $_REQUEST['user_script'];
  $user_stdin = $_REQUEST['user_stdin']; //stdin is not really supported yet for java
  
  $className = guessClassName($user_code); 
  if ($className === NULL) {
    return visError("Error: Make sure your code includes 'public class «ClassName»'", 0, 0, $user_code);
  }

  /*  
   leftover from the cscircles python visualizer
   define("DO_NOT_LOAD_POLYLANG_TEXTDOMAINS", 1);
  require_once("../../../www/wordpress/wp-content/plugins/pybox/include-me-and-load-wp.php");
  $profilingID = beginProfilingEntry(array('activity'=>'visualized-safeexec'));
  */
  
  $descriptorspec = array
    (0 => array("pipe", "r"), 
     1 => array("pipe", "w"),// stdout
     2 => array("pipe", "w"),// stderr
//     3 => array("pipe", "r"),// stdin for visualized program
     );

  $safeexec = "../../safeexec/safeexec"; // an executable
  $java_jail = "../../../java_jail/";    // a directory, with trailing slash

  $command_compile = "$safeexec --chroot_dir $java_jail --exec_dir /scratch --nproc 50 --mem 3000000 --nfile 20 --fsize 10000 --exec /java/bin/javac -g -Xmaxerrs 1 $className.java 2>&1";

  $cp = '"/cp/:/cp/javax.json-1.0.jar:/java/lib/tools.jar"';

  $command_execute = "$safeexec --chroot_dir $java_jail --exec_dir /scratch --nproc 50 --mem 3000000 --nfile 30 --exec /java/bin/java -cp $cp traceprinter.JSONTrace $className";

  system("rm ".$java_jail."scratch/$className.java");
  system("rm ".$java_jail."scratch/*.class");

  file_put_contents($java_jail."scratch/$className.java", $user_code);

  $output = array();
  $return = -1;

  exec($command_compile, $output, $return);
  
  if ($return !== 0) {
    $line1 = $output[0];
    // format is "filename:lineno:msg";
    $colon1 = strpos($line1, ":");
    $colon2 = strpos($line1, ":", $colon1 + 1);
    $lineno = substr($line1, $colon1 + 1, $colon2 - $colon1 - 1);
    $errmsg = substr($line1, $colon2 + 2);
    $columnno = strpos($output[2], "^");
    $i = 3;
    while ($output[$i] != "1 error") {
      $errmsg .= "; \n" . trim($output[$i]);
      $i++;
    }
    return visError($errmsg, $lineno, $columno, $user_code);
  }

  $process = proc_open($command_execute, $descriptorspec, $pipes); //cwd, env not needed
  
  if (!is_resource($process)) return FALSE;
  
  fwrite($pipes[0], $user_code);
  fclose($pipes[0]);
  
  fwrite($pipes[3], $user_stdin);
  fclose($pipes[3]);
  
  $se_stdout = stream_get_contents($pipes[1]); 
  $se_stderr = stream_get_contents($pipes[2]);
  
  fclose($pipes[1]);
  fclose($pipes[2]);
  
  $safeexec_retval = proc_close($process);
  
  //endProfilingEntry($profilingID, array('meta'=>array('retval'=>$safeexec_retval)));

  if ($safeexec_retval === 0) //executed okay
    return '{"trace":'.trim($se_stdout).',"code":'.json_encode($user_code).'}';
  else //this will gum up the javascript, but is convenient to see with browser debugging tools
    return "safeexec did not succeed.\nsafeexec stderr:\n" . $se_stderr . "\nsafeexec stdout:\n" .$se_stdout;
}

header("Content-type: text/plain; charset=iso-8859-1");
echo maketrace();

// end of file.