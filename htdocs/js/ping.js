var _request = new RequestHandler();
"use strict";

/* object handeling "Overview" status updates every few seconds */
function PingObj( ){


  /* query get_status.php */
  this.read = read;
  function read( ){
      var url = './ping_worker.php';
      var pdata = 'cmd=read'; //post data as string
      var callback;


      /* this will be executed after a successful http request */
      callback = function(ret){
        var ele = document.getElementById('ping_out');
        if( ele.innerHTML !== ret ){
          ele.innerHTML = '';
          ele.innerHTML = ret;
          document.getElementById("btn_ping").disabled = false;
          clearInterval(timr2);
          console.log("ended timr2");
        }
      };

      _request.post( 'ping_read', url, pdata, callback);
  }


  /* query get_status.php */
  this.ping = ping;
  function ping( host_ele ){
      var url = './ping_worker.php';
      var host = document.getElementById(host_ele).value;
      var se = document.getElementById('ping_if');
      var intif = se.options[se.selectedIndex].value;
      var pdata = 'cmd=ping&IP=' + host + '&IF=' + intif; //post data as string
      var callback;


      /* this will be executed after a successful http request */
      callback = function(ret){
        var ele = document.getElementById('ping_out');
        if( ele.innerHTML !== ret ){
          ele.innerHTML = '';
          ele.innerHTML = ret;
        }
      };

      _request.post( 'ping_write', url, pdata, callback);
  }

  }