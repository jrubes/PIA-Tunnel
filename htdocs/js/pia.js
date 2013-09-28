/*
 * javascript to handle dynamic events for the PIA-Web UI
 */

var _request = new RequestHandler();

/* object handeling "Overview" status updates every few seconds */
function OverviewObj( ){
  "use strict";


  /* query get_status.php */
  this.refresh_status = refresh_status;
  function refresh_status(){

    var url = '/get_status.php?'+Math.random();
    var pdata = ''; //post data as string
    var callback;

    /* this will be executed after the http request */
    callback = function(ret){
      var parsed;
      //parsed = JSON.parse(ret);
      //console.log(parsed);
      var ele = document.getElementById('network_status');
      ele.innerHTML = '';
      ele.innerHTML = ret;

//      for( var x in parsed ){
//        var ele = document.getElementById(x)
//        if( ele ){
//          ele.innerHTML = '';
//          ele.appendChild(document.createTextNode(parsed[x]));
//        }
//        console.log('executed');
//      }
      //_this.build_table(return_ele_id);
    };

    _request.post( 'status_update', url, pdata, callback);

  }
}