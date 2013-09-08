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

// the main method
function maketrace() {  
  if (!array_key_exists('user_script', $_REQUEST))
    return visError("Error: http mangling? Could not find user_script variable.", 0, 0, "");

  $user_code = $_REQUEST['user_script'];
  //$user_stdin = $_REQUEST['user_stdin']; //stdin is not really supported yet for java
  
  $descriptorspec = array
    (0 => array("pipe", "r"), 
     1 => array("pipe", "w"),// stdout
     2 => array("pipe", "w"),// stderr
//     3 => array("pipe", "r"),// stdin for visualized program
     );

  $safeexec  = "/n/fs/htdocs/cos126/safeexec/safeexec"; // an executable
  $java_jail = "/n/fs/htdocs/cos126/java_jail/";    // a directory, with trailing slash
  $inc = "-i $safeexec -i /n/fs/htdocs/cos126/java_jail/cp -i /etc/alternatives/java_sdk_1.7.0/lib/tools.jar";

  $cp = 'cp/:cp/javax.json-1.0.jar:/etc/alternatives/java_sdk_1.7.0/lib/tools.jar';

  $java = '/etc/alternatives/java_sdk_1.7.0/bin/java';
  
  // clear out the environment variables in the safeexec call. 
  // note: -cp would override CLASSPATH if it were set
  chdir($java_jail);
  $command_execute = "sandbox $inc $safeexec --nproc 50 --mem 3000000 --nfile 30 --clock 5 --exec $java -Xmx400M -cp $cp traceprinter.InMemory";

  $output = array();
  $return = -1;

  $process = proc_open($command_execute, $descriptorspec, $pipes); //cwd, env not needed
  
  if (!is_resource($process)) return FALSE;
  
  fwrite($pipes[0], $user_code);
  fclose($pipes[0]);
  
  //  fwrite($pipes[3], $user_stdin);
  //  fclose($pipes[3]);
  
  $se_stdout = stream_get_contents($pipes[1]); 
  $se_stderr = stream_get_contents($pipes[2]);
  
  fclose($pipes[1]);
  fclose($pipes[2]);
  
  $safeexec_retval = proc_close($process);
  
  if ($safeexec_retval === 0) //executed okay
    {
      if ($se_stdout != "")
        return $se_stdout;
      else 
        return visError("Internal error: safeexec returned 0, but an empty string.\nsafeexec stderr:\n" . $se_stderr, 0, 0, $user_code);
    }
  else //this will gum up the javascript, but is convenient to see with browser debugging tools
    return visError("Safeexec did not succeed:\n" . $se_stderr, 0, 0, $user_code, $se_stdout);
}

header("Content-type: text/plain; charset=utf-8");
echo maketrace();

// end of file.