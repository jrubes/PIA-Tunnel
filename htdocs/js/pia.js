/*
 * javascript to handle dynamic events for the PIA-Web UI
 */

var _request = new RequestHandler();
"use strict";

/* object handeling "Overview" status updates every few seconds */
function OverviewObj( ){


  /* query get_status.php */
  this.refresh_status = refresh_status;
  function refresh_status(){

    var url = '/get_status.php';
    var pdata = ''; //post data as string
    var callback;

    /* this will be executed after a successful http request */
    callback = function(ret){
      var ele = document.getElementById('system_status');
      ele.innerHTML = '';
      ele.innerHTML = ret;
    };

    _request.post( 'status_update', url, pdata, callback);

  }

  /* remove feedback boxes after a few seconds */
  this.clean_feedback = clean_feedback;
  function clean_feedback(){
    var keep = 3; //keep feedback for at least this many seconds (further delayed by timer)
    var xele = document.getElementById('feedback_expires');
    var fele = document.getElementById('feedback');
    var now = Math.round(Date.now()/1000);
    if( fele && xele  )
    {
      if( xele.value < now ){
        fele.parentNode.removeChild(fele);
      }

    }else if( fele && !xele ){
      //feedback has no timestamp yet, add one
      var time = document.createElement('input');
      time.setAttribute('type', 'hidden');
      time.setAttribute('value', now + keep);
      time.setAttribute('id', 'feedback_expires');
      fele.appendChild(time);
    }
  }

  /* method reconfigured the network control UI when js is enabled*/
  this.set_js_network_control = set_js_network_control;
  function set_js_network_control(){
    var rem = document.getElementById('ele_vpn_connect');
    if( rem ){ rem.parentNode.removeChild(rem); }

    rem = document.getElementById('ele_daemon_lbl');
    if( rem ){ rem.parentNode.removeChild(rem); }

    rem = document.getElementById('ele_firewall_lbl');
    if( rem ){ rem.parentNode.removeChild(rem); }

    rem = document.getElementById('ele_os_lbl');
    if( rem ){ rem.parentNode.removeChild(rem); }

    rem = document.getElementById('overview_net_status');
    rem.setAttribute('class', 'box overview');
    rem.setAttribute('style', 'width: 480px; margin-right: 1em;');

    rem = document.getElementById('overview_net_control');
    rem.setAttribute('class', 'box overview');
    rem.setAttribute('style', 'width: 290px; text-align: center;');



  }


}