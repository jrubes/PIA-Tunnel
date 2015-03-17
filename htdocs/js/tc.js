var _request = new RequestHandler();
"use strict";

/* object handeling tc */
function TCObj( ){


  /* query get_status.php */
  this.read = read;
  function read( ){
      var url = '/tools/ping_worker.php';
      var pdata = 'cmd=read'; //post data as string
      var callback;


      /* this will be executed after a successful http request */
      callback = function(ret){
        var ele = document.getElementById('ping_out');
        if( ele.innerHTML !== ret ){
          ele.innerHTML = '';
          ele.innerHTML = ret;

          if( ret.search('PINGDONE') !== -1 ){
            clearInterval(timr2);
            document.getElementById("btn_ping").disabled = false;
            console.log("ended timr2");
          }
        }
      };

      _request.post( 'ping_read', url, pdata, callback);
  }


  /* add rule */
  this.addrule = addrule;
  function addrule( ){
      var url = '/tools/tc_worker.php';
      var tcrule = document.getElementById('tcrule').value;
      var pdata = 'cmd=add&tcrule=' + tcrule; //post data as string
      var callback;


      /* this will be executed after a successful http request */
      callback = function(ret){
        var ele = document.getElementById('tcfeedback');
        if( ele.innerHTML !== ret ){
          ele.innerHTML = '';
          ele.innerHTML = ret;
          document.getElementById("btn_add").disabled = false;
        }
      };

      _request.post( 'add_tc', url, pdata, callback);
  }

  }