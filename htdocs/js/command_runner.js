var _request = new RequestHandler();
"use strict";

/* object handeling "Overview" status updates every few seconds */
function CmdRunnerObj( ){


  /* query get_status.php */
  this.read = read;
  function read( ){
      var url = '/tools/command_runner_worker.php';
      var pdata = 'cmd=read'; //post data as string
      var callback;


      /* this will be executed after a successful http request */
      callback = function(ret){
        var ele = document.getElementById('cmd_out');
        if( ele.innerHTML !== ret ){
          ele.innerHTML = '';
          ele.innerHTML = ret;
          ele.scrollTop = ele.scrollHeight;

          if( ret.search('CMDDONE') !== -1 ){
            clearInterval(timr2);
            document.getElementById("btn_exec").disabled = false;
            console.log("ended timr2");
          }
        }
      };

      _request.post( 'cmd_read', url, pdata, callback);
  }


  /* query get_status.php */
  this.cmdexec = cmdexec;
  function cmdexec( cmd ){
      var url = '/tools/command_runner_worker.php';
      var command_exec = document.getElementById('cmdsel').value;
      var pdata = 'cmd=exec&exec=' + command_exec; //post data as string
      var callback;


      /* this will be executed after a successful http request */
      callback = function(ret){
        var ele = document.getElementById('cmd_out');
        if( ele.innerHTML !== ret ){
          ele.innerHTML = '';
          ele.innerHTML = ret;
        }
      };

      _request.post( 'cmd_write', url, pdata, callback);
  }

  }